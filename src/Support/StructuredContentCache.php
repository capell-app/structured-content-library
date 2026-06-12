<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Closure;
use DateTimeInterface;
use Illuminate\Cache\TaggedCache;
use Illuminate\Contracts\Cache\Repository;
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
     * @param  Closure(): array<string, list<PublicStructuredContentItemData>>  $callback
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

        $cached = self::store()->remember(self::key('public-items-by-types', [
            'locale' => app()->getLocale(),
            'site_id' => $siteId,
            'types' => implode(',', $typeValues),
        ]), self::expiresAt(), $callback);

        return is_array($cached) ? $cached : $callback();
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

    private static function expiresAt(): DateTimeInterface
    {
        $configuredTtl = config('capell-structured-content-library.public_cache_ttl_seconds', 300);
        $ttl = is_numeric($configuredTtl) ? max(1, (int) $configuredTtl) : 300;

        return now()->addSeconds($ttl);
    }

    private static function version(): int
    {
        $version = Cache::get(self::VERSION_KEY, 1);

        return is_numeric($version) ? max(1, (int) $version) : 1;
    }
}
