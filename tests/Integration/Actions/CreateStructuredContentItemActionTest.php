<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('creates a structured content item from typed data', function (): void {
    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Testimonial,
        title: '  Customer story  ',
        status: StructuredContentStatus::Published,
        slug: 'Customer Story!',
        summary: '  A concise portable summary.  ',
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
        ->and($item->summary)->toBe('A concise portable summary.')
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

it('rejects unsafe summary markup before it can be stored', function (): void {
    try {
        CreateStructuredContentItemAction::run(new StructuredContentItemData(
            type: StructuredContentType::Service,
            title: 'Unsafe summary',
            summary: '<script>alert("xss")</script>',
            content: '<p>Portable content.</p>',
        ));
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('summary');

        return;
    }

    $this->fail('Unsafe summary markup was stored.');
});

it('defaults published_at when publishing without an explicit date', function (): void {
    $item = CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Published service',
        status: StructuredContentStatus::Published,
        content: '<p>Portable content.</p>',
    ));

    expect($item->published_at)->not->toBeNull();
});
