<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static DateTimeInterface run(list<StructuredContentType> $types, ?int $siteId, Collection<int, StructuredContentItem>|null $items = null, CarbonImmutable|null $readAt = null) */
final class ResolveStructuredContentCacheExpiryAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  list<StructuredContentType>  $types
     * @param  Collection<int, StructuredContentItem>|null  $items  Published records already scoped to the requested site and types.
     */
    public function handle(array $types, ?int $siteId, ?Collection $items = null, ?CarbonImmutable $readAt = null): DateTimeInterface
    {
        $readAt ??= now()->toImmutable();
        $configuredTtl = config('capell-structured-content-library.public_cache_ttl_seconds', 300);
        $ttl = is_numeric($configuredTtl) ? max(1, (int) $configuredTtl) : 300;
        $expiry = $readAt->addSeconds($ttl);
        $items ??= StructuredContentItem::query()
            ->whereIn('type', $types)
            ->visibleToSite($siteId)
            ->where('status', StructuredContentStatus::Published)
            ->where('published_at', '>', $readAt)
            ->orderBy('published_at')
            ->limit(1)
            ->get();

        foreach ($items as $item) {
            $publication = $item->published_at;

            if ($publication !== null && $publication->greaterThan($readAt) && $publication->lessThan($expiry)) {
                $expiry = $publication;
            }
        }

        return $expiry;
    }
}
