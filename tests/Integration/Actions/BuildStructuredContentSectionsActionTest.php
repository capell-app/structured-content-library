<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('builds section-ready structured content groups for themes and content sections', function (): void {
    $siteId = (int) DB::table('sites')->insertGetId([]);
    $otherSiteId = (int) DB::table('sites')->insertGetId([]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'site_id' => $siteId,
        'title' => 'Implementation',
        'sort_order' => 1,
    ]);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'site_id' => $siteId,
        'title' => 'Training',
        'sort_order' => 2,
    ]);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'site_id' => $siteId,
        'title' => 'Ada Example',
        'payload' => ['quote' => 'Reusable content shipped faster.'],
        'sort_order' => 1,
    ]);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'site_id' => $otherSiteId,
        'title' => 'Wrong site',
    ]);
    StructuredContentItem::factory()->type(StructuredContentType::Faq)->create([
        'site_id' => $siteId,
        'status' => StructuredContentStatus::Draft,
        'title' => 'Draft FAQ',
    ]);

    $sections = BuildStructuredContentSectionsAction::run([
        'services' => [
            'type' => StructuredContentType::Service,
            'label' => 'What we do',
            'limit' => 1,
        ],
        'testimonials' => StructuredContentType::Testimonial,
        'faqs' => StructuredContentType::Faq,
    ], $siteId);
    $payload = json_encode(array_map(
        static fn (mixed $section): array => $section->toArray(),
        $sections,
    ), JSON_THROW_ON_ERROR);

    expect($sections)->toHaveCount(2)
        ->and($sections[0]->key)->toBe('services')
        ->and($sections[0]->label)->toBe('What we do')
        ->and($sections[0]->items)->toHaveCount(1)
        ->and($sections[0]->items[0]->title)->toBe('Implementation')
        ->and($sections[1]->key)->toBe('testimonials')
        ->and($sections[1]->items[0]->payload['quote'])->toBe('Reusable content shipped faster.')
        ->and($payload)->not->toContain('Wrong site')
        ->and($payload)->not->toContain('Draft FAQ')
        ->and($payload)->not->toContain('site_id')
        ->and($payload)->not->toContain('capell-app/structured-content-library')
        ->and($payload)->not->toContain('Filament')
        ->and($payload)->not->toContain('signed');
});

it('batches section public-content reads and reuses cached output', function (): void {
    StructuredContentCache::flush();

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'title' => 'Implementation',
        'sort_order' => 1,
    ]);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'title' => 'Ada Example',
        'sort_order' => 1,
    ]);
    StructuredContentItem::factory()->published()->type(StructuredContentType::Faq)->create([
        'title' => 'Common question',
        'sort_order' => 1,
    ]);

    $sections = [
        'services' => StructuredContentType::Service,
        'testimonials' => StructuredContentType::Testimonial,
        'faqs' => StructuredContentType::Faq,
    ];

    DB::flushQueryLog();
    DB::enableQueryLog();

    $firstResult = BuildStructuredContentSectionsAction::run($sections);
    $firstStructuredContentQueries = structured_content_library_select_query_count();

    DB::flushQueryLog();

    $secondResult = BuildStructuredContentSectionsAction::run($sections);
    $secondStructuredContentQueries = structured_content_library_select_query_count();

    DB::disableQueryLog();

    expect($firstResult)->toHaveCount(3)
        ->and($secondResult)->toHaveCount(3)
        ->and($firstStructuredContentQueries)->toBeLessThanOrEqual(1)
        ->and($secondStructuredContentQueries)->toBe(0);
});

it('invalidates cached public content when structured content changes', function (): void {
    StructuredContentCache::flush();

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'title' => 'Initial service',
        'sort_order' => 1,
    ]);

    $initialSections = BuildStructuredContentSectionsAction::run([
        'services' => StructuredContentType::Service,
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'title' => 'New service',
        'sort_order' => 2,
    ]);

    $updatedSections = BuildStructuredContentSectionsAction::run([
        'services' => StructuredContentType::Service,
    ]);

    expect($initialSections[0]->items)->toHaveCount(1)
        ->and($updatedSections[0]->items)->toHaveCount(2)
        ->and($updatedSections[0]->items[1]->title)->toBe('New service');
});

function structured_content_library_select_query_count(): int
{
    return collect(DB::getQueryLog())
        ->filter(static function (array $query): bool {
            $sql = (string) ($query['query'] ?? '');

            return str_starts_with(strtolower($sql), 'select')
                && str_contains($sql, 'structured_content_items');
        })
        ->count();
}
