<?php

declare(strict_types=1);

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Media;
use Capell\Core\Support\Cache\CapellCacheManager;
use Capell\Frontend\Support\Cache\FragmentCache;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\InvalidateDueScheduledStructuredContentCachesAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryWithFrontendTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Cache;

uses(StructuredContentLibraryWithFrontendTestCase::class);

function cachePageHtml(): CapellCacheManager
{
    $cache = resolve(CapellCacheManager::class);
    $cache->rememberCache('page-12-1-html', static fn (): string => '<p>Cached page listing testimonials</p>');
    $cache->rememberCache('homepage-1-1', static fn (): string => '<p>Cached home page</p>');
    $cache->rememberCache('unrelated-cache-entry', static fn (): string => 'Unrelated');

    return $cache;
}

it('clears cached pages when a record is created, updated or deleted', function (string $change): void {
    $item = $change === 'create' ? null : StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create();
    $cache = cachePageHtml();

    match ($change) {
        'create' => StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create(),
        'update' => $item?->update(['title' => 'Updated testimonial']),
        'delete' => $item?->delete(),
        default => throw new InvalidArgumentException($change),
    };

    expect($cache->getFromCache('page-12-1-html'))->toBeNull()
        ->and($cache->getFromCache('homepage-1-1'))->toBeNull()
        ->and($cache->getFromCache('unrelated-cache-entry'))->toBe('Unrelated');
})->with(['create', 'update', 'delete']);

function cacheSiteFragment(int $siteId): void
{
    resolve(FragmentCache::class)->remember('testimonials-' . $siteId, static fn (): string => 'old', 600, ['site-' . $siteId]);
}

function siteFragmentWasPurged(int $siteId): bool
{
    return resolve(FragmentCache::class)->remember('testimonials-' . $siteId, static fn (): string => 'new', 600, ['site-' . $siteId]) === 'new';
}

it('purges the fragments of every site a record moves between and no others', function (): void {
    $from = $this->createStructuredContentSite('Old site');
    $to = $this->createStructuredContentSite('New site');
    $untouched = $this->createStructuredContentSite('Untouched site');
    $item = StructuredContentItem::factory()->published()->create(['site_id' => $from]);
    array_map(cacheSiteFragment(...), [$from, $to, $untouched]);

    $item->update(['site_id' => $to]);

    expect(siteFragmentWasPurged($from))->toBeTrue()
        ->and(siteFragmentWasPurged($to))->toBeTrue()
        ->and(siteFragmentWasPurged($untouched))->toBeFalse();
});

it('purges the fragments of every site for a record shared with all sites', function (): void {
    $first = $this->createStructuredContentSite('First site');
    $second = $this->createStructuredContentSite('Second site');
    array_map(cacheSiteFragment(...), [$first, $second]);

    StructuredContentItem::factory()->published()->create(['site_id' => null]);

    expect(siteFragmentWasPurged($first))->toBeTrue()
        ->and(siteFragmentWasPurged($second))->toBeTrue();
});

it('clears the public list and cached pages when a record picture changes', function (): void {
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::Logo)->create();
    expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Logo)[0]->logo)->toBeNull();
    $cache = cachePageHtml();

    Media::factory()->model($item)->create([
        'collection_name' => MediaCollectionEnum::Logo->value,
        'file_name' => 'acme.png',
        'mime_type' => 'image/png',
    ]);

    expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Logo)[0]->logo)->not->toBeNull()
        ->and($cache->getFromCache('page-12-1-html'))->toBeNull();
});

it('clears cached pages once a scheduled record publication time passes', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-06 08:59:00', 'UTC'));
    Cache::forget(InvalidateDueScheduledStructuredContentCachesAction::CHECKPOINT_CACHE_KEY);
    StructuredContentItem::factory()->type(StructuredContentType::Testimonial)->create([
        'status' => StructuredContentStatus::Published,
        'published_at' => CarbonImmutable::parse('2026-07-06 09:00:00', 'UTC'),
    ]);
    InvalidateDueScheduledStructuredContentCachesAction::run();
    $cache = cachePageHtml();

    expect(InvalidateDueScheduledStructuredContentCachesAction::run())->toBe(0)
        ->and($cache->getFromCache('page-12-1-html'))->not->toBeNull();

    $this->travelTo(CarbonImmutable::parse('2026-07-06 09:00:30', 'UTC'));

    expect(InvalidateDueScheduledStructuredContentCachesAction::run())->toBe(1)
        ->and($cache->getFromCache('page-12-1-html'))->toBeNull()
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial))->toHaveCount(1);

    $cache = cachePageHtml();
    $this->travelTo(CarbonImmutable::parse('2026-07-06 09:01:30', 'UTC'));

    expect(InvalidateDueScheduledStructuredContentCachesAction::run())->toBe(0)
        ->and($cache->getFromCache('page-12-1-html'))->not->toBeNull();
});

it('ignores drafts whose publication time passes', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-06 09:00:30', 'UTC'));
    StructuredContentItem::factory()->type(StructuredContentType::Testimonial)->create([
        'status' => StructuredContentStatus::Draft,
        'published_at' => CarbonImmutable::parse('2026-07-06 09:00:00', 'UTC'),
    ]);

    expect(InvalidateDueScheduledStructuredContentCachesAction::run())->toBe(0);
});

it('schedules the publication sweep every minute on one server', function (): void {
    $event = collect(resolve(Schedule::class)->events())
        ->first(static fn (Event $event): bool => str_contains((string) $event->command, 'structured-content-library:invalidate-due-caches'));

    expect($event)->toBeInstanceOf(Event::class);
    assert($event instanceof Event);
    expect($event->expression)->toBe('* * * * *')
        ->and($event->withoutOverlapping)->toBeTrue()
        ->and($event->onOneServer)->toBeTrue();
});

it('runs the publication sweep from the console', function (): void {
    expect(Artisan::call('structured-content-library:invalidate-due-caches'))->toBe(0);
});
