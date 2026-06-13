<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
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

it('scopes payload fields to the selected structured content type', function (): void {
    $coveredPayloadFields = collect(StructuredContentType::cases())
        ->flatMap(static fn (StructuredContentType $type): array => StructuredContentItemResource::payloadFieldsForType($type))
        ->unique()
        ->sort()
        ->values()
        ->all();

    expect(StructuredContentItemResource::payloadFieldsForType(StructuredContentType::Faq))->toBe([
        'question',
        'answer',
    ])
        ->and(StructuredContentItemResource::payloadFieldsForType(StructuredContentType::Testimonial))->toBe([
            'quote',
            'attribution',
            'role',
            'company',
            'image_alt',
        ])
        ->and(StructuredContentItemResource::payloadFieldsForType(StructuredContentType::Location))->toBe([
            'email',
            'phone',
            'street_address',
            'locality',
            'region',
            'postal_code',
            'country_code',
            'url',
        ])
        ->and($coveredPayloadFields)->toBe([
            'answer',
            'attribution',
            'company',
            'country_code',
            'email',
            'eyebrow',
            'image_alt',
            'locality',
            'logo_alt',
            'phone',
            'postal_code',
            'question',
            'quote',
            'region',
            'resource_kind',
            'role',
            'street_address',
            'subtitle',
            'url',
        ]);
});
