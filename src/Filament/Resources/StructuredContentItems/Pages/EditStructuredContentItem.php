<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages;

use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemDataAction;
use Capell\StructuredContentLibrary\Actions\BuildStructuredContentTranslationFormStateAction;
use Capell\StructuredContentLibrary\Actions\RestoreStructuredContentRevisionAction;
use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Bridges\PublishingStudioRevisionsHeaderActionBridge;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentRevisions;
use Carbon\CarbonImmutable;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Forms\Components\Select;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class EditStructuredContentItem extends EditRecord
{
    protected static string $resource = StructuredContentItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    #[Override]
    protected function mutateFormDataBeforeFill(array $data): array
    {
        foreach (['content', 'summary'] as $field) {
            $text = $data[$field] ?? null;
            // The database stores HTML/text; a JSON-looking value is never an editor document.
            if (is_string($text) && json_validate($text)) {
                $data[$field] = '<p>' . $text . '</p>';
            }
        }

        $record = $this->getRecord();

        if ($record instanceof StructuredContentItem) {
            $data['translations'] = BuildStructuredContentTranslationFormStateAction::run($record);
        }

        return $data;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        if (! $record instanceof StructuredContentItem) {
            return $record;
        }

        return UpdateStructuredContentItemAction::run($record, StructuredContentItemData::from($data));
    }

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            Action::make('preview')
                ->label(__('capell-structured-content-library::admin.preview'))
                ->authorize('view')
                ->modalHeading(__('capell-structured-content-library::admin.preview'))
                ->modalDescription(__('capell-structured-content-library::admin.preview_help'))
                ->modalSubmitAction(false)
                ->modalContent(function (StructuredContentItem $record): View {
                    Gate::authorize('view', $record);

                    return view('capell-structured-content-library::admin.preview', [
                        'item' => BuildPublicStructuredContentItemDataAction::run($record),
                    ]);
                }),
            ...$this->revisionActions(),
            RestoreAction::make(),
            DeleteAction::make()
                ->modalHeading(__('capell-structured-content-library::structured_content_item.delete_modal.heading'))
                ->modalDescription(__('capell-structured-content-library::structured_content_item.delete_modal.description')),
            ForceDeleteAction::make()
                ->modalHeading(__('capell-structured-content-library::structured_content_item.force_delete_modal.heading'))
                ->modalDescription(__('capell-structured-content-library::structured_content_item.force_delete_modal.description')),
        ];
    }

    /**
     * Revision history comes from Publishing Studio when it is installed; without it the record
     * keeps no history and these actions are not offered.
     *
     * @return list<Action>
     */
    private function revisionActions(): array
    {
        if (! StructuredContentRevisions::available()) {
            return [];
        }

        $actions = [];

        if (CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            $revisionsAction = PublishingStudioRevisionsHeaderActionBridge::make();

            if ($revisionsAction instanceof Action) {
                $actions[] = $revisionsAction;
            }
        }

        $actions[] = Action::make('restoreRevision')
            ->label(__('capell-structured-content-library::admin.revision_restore'))
            ->icon('heroicon-o-arrow-uturn-left')
            ->color('gray')
            ->authorize('update')
            ->visible(fn (StructuredContentItem $record): bool => StructuredContentRevisions::history($record) !== [])
            ->modalDescription(__('capell-structured-content-library::admin.revision_restore_help'))
            ->modalSubmitActionLabel(__('capell-structured-content-library::admin.revision_restore_confirm'))
            ->schema([
                Select::make('revision')
                    ->label(__('capell-structured-content-library::admin.revision'))
                    ->options(fn (StructuredContentItem $record): array => $this->revisionOptions($record))
                    ->required(),
            ])
            ->action(function (StructuredContentItem $record, array $data): void {
                $revision = $data['revision'] ?? null;

                if (! is_numeric($revision)) {
                    return;
                }

                $this->record = RestoreStructuredContentRevisionAction::run($record, (int) $revision);
                $this->fillForm();

                Notification::make()
                    ->success()
                    ->title(__('capell-structured-content-library::admin.revision_restored'))
                    ->send();
            });

        return $actions;
    }

    /**
     * @return array<int, string>
     */
    private function revisionOptions(StructuredContentItem $record): array
    {
        $options = [];

        foreach (StructuredContentRevisions::history($record) as $revision) {
            $time = $revision['created_at'] === null
                ? ''
                : CarbonImmutable::parse($revision['created_at'])->setTimezone(FilamentTimezone::get())->settings(['locale' => app()->getLocale()])->translatedFormat('j M Y H:i T');
            $replacements = ['version' => $revision['version'], 'event' => $revision['event'], 'time' => $time];

            $options[$revision['id']] = $revision['changed'] === []
                ? __('capell-structured-content-library::admin.revision_option', $replacements)
                : __('capell-structured-content-library::admin.revision_option_changes', [...$replacements, 'fields' => implode(', ', $revision['changed'])]);
        }

        return $options;
    }
}
