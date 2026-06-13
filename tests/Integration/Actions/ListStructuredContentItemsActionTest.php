<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\ListStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

function createStructuredContentLibrarySite(int $suffix): int
{
    return (int) DB::table('sites')->insertGetId([]);
}

it('lists published global and site-specific items by type in display order', function (): void {
    $siteId = createStructuredContentLibrarySite(1);
    $otherSiteId = createStructuredContentLibrarySite(2);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'title' => 'Global service',
        'sort_order' => 20,
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'site_id' => $siteId,
        'title' => 'Site service',
        'sort_order' => 10,
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'site_id' => $otherSiteId,
        'title' => 'Other site service',
        'sort_order' => 5,
    ]);

    StructuredContentItem::factory()->type(StructuredContentType::Service)->create([
        'site_id' => $siteId,
        'status' => StructuredContentStatus::Draft,
        'title' => 'Draft service',
    ]);

    StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create([
        'site_id' => $siteId,
        'title' => 'Wrong type',
    ]);

    $items = ListStructuredContentItemsAction::run(StructuredContentType::Service, $siteId);

    expect($items->pluck('title')->all())->toBe([
        'Site service',
        'Global service',
    ]);
});
