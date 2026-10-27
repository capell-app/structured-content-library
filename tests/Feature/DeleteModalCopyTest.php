<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Feature;

use Capell\Admin\Providers\AdminServiceProvider;
use Capell\Admin\Providers\Filament\AdminPanelProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Frontend\Providers\FrontendServiceProvider;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\Tests\AbstractTestCase;
use Capell\Tests\Support\Concerns\CreatesAdminUser;
use Closure;
use Filament\Actions\Action;
use Filament\Actions\Contracts\HasActions;
use Filament\Tables\Contracts\HasTable;
use Illuminate\Database\Eloquent\Model;
use Iterator;
use Livewire\Component;
use Livewire\Livewire;
use Livewire\LivewireServiceProvider;
use Livewire\Mechanisms\ExtendBlade\ExtendBlade;
use LogicException;
use Override;
use PHPUnit\Framework\Attributes\DataProvider;

final class DeleteModalCopyTest extends AbstractTestCase
{
    use CreatesAdminUser;

    /** @return Iterator<string, array{class-string<Component>, Closure(): Model, string, string, string, bool}> */
    public static function deleteActions(): Iterator
    {
        yield 'EditStructuredContentItem DeleteAction' => [EditStructuredContentItem::class, fn (): Model => StructuredContentItem::factory()->create(), 'page', 'delete', 'capell-structured-content-library::structured_content_item.delete_modal', false];
        yield 'EditStructuredContentItem ForceDeleteAction' => [EditStructuredContentItem::class, fn (): Model => StructuredContentItem::factory()->create(), 'page', 'forceDelete', 'capell-structured-content-library::structured_content_item.force_delete_modal', true];
        yield 'StructuredContentItemResource DeleteAction' => [ListStructuredContentItems::class, fn (): Model => StructuredContentItem::factory()->create(), 'table', 'delete', 'capell-structured-content-library::structured_content_item.delete_modal', false];
        yield 'StructuredContentItemResource DeleteBulkAction' => [ListStructuredContentItems::class, fn (): Model => StructuredContentItem::factory()->create(), 'bulk', 'delete', 'capell-structured-content-library::structured_content_item.delete_bulk_modal', false];
        yield 'StructuredContentItemResource ForceDeleteBulkAction' => [ListStructuredContentItems::class, fn (): Model => StructuredContentItem::factory()->create(), 'bulk', 'forceDelete', 'capell-structured-content-library::structured_content_item.force_delete_bulk_modal', true];
    }

    /**
     * @param  class-string<Component>  $page
     * @param  Closure(): Model  $makeRecord
     */
    #[DataProvider('deleteActions')]
    public function test_it_renders_translated_delete_consequences(string $page, Closure $makeRecord, string $mode, string $actionName, string $translation, bool $force): void
    {
        $this->actingAsAdmin();
        $heading = __($translation . '.heading');
        $description = __($translation . '.description');

        expect($heading)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.heading')
            ->and($description)->toBeString()->not->toBeEmpty()->not->toBe($translation . '.description');

        $record = $makeRecord();

        $parameters = $mode === 'page' ? ['record' => $record->getRouteKey()] : [];
        $component = Livewire::test($page, $parameters)->assertSuccessful();
        if ($force) {
            $record->delete();
            $surface = $component->instance();
            if ($mode === 'page') {
                throw_unless($surface instanceof HasActions, LogicException::class);
                $action = $surface->getAction($actionName);
            } else {
                throw_unless($surface instanceof HasTable, LogicException::class);
                $action = $surface->getTable()->getBulkAction($actionName);
            }

            throw_unless($action instanceof Action, LogicException::class);
            // Render the real action in its page context without rehydrating a trashed route record.
            $blade = resolve(ExtendBlade::class);
            $blade->startLivewireRendering($surface);
            try {
                $html = $action->record($record)->toModalHtmlable()->toHtml();
            } finally {
                $blade->endLivewireRendering();
            }

            expect($html)->toContain(e($heading), e($description));

            return;
        }

        if ($mode === 'page') {
            $component->mountAction($actionName);
        } else {
            if ($mode === 'bulk') {
                $component->mountTableBulkAction($actionName, [$record->getKey()]);
            } else {
                $component->mountTableAction($actionName, $record);
            }
        }

        $component->assertMountedActionModalSee($heading)->assertMountedActionModalSee($description);
    }

    #[Override]
    protected function getPackageServiceName(): string
    {
        return 'capell-structured-content-library';
    }

    /** @return list<class-string> */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [...array_values(parent::getPackageProviders($app)), AdminServiceProvider::class, FrontendServiceProvider::class, StructuredContentLibraryServiceProvider::class, AdminPanelProvider::class, LivewireServiceProvider::class];
    }

    #[Override]
    protected function getEnvironmentSetUp(mixed $app): void
    {
        parent::getEnvironmentSetUp($app);
        CapellCore::forcePackageInstalled(AdminServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(FrontendServiceProvider::$packageName);
        CapellCore::forcePackageInstalled(StructuredContentLibraryServiceProvider::$packageName);
    }
}
