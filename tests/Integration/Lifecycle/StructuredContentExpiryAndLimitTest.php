<?php

declare(strict_types=1);

use Capell\Core\Support\Cache\CapellCacheManager;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Actions\DescribeStructuredContentAvailabilityAction;
use Capell\StructuredContentLibrary\Actions\InvalidateDueScheduledStructuredContentCachesAction;
use Capell\StructuredContentLibrary\Actions\ResolveStructuredContentCacheExpiryAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryWithFrontendTestCase;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

uses(StructuredContentLibraryWithFrontendTestCase::class);

beforeEach(function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-06 09:00:00', 'UTC'));
});

it('stops showing a record once its end time passes', function (): void {
    StructuredContentItem::factory()->published()->type(StructuredContentType::Faq)->create([
        'title' => 'Summer offer', 'expires_at' => CarbonImmutable::parse('2026-07-06 10:00:00', 'UTC'),
    ]);

    expect(StructuredContentItem::query()->published()->count())->toBe(1)
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Faq))->toHaveCount(1);

    $this->travelTo(CarbonImmutable::parse('2026-07-06 10:00:00', 'UTC'));

    expect(StructuredContentItem::query()->published()->count())->toBe(0)
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Faq))->toBe([]);
});

it('ends the public cache at the next end time', function (): void {
    config()->set('capell-structured-content-library.public_cache_ttl_seconds', 300);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Faq)->create([
        'expires_at' => now()->addSeconds(40),
    ]);

    expect(ResolveStructuredContentCacheExpiryAction::run([StructuredContentType::Faq], null)->getTimestamp())
        ->toBe(now()->addSeconds(40)->getTimestamp());
});

it('describes a record ending soon and one that has ended', function (): void {
    $item = StructuredContentItem::factory()->published()->create(['expires_at' => now()->addDay()]);

    expect(DescribeStructuredContentAvailabilityAction::run($item))->toStartWith('Available until');

    $item->expires_at = now()->subMinute()->toImmutable();

    expect(DescribeStructuredContentAvailabilityAction::run($item))->toStartWith('Ended');

    $item->published_at = now()->addHour()->toImmutable();
    $item->expires_at = now()->addDay()->toImmutable();

    expect(DescribeStructuredContentAvailabilityAction::run($item))->toStartWith('Scheduled for')
        ->and(DescribeStructuredContentAvailabilityAction::run($item))->toContain('until');
});

it('writes the availability date in the admin locale', function (): void {
    $item = StructuredContentItem::factory()->published()->create(['expires_at' => CarbonImmutable::parse('2026-10-15 12:00:00', 'UTC')]);
    app()->setLocale('de');

    expect(DescribeStructuredContentAvailabilityAction::run($item))
        ->toContain('15 Okt 2026')
        ->not->toContain('Oct');

    app()->setLocale('en');

    expect(DescribeStructuredContentAvailabilityAction::run($item))->toContain('15 Oct 2026');
});

it('refuses an end time at or before the publish time', function (): void {
    CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Faq,
        title: 'Backwards window',
        status: StructuredContentStatus::Published,
        publishedAt: now()->addDay()->toImmutable(),
        expiresAt: now()->toImmutable(),
    ));
})->throws(ValidationException::class);

it('saves an end time through the create action', function (): void {
    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Faq,
        title: 'Limited offer',
        status: StructuredContentStatus::Published,
        expiresAt: now()->addWeek()->toImmutable(),
    ));

    expect($item->expires_at?->getTimestamp())->toBe(now()->addWeek()->getTimestamp());
});

it('clears cached pages once a record end time passes', function (): void {
    Cache::forget(InvalidateDueScheduledStructuredContentCachesAction::CHECKPOINT_CACHE_KEY);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Faq)->create([
        'expires_at' => CarbonImmutable::parse('2026-07-06 09:05:00', 'UTC'),
    ]);
    InvalidateDueScheduledStructuredContentCachesAction::run();
    $cache = resolve(CapellCacheManager::class);
    $cache->rememberCache('page-7-1-html', static fn (): string => 'Page showing the offer');

    $this->travelTo(CarbonImmutable::parse('2026-07-06 09:05:20', 'UTC'));

    expect(InvalidateDueScheduledStructuredContentCachesAction::run())->toBe(1)
        ->and($cache->getFromCache('page-7-1-html'))->toBeNull();
});

/**
 * @return list<string>
 */
function structuredContentItemSelects(Closure $callback): array
{
    $queries = [];
    DB::listen(static function (QueryExecuted $query) use (&$queries): void {
        if (str_contains($query->sql, 'structured_content_items') && str_starts_with(strtolower($query->sql), 'select *')) {
            $queries[] = $query->sql;
        }
    });

    $callback();

    return $queries;
}

it('reads only the requested number of records from the database', function (): void {
    foreach (range(1, 6) as $order) {
        StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create(['title' => 'Quote ' . $order, 'sort_order' => $order]);
    }

    $items = [];
    $queries = structuredContentItemSelects(static function () use (&$items): void {
        $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial, null, 3);
    });

    expect(array_map(static fn (object $item): string => $item->title, $items))->toBe(['Quote 1', 'Quote 2', 'Quote 3'])
        ->and($queries)->toHaveCount(1)
        ->and($queries[0])->toContain('limit 3')
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial))->toHaveCount(6)
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial, null, 0))->toBe([]);
});

it('reads only as many records as the largest limited section shows', function (): void {
    foreach (range(1, 5) as $order) {
        StructuredContentItem::factory()->published()->type(StructuredContentType::Faq)->create(['sort_order' => $order]);
        StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create(['sort_order' => $order]);
    }

    $sections = [];
    $queries = structuredContentItemSelects(static function () use (&$sections): void {
        $sections = BuildStructuredContentSectionsAction::run([
            'top-faqs' => ['type' => StructuredContentType::Faq, 'limit' => 2],
            'more-faqs' => ['type' => StructuredContentType::Faq, 'limit' => 4],
            'services' => StructuredContentType::Service,
        ]);
    });

    expect(array_map(static fn (object $section): int => count($section->items), $sections))->toBe([2, 4, 5])
        ->and(collect($queries)->contains(static fn (string $sql): bool => str_contains($sql, 'limit 4')))->toBeTrue()
        ->and(collect($queries)->contains(static fn (string $sql): bool => str_contains($sql, 'limit 2')))->toBeFalse();
});
