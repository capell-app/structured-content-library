<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\ListStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('lists published global and site-specific items by type in display order', function (): void {
    $siteId = $this->createStructuredContentSite('Structured content site 1');
    $otherSiteId = $this->createStructuredContentSite('Structured content site 2');

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
