<?php

declare(strict_types=1);

use Capell\Core\Support\Cache\CapellCacheManager;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\Fixtures\StructuredContentGlobalTestUser;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryWithFrontendTestCase;
use Livewire\Livewire;

uses(StructuredContentLibraryWithFrontendTestCase::class);

it('clears public page caches that depend on structured content after a drag reorder', function (): void {
    $this->actingAs(new StructuredContentGlobalTestUser);
    $make = static fn (string $title, int $order): StructuredContentItem => StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Service, 'site_id' => null, 'title' => $title, 'sort_order' => $order, 'status' => 'published', 'published_at' => null,
    ]);
    $first = $make('First', 1);
    $second = $make('Second', 2);
    $cache = resolve(CapellCacheManager::class);
    $cache->rememberCache('structured-content-library-services-page', static fn (): string => 'First, Second');
    $cache->rememberCache('unrelated-page', static fn (): string => 'Unrelated');

    Livewire::test(ListStructuredContentItems::class)->call('reorderTable', [$second->getKey(), $first->getKey()]);

    expect($cache->getFromCache('structured-content-library-services-page'))->toBeNull()
        ->and($cache->getFromCache('unrelated-page'))->toBe('Unrelated');
});

it('keeps dependent public page caches when a reorder is invalid or outside the editor\'s sites', function (bool $global): void {
    $site = $this->createStructuredContentSite('Refused reorder');
    $first = StructuredContentItem::factory()->create(['type' => StructuredContentType::Service, 'site_id' => $site, 'sort_order' => 1]);
    $second = StructuredContentItem::factory()->create(['type' => StructuredContentType::Service, 'site_id' => $site, 'sort_order' => 2]);
    $cache = resolve(CapellCacheManager::class);
    $cache->rememberCache('structured-content-library-services-page', static fn (): string => 'First, Second');
    $this->actingAs(new StructuredContentGlobalTestUser(global: $global, assignedSiteIds: [$site]));

    Livewire::test(ListStructuredContentItems::class)->call('reorderTable', $global
        ? [$second->getKey(), $second->getKey()]
        : [$second->getKey(), $first->getKey()]);

    expect($cache->getFromCache('structured-content-library-services-page'))->toBe('First, Second')
        ->and($first->refresh()->sort_order)->toBe(1);
})->with(['invalid order' => true, 'unauthorised editor' => false]);
