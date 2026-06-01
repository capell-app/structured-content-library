<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsObject;

class BuildPublicStructuredContentItemsAction
{
    use AsObject;

    /**
     * @return list<PublicStructuredContentItemData>
     */
    public function handle(StructuredContentType $type, ?int $siteId = null, ?int $limit = null): array
    {
        /** @var Collection<int, StructuredContentItem> $items */
        $items = ListStructuredContentItemsAction::run($type, $siteId);

        if ($limit !== null) {
            $items = $items->take(max(0, $limit));
        }

        return $items
            ->map(static fn (StructuredContentItem $item): PublicStructuredContentItemData => new PublicStructuredContentItemData(
                type: $item->type,
                title: $item->title,
                slug: $item->slug,
                summary: $item->summary,
                content: $item->content,
                payload: $item->payload?->toArray() ?? [],
            ))
            ->values()
            ->all();
    }
}
