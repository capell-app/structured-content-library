<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\Schema;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('casts enum and payload columns to structured types', function (): void {
    $item = StructuredContentItem::factory()->create([
        'type' => StructuredContentType::Partner,
        'status' => StructuredContentStatus::Published,
        'payload' => new StructuredContentPayloadData(
            company: 'Partner Co',
            url: 'https://example.com',
        ),
    ])->refresh();

    expect($item->type)->toBe(StructuredContentType::Partner)
        ->and($item->status)->toBe(StructuredContentStatus::Published)
        ->and($item->payload)->toBeInstanceOf(StructuredContentPayloadData::class)
        ->and($item->payload?->company)->toBe('Partner Co');
});

it('installs the structured content items table', function (): void {
    expect(Schema::hasTable('structured_content_items'))->toBeTrue()
        ->and(Schema::hasColumns('structured_content_items', [
            'site_id',
            'type',
            'status',
            'title',
            'slug',
            'summary',
            'content',
            'payload',
            'published_at',
            'sort_order',
        ]))->toBeTrue();
});
