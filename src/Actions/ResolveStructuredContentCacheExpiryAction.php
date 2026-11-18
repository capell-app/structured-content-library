<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static DateTimeInterface run(list<StructuredContentType> $types, ?int $siteId, Collection<int, StructuredContentItem>|null $items = null, CarbonImmutable|null $readAt = null) */
final class ResolveStructuredContentCacheExpiryAction
{
    use AsFake;
    use AsObject;

    /**
     * The earlier of the configured lifetime and the next moment a record of these types in this
     * site scope is published or expires, since either changes the public list.
     *
     * @param  list<StructuredContentType>  $types
     * @param  Collection<int, StructuredContentItem>|null  $items  Published records already scoped to the requested site and types; null reads the next boundary from the database.
     */
    public function handle(array $types, ?int $siteId, ?Collection $items = null, ?CarbonImmutable $readAt = null): DateTimeInterface
    {
        $readAt ??= now()->toImmutable();
        $configuredTtl = config('capell-structured-content-library.public_cache_ttl_seconds', 300);
        $ttl = is_numeric($configuredTtl) ? max(1, (int) $configuredTtl) : 300;
        $expiry = $readAt->addSeconds($ttl);

        foreach ($this->boundaries($types, $siteId, $items, $readAt) as $boundary) {
            if ($boundary->greaterThan($readAt) && $boundary->lessThan($expiry)) {
                $expiry = $boundary;
            }
        }

        return $expiry;
    }

    /**
     * @param  list<StructuredContentType>  $types
     * @param  Collection<int, StructuredContentItem>|null  $items
     * @return list<CarbonImmutable>
     */
    private function boundaries(array $types, ?int $siteId, ?Collection $items, CarbonImmutable $readAt): array
    {
        if ($items instanceof Collection) {
            $boundaries = [];

            foreach ($items as $item) {
                foreach ([$item->published_at, $item->expires_at] as $boundary) {
                    if ($boundary instanceof CarbonImmutable) {
                        $boundaries[] = $boundary;
                    }
                }
            }

            return $boundaries;
        }

        // One query answers both boundaries.
        $next = StructuredContentItem::query()
            ->whereIn('type', $types)
            ->visibleToSite($siteId)
            ->where('status', StructuredContentStatus::Published)
            ->where(static function (Builder $query) use ($readAt): void {
                $query->where('published_at', '>', $readAt)->orWhere('expires_at', '>', $readAt);
            })
            ->toBase()
            ->selectRaw('min(case when published_at > ? then published_at end) as next_published_at', [$readAt])
            ->selectRaw('min(case when expires_at > ? then expires_at end) as next_expires_at', [$readAt])
            ->first();

        $boundaries = [];

        foreach (['next_published_at', 'next_expires_at'] as $column) {
            $value = is_object($next) ? ($next->{$column} ?? null) : null;

            if (is_scalar($value) && $value !== '') {
                $boundaries[] = CarbonImmutable::parse((string) $value);
            }
        }

        return $boundaries;
    }
}
