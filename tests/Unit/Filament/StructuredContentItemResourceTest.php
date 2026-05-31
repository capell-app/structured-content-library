<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('exposes a structured content Filament resource', function (): void {
    CapellCore::forcePackageInstalled(StructuredContentLibraryServiceProvider::$packageName);

    $pages = StructuredContentItemResource::getPages();

    expect(StructuredContentItemResource::getModel())->toBe(StructuredContentItem::class)
        ->and(StructuredContentItemResource::shouldRegisterNavigation())->toBeTrue()
        ->and(StructuredContentItemResource::getNavigationLabel())->toBe('Structured content')
        ->and(StructuredContentItemResource::getModelLabel())->toBe('structured content item')
        ->and(StructuredContentItemResource::getPluralModelLabel())->toBe('structured content items')
        ->and(array_keys($pages))->toBe(['index', 'create', 'edit'])
        ->and($pages['index']->getPage())->toBe(ListStructuredContentItems::class)
        ->and($pages['create']->getPage())->toBe(CreateStructuredContentItem::class)
        ->and($pages['edit']->getPage())->toBe(EditStructuredContentItem::class);
});
