<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support;

use Capell\StructuredContentLibrary\Actions\EnsurePortableContentHtmlAction;
use Capell\StructuredContentLibrary\Actions\ResolveStructuredContentCacheExpiryAction;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Closure;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use JsonException;
use Throwable;

final class StructuredContentCache
{
    private const string PREFIX = 'capell-structured-content-library';

    private const string TAG = 'structured-content-library';

    private const string VERSION_KEY = self::PREFIX . ':version';

    /**
     * @param  list<StructuredContentType>  $types
     * @param  Closure(Collection<int, StructuredContentItem>): array<string, list<PublicStructuredContentItemData>>  $callback
     * @return array<string, list<PublicStructuredContentItemData>>
     */
    public static function rememberPublicItemsByTypes(array $types, ?int $siteId, Closure $callback): array
    {
        $typeValues = array_map(
            static fn (StructuredContentType $type): string => $type->value,
            $types,
        );
        $typeValues = array_values(array_unique($typeValues));
        sort($typeValues);

        $cacheKey = self::key('public-items-by-types-v2', [
            'locale' => app()->getLocale(),
            'site_id' => $siteId,
            'types' => implode(',', $typeValues),
        ]);
        $store = self::store();
        $payload = $store->get($cacheKey);

        if (is_array($payload)) {
            try {
                $items = self::publicItemsFromPayload($payload);

                if (is_array($items)) {
                    return $items;
                }
            } catch (Throwable) {
                //
            }
        }

        if ($payload !== null) {
            $store->forget($cacheKey);
        }

        // Share one snapshot for public items and their next publication boundary.
        // Fix the expiry before projection so a boundary crossed during the build cannot cache stale output.
        $readAt = now()->toImmutable();
        $records = StructuredContentItem::query()
            ->whereIn('type', $types)
            ->visibleToSite($siteId)
            ->where('status', StructuredContentStatus::Published)
            ->ordered()
            ->get();
        $expiresAt = ResolveStructuredContentCacheExpiryAction::run($types, $siteId, $records, $readAt);
        $items = $callback($records->filter(
            static fn (StructuredContentItem $item): bool => $item->published_at === null || $item->published_at->lessThanOrEqualTo($readAt),
        ));

        $store->put($cacheKey, self::publicItemsPayload($items), $expiresAt);

        return $items;
    }

    public static function flush(): void
    {
        Cache::forever(self::VERSION_KEY, self::version() + 1);

        try {
            if (Cache::supportsTags()) {
                Cache::tags([self::TAG])->flush();
            }
        } catch (Throwable) {
            //
        }
    }

    private static function store(): Repository
    {
        try {
            if (Cache::supportsTags()) {
                $store = Cache::tags([self::TAG]);

                if ($store instanceof TaggedCache) {
                    return $store;
                }
            }
        } catch (Throwable) {
            //
        }

        return Cache::store();
    }

    /**
     * @param  array<string, list<PublicStructuredContentItemData>>  $itemsByType
     * @return array<string, list<array<string, mixed>>>
     */
    private static function publicItemsPayload(array $itemsByType): array
    {
        $payload = [];

        foreach ($itemsByType as $type => $items) {
            $payload[$type] = array_map(
                static fn (PublicStructuredContentItemData $item): array => $item->toArray(),
                $items,
            );
        }

        return $payload;
    }

    /**
     * @param  array<mixed>  $payload
     * @return array<string, list<PublicStructuredContentItemData>>|null
     */
    private static function publicItemsFromPayload(array $payload): ?array
    {
        $itemsByType = [];

        foreach ($payload as $type => $items) {
            if (! is_string($type) || ! is_array($items)) {
                return null;
            }

            $itemsByType[$type] = [];

            foreach ($items as $item) {
                if (! is_array($item)) {
                    return null;
                }

                // Cached payloads may predate the current write validation.
                foreach (['content', 'summary'] as $field) {
                    if (is_string($item[$field] ?? null)) {
                        $item[$field] = EnsurePortableContentHtmlAction::run($item[$field], $field);
                    }
                }

                $itemsByType[$type][] = PublicStructuredContentItemData::from($item);
            }
        }

        return $itemsByType;
    }

    /**
     * @param  array<string, bool|int|string|null>  $parts
     */
    private static function key(string $name, array $parts): string
    {
        try {
            $encodedParts = json_encode($parts, JSON_THROW_ON_ERROR);
        } catch (JsonException) {
            $encodedParts = serialize($parts);
        }

        return implode(':', [
            self::PREFIX,
            self::version(),
            $name,
            hash('sha256', $encodedParts),
        ]);
    }

    private static function version(): int
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return is_numeric($version) ? max(1, (int) $version) : 1;
    }
}
