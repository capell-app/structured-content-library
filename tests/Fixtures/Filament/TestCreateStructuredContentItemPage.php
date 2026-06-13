<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures\Filament;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use RuntimeException;

final class TestCreateStructuredContentItemPage extends CreateStructuredContentItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createRecordForTest(array $data): StructuredContentItem
    {
        $record = $this->handleRecordCreation($data);

        throw_unless($record instanceof StructuredContentItem, RuntimeException::class);

        return $record;
    }
}
