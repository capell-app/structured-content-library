<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\Fixtures\StructuredContentGlobalTestUser;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('creates a structured content item from typed data', function (): void {
    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Testimonial,
        title: '  Customer story  ',
        status: StructuredContentStatus::Published,
        slug: 'Customer Story!',
        summary: '  <p>A <strong>concise</strong> portable summary.</p>  ',
        content: ' <p>Portable <strong>semantic</strong> content.</p> ',
        payload: new StructuredContentPayloadData(
            quote: 'Capell made our editing workflow simpler.',
            attribution: 'Jane Smith',
            company: 'Example Ltd',
        ),
        publishedAt: now()->subMinute(),
        sortOrder: 12,
    ));

    expect($item)->toBeInstanceOf(StructuredContentItem::class)
        ->and($item->type)->toBe(StructuredContentType::Testimonial)
        ->and($item->status)->toBe(StructuredContentStatus::Published)
        ->and($item->title)->toBe('Customer story')
        ->and($item->slug)->toBe('customer-story')
        ->and($item->summary)->toBe('<p>A <strong>concise</strong> portable summary.</p>')
        ->and($item->content)->toBe('<p>Portable <strong>semantic</strong> content.</p>')
        ->and($item->payload)->toBeInstanceOf(StructuredContentPayloadData::class)
        ->and($item->payload?->company)->toBe('Example Ltd')
        ->and($item->sort_order)->toBe(12);
});

it('rejects designed markup before it can be stored', function (): void {
    expect(fn (): mixed => CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Designed service',
        content: '<div class="grid gap-6"><p>Theme-shaped markup.</p></div>',
    )))->toThrow(ValidationException::class);
});

it('rejects unsafe summary markup before it can be stored', function (string $summary): void {
    try {
        CreateStructuredContentItemAction::run(new StructuredContentItemData(
            type: StructuredContentType::Service,
            title: 'Unsafe summary',
            summary: $summary,
            content: '<p>Portable content.</p>',
        ));
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('summary');

        return;
    }

    $this->fail('Unsafe summary markup was stored.');
})->with([
    'script tag' => ['<script>alert("xss")</script>'],
    'inline event handler' => ['<p onclick="alert(1)">Unsafe summary.</p>'],
]);

it('defaults published_at when publishing without an explicit date', function (): void {
    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Published service',
        status: StructuredContentStatus::Published,
        content: '<p>Portable content.</p>',
    ));

    expect($item->published_at)->not->toBeNull();
});

it('uniques generated slugs within the same type and site scope', function (): void {
    StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Testimonial,
        'site_id' => null,
        'title' => 'Customer story',
        'slug' => 'customer-story',
    ]);

    $testimonial = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Testimonial,
        title: 'Customer Story',
    ));

    $service = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Customer Story',
    ));

    expect($testimonial->slug)->toBe('customer-story-2')
        ->and($service->slug)->toBe('customer-story');
});

it('allows the same slug in a different site scope', function (): void {
    $firstSiteId = $this->createStructuredContentSite('First slug scope');
    $secondSiteId = $this->createStructuredContentSite('Second slug scope');

    StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Testimonial,
        'site_id' => $firstSiteId,
        'title' => 'Customer story',
        'slug' => 'customer-story',
    ]);

    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Testimonial,
        title: 'Customer Story',
        siteId: $secondSiteId,
    ));

    expect($item->slug)->toBe('customer-story');
});

it('enforces global slug uniqueness in the database', function (): void {
    $firstItem = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Global uniqueness',
    ));

    expect(fn (): bool => DB::table('structured_content_items')->insert([
        'site_id' => null,
        'site_scope_key' => 'global',
        'type' => StructuredContentType::Service->value,
        'status' => StructuredContentStatus::Draft->value,
        'title' => 'Concurrent global uniqueness',
        'slug' => $firstItem->slug,
        'sort_order' => 0,
        'created_at' => now(),
        'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('rejects a scoped actor creating content for another site or global scope', function (): void {
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [10]));

    $data = new StructuredContentItemData(
        type: StructuredContentType::Service,
        status: StructuredContentStatus::Draft,
        title: 'Foreign service',
        content: '<p>Denied</p>',
        siteId: 20,
    );

    expect(fn (): StructuredContentItem => CreateStructuredContentItemAction::run($data))
        ->toThrow(AuthorizationException::class);

    expect(fn (): StructuredContentItem => CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Service,
        status: StructuredContentStatus::Draft,
        title: 'Global service',
        content: '<p>Denied</p>',
        siteId: null,
    )))->toThrow(AuthorizationException::class);
});

it('reserves slugs used by soft deleted records', function (): void {
    $deletedItem = StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Resource,
        'site_id' => null,
        'title' => 'Migration checklist',
        'slug' => 'migration-checklist',
    ]);

    $deletedItem->delete();

    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Resource,
        title: 'Migration checklist',
    ));

    expect($item->slug)->toBe('migration-checklist-2');
});
