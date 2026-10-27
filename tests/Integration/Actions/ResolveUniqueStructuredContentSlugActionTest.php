<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\ResolveUniqueStructuredContentSlugAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('de-duplicates a slug for a global (null site_id) item against an existing global item', function (): void {
    StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Testimonial,
        'site_id' => null,
        'title' => 'Customer story',
        'slug' => 'customer-story',
    ]);

    $resolvedSlug = ResolveUniqueStructuredContentSlugAction::run(
        type: StructuredContentType::Testimonial,
        siteId: null,
        slugSource: 'Customer Story',
    );

    expect($resolvedSlug)->toBe('customer-story-2');
});

it('normalises a supplied content key rather than rejecting its input format', function (string $input, string $expected): void {
    expect(ResolveUniqueStructuredContentSlugAction::run(
        type: StructuredContentType::Testimonial,
        siteId: null,
        slugSource: $input,
    ))->toBe($expected);
})->with([
    'spaces and capitals' => ['Customer Story', 'customer-story'],
    'underscores' => ['Customer_Story', 'customer-story'],
    'punctuation' => ['Customer Story!', 'customer-story'],
]);
