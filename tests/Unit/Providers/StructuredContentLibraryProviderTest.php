<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Manifest\StructuredContentItemResourceContribution;
use Capell\StructuredContentLibrary\Manifest\StructuredContentModelsContribution;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\File;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('declares provider classes and package metadata', function (): void {
    $manifest = json_decode(
        (string) File::get(__DIR__ . '/../../../capell.json'),
        associative: true,
        flags: JSON_THROW_ON_ERROR,
    );

    expect(StructuredContentLibraryServiceProvider::class)->toExtend(AbstractPackageServiceProvider::class)
        ->and(StructuredContentLibraryServiceProvider::$name)->toBe('capell-structured-content-library')
        ->and(StructuredContentLibraryServiceProvider::$packageName)->toBe('capell-app/structured-content-library')
        ->and($manifest['name'])->toBe('capell-app/structured-content-library')
        ->and($manifest['providers']['runtime'])->toBe([StructuredContentLibraryServiceProvider::class])
        ->and($manifest['providers']['admin'])->toBe([StructuredContentLibraryServiceProvider::class])
        ->and($manifest['contributes'][0]['type'])->toBe('admin-resource')
        ->and($manifest['contributes'][0]['class'])->toBe(StructuredContentItemResourceContribution::class)
        ->and($manifest['contributes'][0]['resourceClass'])->toBe(StructuredContentItemResource::class)
        ->and($manifest['contributes'])->toContain([
            'type' => 'model',
            'class' => StructuredContentModelsContribution::class,
        ])
        ->and($manifest['contributionTraceability']['deferredContributions'])->not->toContain('admin-resource')
        ->and($manifest['contributionTraceability']['deferredContributions'])->toBe([
            'content-section-adapter',
            'theme-adapter',
        ])
        ->and($manifest['actions']['buildStructuredContentSections'])
        ->toBe(BuildStructuredContentSectionsAction::class)
        ->and($manifest['capabilities'])->toContain(
            'structured-content-library',
            'structured-content-public-adapter',
            'structured-content-import',
        )
        ->and($manifest['capabilities'])->not->toContain(
            'structured-content-section-adapter',
            'structured-content-theme-adapter',
        )
        ->and($manifest['description'])->toBe('Structured Content Library stores portable reusable records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos.');
});

it('declares all first-class reusable content concepts', function (): void {
    expect(array_map(
        static fn (StructuredContentType $type): string => $type->value,
        StructuredContentType::cases(),
    ))->toBe([
        'case_study',
        'testimonial',
        'team_member',
        'service',
        'faq',
        'resource',
        'partner',
        'location',
        'logo',
    ]);
});

it('registers models and protected tables when installed', function (): void {
    CapellCore::forcePackageInstalled(StructuredContentLibraryServiceProvider::$packageName);

    (new StructuredContentLibraryServiceProvider(app()))->packageRegistered();

    expect(CapellCore::getModels())->toContain(StructuredContentItem::class)
        ->and(CapellCore::getProtectedTables())->toContain('structured_content_items');
});
