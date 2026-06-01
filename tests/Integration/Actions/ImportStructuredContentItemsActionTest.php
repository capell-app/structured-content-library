<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\ImportStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('imports portable structured content records and updates existing records by site type and slug', function (): void {
    $firstResult = ImportStructuredContentItemsAction::run([
        [
            'type' => StructuredContentType::Testimonial,
            'status' => StructuredContentStatus::Published,
            'title' => 'Ada Example',
            'slug' => 'ada-example',
            'summary' => 'Original summary',
            'content' => '<p>Portable testimonial content.</p>',
            'payload' => [
                'quote' => 'Capell made content reusable.',
                'attribution' => 'Ada Example',
            ],
        ],
    ]);

    $secondResult = ImportStructuredContentItemsAction::run([
        [
            'type' => StructuredContentType::Testimonial,
            'status' => StructuredContentStatus::Published,
            'title' => 'Ada Example Updated',
            'slug' => 'ada-example',
            'summary' => 'Updated summary',
            'content' => '<p>Updated portable content.</p>',
        ],
    ]);

    $item = StructuredContentItem::query()->firstOrFail();

    expect($firstResult->created)->toBe(1)
        ->and($firstResult->updated)->toBe(0)
        ->and($secondResult->created)->toBe(0)
        ->and($secondResult->updated)->toBe(1)
        ->and(StructuredContentItem::query()->count())->toBe(1)
        ->and($item->title)->toBe('Ada Example Updated')
        ->and($item->summary)->toBe('Updated summary');
});

it('can skip existing structured content records during imports', function (): void {
    StructuredContentItem::factory()->type(StructuredContentType::Service)->create([
        'slug' => 'strategy',
        'title' => 'Strategy',
    ]);

    $result = ImportStructuredContentItemsAction::run([
        [
            'type' => StructuredContentType::Service,
            'title' => 'Strategy Updated',
            'slug' => 'strategy',
        ],
    ], updateExisting: false);

    expect($result->created)->toBe(0)
        ->and($result->updated)->toBe(0)
        ->and($result->skipped)->toBe(1)
        ->and(StructuredContentItem::query()->first()?->title)->toBe('Strategy');
});
