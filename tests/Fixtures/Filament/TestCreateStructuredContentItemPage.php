<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures\Filament;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Illuminate\Database\Eloquent\Model;

final class TestCreateStructuredContentItemPage extends CreateStructuredContentItem
{
    /**
     * @param  array<string, mixed>  $data
     */
    public function createRecordForTest(array $data): Model
    {
        return $this->handleRecordCreation($data);
    }
}
