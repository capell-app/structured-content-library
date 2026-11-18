<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Models\Site;
use Capell\Frontend\Actions\InvalidateFrontendSurrogateKeysAction;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Retires everything public that may show these records: the package's own list cache, the
 * Core page caches and the fragment and CDN entries of every site the records were or are
 * shown on.
 *
 * A theme or section lists records by type, so no page records which items it showed. The
 * records' cache dependency therefore names Core's page patterns, as a site change does, and
 * a global record reaches every site.
 *
 * @method static void run(iterable<StructuredContentItem> $items, list<int|null> $previousSiteIds = [])
 */
final class InvalidateStructuredContentPublicOutputAction
{
    use AsFake;
    use AsObject;

    /** Core cache patterns a record change retires, beside the package's own list cache. */
    public const array PAGE_CACHE_PATTERNS = [
        'structured-content-library-*',
        'page-*',
        'homepage-*',
        'system-page-*',
    ];

    /**
     * @param  iterable<StructuredContentItem>  $items
     * @param  list<int|null>  $previousSiteIds  Sites the records were shown on before the change.
     */
    public function handle(iterable $items, array $previousSiteIds = []): void
    {
        StructuredContentCache::flush();

        if (! class_exists(CacheInvalidationRegistry::class) || ! app()->bound(CacheInvalidationRegistry::class)) {
            return;
        }

        $registry = resolve(CacheInvalidationRegistry::class);
        $siteIds = $previousSiteIds;
        $changed = false;

        foreach ($items as $item) {
            $registry->invalidateChangedModel($item);
            $siteIds[] = $item->site_id;
            $changed = true;
        }

        if (! $changed) {
            $registry->invalidateForModel(StructuredContentItem::class);
        }

        $surrogateKeys = array_map(
            static fn (int $siteId): string => 'site-' . $siteId,
            $this->affectedSiteIds($siteIds),
        );

        InvalidateFrontendSurrogateKeysAction::run($surrogateKeys);
    }

    /**
     * @param  list<int|null>  $siteIds
     * @return list<int>
     */
    private function affectedSiteIds(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        // A record shared with every site appears on all of them.
        if (in_array(null, $siteIds, true)) {
            $ids = [];

            foreach (Site::query()->orderBy('id')->pluck('id') as $id) {
                if (is_numeric($id)) {
                    $ids[] = (int) $id;
                }
            }

            return $ids;
        }

        $unique = array_values(array_unique(array_map(static fn (?int $siteId): int => (int) $siteId, $siteIds)));
        sort($unique);

        return $unique;
    }
}
