<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages;

use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemDataAction;
use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\ForceDeleteAction;
use Filament\Actions\RestoreAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Gate;
use Override;

class EditStructuredContentItem extends EditRecord
{
    protected static string $resource = StructuredContentItemResource::class;

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
            RestoreAction::make(),
            DeleteAction::make(),
            ForceDeleteAction::make(),
        ];
    }
}
