<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('builds public-safe structured content items for theme and section adapters', function (): void {
    $siteId = (int) DB::table('sites')->insertGetId([]);
    $otherSiteId = (int) DB::table('sites')->insertGetId([]);

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
            'quote' => 'Useful quote',
            'answer' => 'Portable answer.',
            'company' => 'Example Ltd',
            'image_alt' => 'Office interior',
        ])
        ->and($items[0]->payload)->not->toHaveKey('url')
        ->and($items[0]->payload)->not->toHaveKey('email');
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
        'email' => 'editor@example.com',
    ]);
});
