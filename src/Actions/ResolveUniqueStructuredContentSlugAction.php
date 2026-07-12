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
            ->where('slug', $slug);

        // Global items use a null site_id. Branch explicitly so the existence check
        // compiles to `site_id IS NULL` rather than relying on the query builder's
        // implicit null shortcut. This resolver is the only thing guaranteeing slug
        // uniqueness for global items, because the composite unique index
        // (type, site_id, slug) does NOT enforce uniqueness across rows where
        // site_id IS NULL under MySQL — NULLs are treated as distinct, so multiple
        // global rows can share a slug at the database level.
        if ($siteId === null) {
            $query->whereNull('site_id');
        } else {
            $query->where('site_id', $siteId);
        }

        if ($ignoredItem instanceof StructuredContentItem) {
            $query->whereKeyNot($ignoredItem->getKey());
        }

        return $query->exists();
    }
}
