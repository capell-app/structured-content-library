<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Illuminate\Support\Collection;
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
            fn (): array => $this->build($types, $siteId),
        );
    }

    /**
     * @param  list<StructuredContentType>  $types
     * @return array<string, list<PublicStructuredContentItemData>>
     */
    private function build(array $types, ?int $siteId): array
    {
        /** @var Collection<int, StructuredContentItem> $items */
        $items = StructuredContentItem::query()
            ->whereIn('type', array_map(
                static fn (StructuredContentType $type): string => $type->value,
                $types,
            ))
            ->visibleToSite($siteId)
            ->published()
            ->ordered()
            ->get();

        $groupedItems = [];

        foreach ($items as $item) {
            $groupedItems[$item->type->value][] = $this->publicData($item);
        }

        return $groupedItems;
    }

    private function publicData(StructuredContentItem $item): PublicStructuredContentItemData
    {
        return new PublicStructuredContentItemData(
            type: $item->type,
            title: $item->title,
            slug: $item->slug,
            summary: $item->summary,
            content: $item->content,
            payload: BuildPublicStructuredContentPayloadAction::run($item->payload),
        );
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
