<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\DescribeStructuredContentAvailabilityAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Carbon\CarbonImmutable;
use Dom\HTMLDocument;
use Filament\Support\Facades\FilamentTimezone;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('builds public-safe structured content items for theme and section adapters', function (): void {
    $siteId = $this->createStructuredContentSite('Public content');
    $otherSiteId = $this->createStructuredContentSite('Other public content');

    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'title' => 'Global testimonial',
        'summary' => 'Portable summary',
        'content' => '<p>Portable content.</p>',
        'payload' => [
            'quote' => 'A useful package-owned testimonial.',
            'attribution' => 'Ada Example',
        ],
        'sort_order' => 20,
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'site_id' => $siteId,
        'title' => 'Site testimonial',
        'payload' => [
            'quote' => 'A site-specific testimonial.',
            'company' => 'Example Ltd',
        ],
        'sort_order' => 10,
    ]);

    StructuredContentItem::factory()->type(StructuredContentType::Testimonial)->create([
        'site_id' => $siteId,
        'title' => 'Draft testimonial',
        'status' => StructuredContentStatus::Draft,
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'site_id' => $siteId,
        'title' => 'Wrong type',
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'site_id' => $otherSiteId,
        'title' => 'Wrong site',
    ]);

    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial, $siteId);
    $payload = json_encode(array_map(
        static fn (mixed $item): array => $item->toArray(),
        $items,
    ), JSON_THROW_ON_ERROR);

    expect($items)->toHaveCount(2)
        ->and($items[0]->title)->toBe('Site testimonial')
        ->and($items[0]->payload['quote'])->toBe('A site-specific testimonial.')
        ->and($items[1]->title)->toBe('Global testimonial')
        ->and($payload)->toContain('Site testimonial')
        ->and($payload)->not->toContain('Draft testimonial')
        ->and($payload)->not->toContain('Wrong type')
        ->and($payload)->not->toContain('Wrong site')
        ->and($payload)->not->toContain('id')
        ->and($payload)->not->toContain('site_id')
        ->and($payload)->not->toContain('capell-app/structured-content-library')
        ->and($payload)->not->toContain('Filament')
        ->and($payload)->not->toContain('signed');
});

it('limits public structured content adapter output', function (): void {
    StructuredContentItem::factory()->published()->count(3)->type(StructuredContentType::Service)->sequence(
        ['title' => 'First service', 'sort_order' => 1],
        ['title' => 'Second service', 'sort_order' => 2],
        ['title' => 'Third service', 'sort_order' => 3],
    )->create();

    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service, limit: 2);

    expect($items)->toHaveCount(2)
        ->and($items[0]->title)->toBe('First service')
        ->and($items[1]->title)->toBe('Second service');
});

it('sanitizes public payload output for theme adapters', function (): void {
    StructuredContentItem::factory()->published()->type(StructuredContentType::Resource)->create([
        'title' => 'Unsafe payload',
        'payload' => [
            'quote' => '<script>alert("xss")</script><strong>Useful quote</strong>',
            'answer' => '<p onclick="alert(1)">Portable answer.</p>',
            'url' => 'javascript:alert(1)',
            'email' => 'not-an-email',
            'company' => '<img src=x onerror=alert(1)>Example Ltd',
            'image_alt' => '<span>Office interior</span>',
        ],
    ]);

    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Resource);

    expect($items)->toHaveCount(1)
        ->and($items[0]->payload)->toMatchArray([
            'image_alt' => 'Office interior',
        ])
        ->and($items[0]->payload)->not->toHaveKey('url')
        ->and($items[0]->payload)->not->toHaveKey('email')
        ->and($items[0]->payload)->not->toHaveKeys(['quote', 'answer', 'company']);
});

