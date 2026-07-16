<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<PublicStructuredContentItemData> run(StructuredContentType $type, ?int $siteId = null, ?int $limit = null)
 */
class BuildPublicStructuredContentItemsAction
{
    use AsFake;
    use AsObject;

    /**
     * @return list<PublicStructuredContentItemData>
     */
    public function handle(StructuredContentType $type, ?int $siteId = null, ?int $limit = null): array
    {
        $itemsByType = BuildPublicStructuredContentItemsForTypesAction::run([$type], $siteId);
        $items = $this->itemsForType($itemsByType, $type);

        if ($limit !== null) {
            return array_slice($items, 0, max(0, $limit));
        }

        return $items;
    }

    /**
     * @param  array<string, list<PublicStructuredContentItemData>>  $itemsByType
     * @return list<PublicStructuredContentItemData>
     */
    private function itemsForType(array $itemsByType, StructuredContentType $type): array
    {
        return $itemsByType[$type->value] ?? [];
    }
}
