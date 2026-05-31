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