it('prevents raw and encoded payload html from rendering as executable public markup', function (): void {
    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'title' => 'Unsafe rendered payload',
        'payload' => [
            'quote' => '&lt;script&gt;alert("encoded")&lt;/script&gt;<strong>Safe quote</strong>',
            'company' => '&lt;img src=x onerror=alert(1)&gt;Example Ltd',
            'url' => 'javascript:alert(1)',
        ],
    ]);

    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial);
    $publicPayload = $items[0]->payload;
    $serializedPayload = json_encode($publicPayload, JSON_THROW_ON_ERROR);
    $renderedPayload = Blade::render(
        '{!! $payload["quote"] !!}{!! $payload["company"] !!}<a href="{!! $payload["url"] ?? "#" !!}">Link</a>',
        ['payload' => $publicPayload],
    );

    expect($publicPayload)->toMatchArray([
        'quote' => 'Safe quote',
        'company' => 'Example Ltd',
    ])
        ->and($publicPayload)->not->toHaveKey('url')
        ->and($serializedPayload)->not->toContain('<script')
        ->and($serializedPayload)->not->toContain('&lt;script')
        ->and($serializedPayload)->not->toContain('onerror')
        ->and($renderedPayload)->toContain('Safe quote')
        ->and($renderedPayload)->toContain('Example Ltd')
        ->and($renderedPayload)->not->toContain('<script')
        ->and($renderedPayload)->not->toContain('&lt;script')
        ->and($renderedPayload)->not->toContain('<img')
        ->and($renderedPayload)->not->toContain('onerror')
        ->and($renderedPayload)->not->toContain('javascript:');
});

it('keeps public http and relative payload urls', function (): void {
    StructuredContentItem::factory()->published()->type(StructuredContentType::Resource)->create([
        'title' => 'Safe payload URL',
        'payload' => [
            'url' => 'https://example.com/resource',
            'email' => 'editor@example.com',
        ],
    ]);

    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Resource);

    expect($items[0]->payload)->toMatchArray([
        'url' => 'https://example.com/resource',
    ])
        ->and($items[0]->payload)->not->toHaveKey('email');
});

it('removes adjacent unsafe links from both public rich text fields', function (string $field): void {
    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        $field => '<a title="safe"href="javascript:alert(1)">test</a>',
    ]);
    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service)[0];
    $dom = HTMLDocument::createFromString($public->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
    expect($dom->getElementsByTagName('a')->item(0)?->hasAttribute('href'))->toBeFalse()
        ->and($dom->getElementsByTagName('a')->item(0)?->textContent)->toBe('test');
})->with(['content', 'summary']);

it('sanitises legacy cached rich text before returning public items', function (string $field): void {
    $store = Mockery::mock(Repository::class);
    $store->shouldReceive('get')->once()->andReturn(['service' => [[
        'type' => 'service', 'title' => 'Cached service',
        $field => '<a title="safe"href="javascript:alert(1)">test</a>',
    ]]]);
    Cache::shouldReceive('get')->andReturn(1);
    Cache::shouldReceive('supportsTags')->andReturnFalse();
    Cache::shouldReceive('store')->andReturn($store);

    $items = StructuredContentCache::rememberPublicItemsByTypes([StructuredContentType::Service], null, function (): never {
        throw new RuntimeException('A cache hit must not query the database.');
    });
    $dom = HTMLDocument::createFromString($items['service'][0]->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
    expect($dom->querySelector('a')?->hasAttribute('href'))->toBeFalse()
        ->and($dom->querySelector('a')?->textContent)->toBe('test');
})->with(['content', 'summary']);

it('changes availability at the publication instant even when the adapter was cached before it', function (): void {
    $this->travelTo(CarbonImmutable::parse('2026-07-01 10:00:00', 'UTC'));
    $publication = now()->addSeconds(30);
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'published_at' => $publication,
    ]);

    foreach ([StructuredContentStatus::Draft, StructuredContentStatus::Archived] as $status) {
        StructuredContentItem::factory()->type(StructuredContentType::Service)->create([
            'status' => $status,
            'published_at' => now()->subDay(),
        ]);
    }

    FilamentTimezone::set('Europe/London');
    try {
        expect(DescribeStructuredContentAvailabilityAction::run($item))
            ->toBe(__('capell-structured-content-library::admin.scheduled', [
                'time' => '1 Jul 2026 11:00:30 BST',
            ]))
            ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service))->toBe([]);

        $this->travelTo($publication->copy()->subSecond());
        expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service))->toBe([]);

        $this->travelTo($publication);
        $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service);
        expect($public)->toHaveCount(1)
            ->and($public[0]->title)->toBe($item->title)
            ->and(DescribeStructuredContentAvailabilityAction::run($item))
            ->toBe(__('capell-structured-content-library::admin.available'));
    } finally {
        FilamentTimezone::set(null);
        $this->travelBack();
    }
});

it('keeps draft and archived availability independent of a past date', function (StructuredContentStatus $status): void {
    $item = StructuredContentItem::factory()->create(['status' => $status, 'published_at' => now()->subDay()]);
    expect(DescribeStructuredContentAvailabilityAction::run($item))->toBe($status->getLabel());
})->with([StructuredContentStatus::Draft, StructuredContentStatus::Archived]);
