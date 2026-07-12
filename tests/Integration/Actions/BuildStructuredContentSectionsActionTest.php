<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentSectionData;
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
    $servicesSection = $sections[0] ?? null;
    $testimonialsSection = $sections[1] ?? null;

    throw_unless($servicesSection instanceof StructuredContentSectionData, RuntimeException::class);
    throw_unless($testimonialsSection instanceof StructuredContentSectionData, RuntimeException::class);

    $serviceItem = $servicesSection->items[0] ?? null;
    $testimonialItem = $testimonialsSection->items[0] ?? null;

    throw_unless($serviceItem instanceof PublicStructuredContentItemData, RuntimeException::class);
    throw_unless($testimonialItem instanceof PublicStructuredContentItemData, RuntimeException::class);

    expect($sections)->toHaveCount(2)
        ->and($servicesSection->key)->toBe('services')
        ->and($servicesSection->label)->toBe('What we do')
        ->and($servicesSection->items)->toHaveCount(1)
        ->and($serviceItem->title)->toBe('Implementation')
        ->and($testimonialsSection->key)->toBe('testimonials')
        ->and($testimonialItem->payload['quote'] ?? null)->toBe('Reusable content shipped faster.')
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
    $initialSection = $initialSections[0] ?? null;
    $updatedSection = $updatedSections[0] ?? null;

    throw_unless($initialSection instanceof StructuredContentSectionData, RuntimeException::class);
    throw_unless($updatedSection instanceof StructuredContentSectionData, RuntimeException::class);

    $newItem = $updatedSection->items[1] ?? null;

    throw_unless($newItem instanceof PublicStructuredContentItemData, RuntimeException::class);

    expect($initialSection->items)->toHaveCount(1)
        ->and($updatedSection->items)->toHaveCount(2)
        ->and($newItem->title)->toBe('New service');
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
