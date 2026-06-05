<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
        ]))->toBeTrue()
        ->and(Schema::hasIndex('structured_content_items', 'structured_content_type_site_slug_unique'))->toBeTrue();
});

it('deduplicates legacy scoped slugs before adding the unique index', function (): void {
    Schema::dropIfExists('structured_content_items');

    Schema::create('structured_content_items', function (Blueprint $table): void {
        $table->id();
        $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
        $table->string('type')->index();
        $table->string('slug')->nullable()->index();
        $table->timestamps();
    });

    $siteId = DB::table('sites')->insertGetId([]);

    DB::table('structured_content_items')->insert([
        [
            'site_id' => $siteId,
            'type' => StructuredContentType::Service->value,
            'slug' => 'strategy',
        ],
        [
            'site_id' => $siteId,
            'type' => StructuredContentType::Service->value,
            'slug' => 'strategy',
        ],
        [
            'site_id' => $siteId,
            'type' => StructuredContentType::Service->value,
            'slug' => 'strategy-2',
        ],
        [
            'site_id' => null,
            'type' => StructuredContentType::Service->value,
            'slug' => 'global-strategy',
        ],
        [
            'site_id' => null,
            'type' => StructuredContentType::Service->value,
            'slug' => 'global-strategy',
        ],
    ]);

    $migration = require dirname(__DIR__, 3) . '/database/migrations/2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table.php';

    throw_unless(is_object($migration) && method_exists($migration, 'up'), RuntimeException::class, 'Expected migration object with an up method.');

    call_user_func([$migration, 'up']);

    $siteScopedSlugs = DB::table('structured_content_items')
        ->where('site_id', $siteId)
        ->orderBy('id')
        ->pluck('slug')
        ->all();
    $globalSlugs = DB::table('structured_content_items')
        ->whereNull('site_id')
        ->orderBy('id')
        ->pluck('slug')
        ->all();

    expect($siteScopedSlugs)->toBe(['strategy', 'strategy-3', 'strategy-2'])
        ->and($globalSlugs)->toBe(['global-strategy', 'global-strategy-2'])
        ->and(Schema::hasIndex('structured_content_items', 'structured_content_type_site_slug_unique'))->toBeTrue();
});
