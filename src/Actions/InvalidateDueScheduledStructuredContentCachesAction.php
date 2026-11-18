<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Throwable;

/**
 * Retires cached pages when a published record's publication time passes, so a testimonial
 * scheduled for Monday appears on Monday instead of at the next unrelated invalidation.
 * Core's scheduled-publication sweep only knows pages. Each run covers the time since the
 * last run, with a short overlap so a record due at the boundary is never skipped.
 *
 * @method static int run(?CarbonImmutable $until = null)
 */
final class InvalidateDueScheduledStructuredContentCachesAction
{
    use AsFake;
    use AsObject;

    public const string CHECKPOINT_CACHE_KEY = 'capell:structured-content-library:scheduled-publication-checkpoint';

    /** Used when no checkpoint survives (first run, evicted cache): wide enough to cover a deploy or scheduler gap. */
    private const int FALLBACK_SCAN_MINUTES = 1440;

    private const int CHECKPOINT_OVERLAP_SECONDS = 5;

    public function handle(?CarbonImmutable $until = null): int
    {
        $until ??= CarbonImmutable::now();
        $from = $this->checkpoint($until);
        $invalidated = 0;

        StructuredContentItem::query()
            ->where('status', StructuredContentStatus::Published)
            ->where(static function (Builder $query) use ($from, $until): void {
                StructuredContentItem::constrainToBoundariesBetween($query, $from, $until);
            })
            ->chunkById(100, static function (Collection $items) use (&$invalidated): void {
                InvalidateStructuredContentPublicOutputAction::run($items->all());
                $invalidated += $items->count();
            });

        Cache::forever(self::CHECKPOINT_CACHE_KEY, $until->toIso8601String());

        return $invalidated;
    }

    private function checkpoint(CarbonImmutable $until): CarbonImmutable
    {
        $stored = Cache::get(self::CHECKPOINT_CACHE_KEY);

        if (! is_string($stored)) {
            return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
        }

        try {
            $from = CarbonImmutable::parse($stored)->setTimezone($until->getTimezone());
        } catch (Throwable) {
            return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
        }

        if ($from->greaterThan($until)) {
            return $until->subMinutes(self::FALLBACK_SCAN_MINUTES);
        }

        return $from->subSeconds(self::CHECKPOINT_OVERLAP_SECONDS);
    }
}
