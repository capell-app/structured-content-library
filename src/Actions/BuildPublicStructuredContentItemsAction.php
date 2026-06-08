<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static list<PublicStructuredContentItemData> run(StructuredContentType $type, ?int $siteId = null, ?int $limit = null)
 */
class BuildPublicStructuredContentItemsAction
{
    use AsObject;

    /**
     * @return list<PublicStructuredContentItemData>
     */
    public function handle(StructuredContentType $type, ?int $siteId = null, ?int $limit = null): array
    {
        $itemsByType = BuildPublicStructuredContentItemsForTypesAction::run([$type], $siteId);
        $items = $itemsByType[$type->value] ?? [];

        if ($limit !== null) {
            return array_slice($items, 0, max(0, $limit));
        }

        return $items;
    }
}
