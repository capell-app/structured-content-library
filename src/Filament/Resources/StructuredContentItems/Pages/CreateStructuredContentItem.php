<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages;

use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Override;

class CreateStructuredContentItem extends CreateRecord
{
    protected static string $resource = StructuredContentItemResource::class;

    /**
     * @param  array<string, mixed>  $data
     */
    #[Override]
    protected function handleRecordCreation(array $data): Model
    {
        return CreateStructuredContentItemAction::run(StructuredContentItemData::from($data));
    }

    #[Override]
    protected function getCreateFormAction(): Action
    {
        return parent::getCreateFormAction()
            ->label(__('capell-structured-content-library::admin.create_action'));
    }
}
