<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
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
