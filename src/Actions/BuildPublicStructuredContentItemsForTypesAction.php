<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static array<string, list<PublicStructuredContentItemData>> run(list<StructuredContentType> $types, ?int $siteId = null)
 */
final class BuildPublicStructuredContentItemsForTypesAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  list<StructuredContentType>  $types
     * @return array<string, list<PublicStructuredContentItemData>>
     */
    public function handle(array $types, ?int $siteId = null): array
    {
        $types = $this->uniqueTypes($types);

        if ($types === []) {
            return [];
        }

        return StructuredContentCache::rememberPublicItemsByTypes(
            $types,
            $siteId,
            fn (Collection $items): array => $this->build($items),
        );
    }

    /**
     * @param  Collection<int, StructuredContentItem>  $items
     * @return array<string, list<PublicStructuredContentItemData>>
     */
    private function build(Collection $items): array
    {
        $groupedItems = [];

        foreach ($items as $item) {
            $groupedItems[$item->type->value][] = BuildPublicStructuredContentItemDataAction::run($item);
        }

        return $groupedItems;
    }

    /**
     * @param  list<StructuredContentType>  $types
     * @return list<StructuredContentType>
     */
    private function uniqueTypes(array $types): array
    {
        $uniqueTypes = [];

        foreach ($types as $type) {
            $uniqueTypes[$type->value] = $type;
        }

        ksort($uniqueTypes);

        return array_values($uniqueTypes);
    }
}
