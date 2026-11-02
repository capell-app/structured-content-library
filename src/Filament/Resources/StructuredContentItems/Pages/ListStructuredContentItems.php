<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages;

use Capell\StructuredContentLibrary\Actions\MergeStructuredContentReorderAction;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Database\Eloquent\Builder;
use Override;

class ListStructuredContentItems extends ListRecords
{
    protected static string $resource = StructuredContentItemResource::class;

    /**
     * @param  array<int|string>  $order
     */
    #[Override]
    public function reorderTable(array $order, int|string|null $draggedRecordKey = null): void
    {
        if (! $this->getTable()->isReorderable()) {
            return;
        }

        /** @var Builder<StructuredContentItem> $reorderable */
        $reorderable = $this->getFilteredTableQuery();
        if ($reorderable === null) {
            return;
        }

        $this->getTable()->callBeforeReordering($order);
        $changed = MergeStructuredContentReorderAction::run($reorderable, $order);
        if ($changed === []) {
            return;
        }

        $this->flushCachedTableRecords();
        $this->getTable()->callAfterReordering($changed);
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('capell-structured-content-library::admin.create_action')),
        ];
    }
}
