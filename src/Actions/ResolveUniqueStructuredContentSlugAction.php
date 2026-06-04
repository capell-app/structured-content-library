<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsObject;

final class ResolveUniqueStructuredContentSlugAction
{
    use AsObject;

    public function handle(
        StructuredContentType $type,
        ?int $siteId,
        ?string $slugSource,
        ?StructuredContentItem $ignoredItem = null,
    ): ?string {
        $baseSlug = Str::slug((string) $slugSource);

        if ($baseSlug === '') {
            return null;
        }

        $candidateSlug = $baseSlug;
        $suffix = 2;

        while ($this->slugExists($type, $siteId, $candidateSlug, $ignoredItem)) {
            $candidateSlug = $baseSlug . '-' . $suffix;
            $suffix++;
        }

        return $candidateSlug;
    }

    private function slugExists(
        StructuredContentType $type,
        ?int $siteId,
        string $slug,
        ?StructuredContentItem $ignoredItem,
    ): bool {
        /** @var Builder<StructuredContentItem> $query */
        $query = StructuredContentItem::query()
            ->withTrashed()
            ->where('type', $type)
            ->where('site_id', $siteId)
            ->where('slug', $slug);

        if ($ignoredItem instanceof StructuredContentItem) {
            $query->whereKeyNot($ignoredItem->getKey());
        }

        return $query->exists();
    }
}
