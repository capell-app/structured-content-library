<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures\Filament;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Illuminate\Database\Eloquent\Model;

final class TestEditStructuredContentItemPage extends EditStructuredContentItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRecordForTest(Model $record, array $data): Model
    {
        return $this->handleRecordUpdate($record, $data);
    }
}
