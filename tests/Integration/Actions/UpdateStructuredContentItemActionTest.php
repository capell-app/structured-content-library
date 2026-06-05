<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('updates a structured content item through typed data', function (): void {
    $item = StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Service,
        'status' => StructuredContentStatus::Draft,
        'title' => 'Old title',
        'sort_order' => 5,
    ]);

    $updated = UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Faq,
        title: '  Updated title  ',
        status: StructuredContentStatus::Published,
        slug: 'Updated Title!',
        summary: '  <p>Updated <em>portable</em> summary.</p>  ',
        content: '<p>Portable updated content.</p>',
        payload: new StructuredContentPayloadData(question: 'Can this be edited?', answer: 'Yes.'),
        publishedAt: now()->subMinute(),
        sortOrder: 2,
    ));

    expect($updated->type)->toBe(StructuredContentType::Faq)
        ->and($updated->status)->toBe(StructuredContentStatus::Published)
        ->and($updated->title)->toBe('Updated title')
        ->and($updated->slug)->toBe('updated-title')
        ->and($updated->summary)->toBe('<p>Updated <em>portable</em> summary.</p>')
        ->and($updated->content)->toBe('<p>Portable updated content.</p>')
        ->and($updated->payload)->toBeInstanceOf(StructuredContentPayloadData::class)
        ->and($updated->payload?->question)->toBe('Can this be edited?')
        ->and($updated->sort_order)->toBe(2);
});

it('rejects designed markup during updates', function (): void {
    $item = StructuredContentItem::factory()->create();

    expect(fn (): mixed => UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Designed service',
        content: '<section class="grid gap-4"><p>Designed markup.</p></section>',
    )))->toThrow(ValidationException::class);
});

it('rejects unsafe summary markup during updates', function (string $summary): void {
    $item = StructuredContentItem::factory()->create([
        'summary' => 'Safe summary.',
    ]);

    try {
        UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
            type: StructuredContentType::Service,
            title: 'Unsafe summary',
            summary: $summary,
            content: '<p>Portable content.</p>',
        ));
    } catch (ValidationException $validationException) {
        expect($validationException->errors())->toHaveKey('summary')
            ->and($item->refresh()->summary)->toBe('Safe summary.');

        return;
    }

    $this->fail('Unsafe summary markup was stored.');
})->with([
    'script tag' => ['<script>alert("xss")</script>'],
    'inline event handler' => ['<p onclick="alert(1)">Unsafe summary.</p>'],
]);

it('uniques slugs when an update collides with another item in the same type and site scope', function (): void {
    StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Service,
        'site_id' => null,
        'title' => 'Strategy',
        'slug' => 'strategy',
    ]);

    $item = StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Service,
        'site_id' => null,
        'title' => 'Planning',
        'slug' => 'planning',
    ]);

    $updated = UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Strategy',
    ));

    expect($updated->slug)->toBe('strategy-2');
});

it('keeps an existing slug when updating the owning item', function (): void {
    $item = StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Service,
        'site_id' => null,
        'title' => 'Strategy',
        'slug' => 'strategy',
    ]);

    $updated = UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Strategy',
    ));

    expect($updated->slug)->toBe('strategy');
});

it('defaults published_at when transitioning to published without an explicit date', function (): void {
    Carbon::setTestNow(Carbon::parse('2026-06-05 10:30:00'));

    try {
        $item = StructuredContentItem::factory()->create([
            'status' => StructuredContentStatus::Draft,
            'published_at' => null,
        ]);

        $updated = UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
            type: StructuredContentType::Service,
            title: 'Published service',
            status: StructuredContentStatus::Published,
            content: '<p>Portable content.</p>',
        ));

        expect($updated->published_at?->toDateTimeString())->toBe('2026-06-05 10:30:00');
    } finally {
        Carbon::setTestNow();
    }
});

it('preserves existing published_at when updating a published item without an explicit date', function (): void {
    $publishedAt = Carbon::parse('2026-05-20 09:15:00');
    $item = StructuredContentItem::factory()->published()->create([
        'published_at' => $publishedAt,
    ]);

    $updated = UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Updated published service',
        status: StructuredContentStatus::Published,
        content: '<p>Portable content.</p>',
    ));

    expect($updated->published_at?->toDateTimeString())->toBe('2026-05-20 09:15:00');
});

it('does not change published_at for non-publish transitions without an explicit date', function (): void {
    $publishedAt = Carbon::parse('2026-05-20 09:15:00');
    $item = StructuredContentItem::factory()->published()->create([
        'published_at' => $publishedAt,
    ]);

    $updated = UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Archived service',
        status: StructuredContentStatus::Archived,
        content: '<p>Portable content.</p>',
    ));

    expect($updated->published_at?->toDateTimeString())->toBe('2026-05-20 09:15:00');
});
