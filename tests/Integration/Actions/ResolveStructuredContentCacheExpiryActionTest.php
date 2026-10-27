<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\ResolveStructuredContentCacheExpiryAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Carbon\CarbonImmutable;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('expires only for a scheduled published item in the requested site and type scope', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-01 10:00:00', 'UTC'));
    try {
        config()->set('capell-structured-content-library.public_cache_ttl_seconds', 300);
        $siteId = $this->createStructuredContentSite('Cache scope');
        $otherSiteId = $this->createStructuredContentSite('Other cache scope');
        StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
            'site_id' => $siteId, 'published_at' => now()->addSeconds(30),
        ]);
        StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
            'site_id' => $otherSiteId, 'published_at' => now()->addSeconds(10),
        ]);
        StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
            'published_at' => now()->addSeconds(5),
        ]);
        StructuredContentItem::factory()->type(StructuredContentType::Service)->create([
            'status' => StructuredContentStatus::Draft, 'published_at' => now()->addSecond(),
        ]);

        expect(ResolveStructuredContentCacheExpiryAction::run([StructuredContentType::Service], $siteId)->getTimestamp())
            ->toBe(now()->addSeconds(30)->getTimestamp())
            ->and(ResolveStructuredContentCacheExpiryAction::run([StructuredContentType::Service], null)->getTimestamp())
            ->toBe(now()->addSeconds(300)->getTimestamp());
    } finally {
        $this->travelBack();
    }
});

it('does not extend the configured ttl to a later publication date', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-01 10:00:00', 'UTC'));
    try {
        config()->set('capell-structured-content-library.public_cache_ttl_seconds', 60);
        StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
            'published_at' => now()->addDay(),
        ]);
        expect(ResolveStructuredContentCacheExpiryAction::run([StructuredContentType::Service], null)->getTimestamp())
            ->toBe(now()->addMinute()->getTimestamp());
    } finally {
        $this->travelBack();
    }
});

it('does not cache a stale payload when publication occurs while the payload is being built', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-01 10:00:00', 'UTC'));
    try {
        $publication = now()->addSecond();
        StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
            'published_at' => $publication,
        ]);
        StructuredContentCache::rememberPublicItemsByTypes([StructuredContentType::Service], null, function () use ($publication): array {
            $this->travelTo($publication);

            return [];
        });
        expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service))->toHaveCount(1);
    } finally {
        $this->travelBack();
    }
});
