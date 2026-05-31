<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages;

use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;
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
}
