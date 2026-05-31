<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;
use Override;

class ListStructuredContentItems extends ListRecords
{
    protected static string $resource = StructuredContentItemResource::class;

    #[Override]
    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label(__('capell-structured-content-library::admin.create_action')),
        ];
    }
}
