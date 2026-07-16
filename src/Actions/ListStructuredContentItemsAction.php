<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Support\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class ListStructuredContentItemsAction
{
    use AsFake;
    use AsObject;

    /**
     * @return Collection<int, StructuredContentItem>
     */
    public function handle(StructuredContentType $type, ?int $siteId = null, bool $publishedOnly = true): Collection
    {
        $query = StructuredContentItem::query()
            ->forType($type)
            ->visibleToSite($siteId)
            ->ordered();

        if ($publishedOnly) {
            $query->published();
        }

        return $query->get();
    }
}
