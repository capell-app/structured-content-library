<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\Fixtures\Filament\TestCreateStructuredContentItemPage;
use Capell\StructuredContentLibrary\Tests\Fixtures\Filament\TestEditStructuredContentItemPage;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('delegates the create page save path to the create Action', function (): void {
    $page = new TestCreateStructuredContentItemPage;

    $record = $page->createRecordForTest([
        'type' => StructuredContentType::Testimonial,
        'status' => StructuredContentStatus::Published,
        'title' => '  Customer proof  ',
        'summary' => '<p>Short portable summary.</p>',
        'content' => '<p>Portable content.</p>',
        'payload' => [
            'quote' => 'The package stores reusable proof.',
            'attribution' => 'Ada Example',
        ],
        'sort_order' => 7,
    ]);

    expect($record)->toBeInstanceOf(StructuredContentItem::class);
    expect($record->exists)->toBeTrue();
    expect($record->title)->toBe('Customer proof');
    expect($record->slug)->toBe('customer-proof');
    expect($record->payload?->quote)->toBe('The package stores reusable proof.');
    expect($record->published_at)->not->toBeNull();
    expect($record->sort_order)->toBe(7);
});

it('delegates the edit page save path to the update Action', function (): void {
    $record = StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Faq,
        'status' => StructuredContentStatus::Draft,
        'title' => 'Old service',
        'slug' => 'old-service',
    ]);
    $page = new TestEditStructuredContentItemPage;

    $updatedRecord = $page->updateRecordForTest($record, [
        'type' => StructuredContentType::Faq,
        'status' => StructuredContentStatus::Published,
        'title' => '  Updated FAQ  ',
        'content' => '<p>Portable answer content.</p>',
        'payload' => [
            'question' => 'Can admins save through Filament?',
            'answer' => 'Yes.',
        ],
    ]);

    expect($updatedRecord)->toBeInstanceOf(StructuredContentItem::class);
    expect($updatedRecord->type)->toBe(StructuredContentType::Faq);
    expect($updatedRecord->status)->toBe(StructuredContentStatus::Published);
    expect($updatedRecord->title)->toBe('Updated FAQ');
    expect($updatedRecord->slug)->toBe('updated-faq');
    expect($updatedRecord->payload?->question)->toBe('Can admins save through Filament?');
    expect($updatedRecord->published_at)->not->toBeNull();
});
