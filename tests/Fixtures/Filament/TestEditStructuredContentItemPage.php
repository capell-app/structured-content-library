<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures\Filament;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Database\Eloquent\Model;
use RuntimeException;

final class TestEditStructuredContentItemPage extends EditStructuredContentItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function updateRecordForTest(Model $record, array $data): StructuredContentItem
    {
        $updatedRecord = $this->handleRecordUpdate($record, $data);

        throw_unless($updatedRecord instanceof StructuredContentItem, RuntimeException::class);

        return $updatedRecord;
    }
}
