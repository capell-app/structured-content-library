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
    $manifest = capell_json_file_array(__DIR__ . '/../../../capell.json');

    expect(StructuredContentLibraryServiceProvider::class)->toExtend(AbstractPackageServiceProvider::class)
        ->and(StructuredContentLibraryServiceProvider::$name)->toBe('capell-structured-content-library')
        ->and(StructuredContentLibraryServiceProvider::$packageName)->toBe('capell-app/structured-content-library')
        ->and($manifest['name'])->toBe('capell-app/structured-content-library')
        ->and(data_get($manifest, 'providers.runtime'))->toBe([StructuredContentLibraryServiceProvider::class])
        ->and(data_get($manifest, 'providers.admin'))->toBe([StructuredContentLibraryServiceProvider::class])
        ->and(data_get($manifest, 'contributes.0.type'))->toBe('admin-resource')
        ->and(data_get($manifest, 'contributes.0.class'))->toBe(StructuredContentItemResourceContribution::class)
        ->and(data_get($manifest, 'contributes.0.resourceClass'))->toBe(StructuredContentItemResource::class)
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'model',
            'class' => StructuredContentModelsContribution::class,
        ])
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->not->toContain('admin-resource')
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->toBe([
            'content-section-adapter',
            'theme-adapter',
        ])
        ->and(data_get($manifest, 'actions.buildStructuredContentSections'))
        ->toBe(BuildStructuredContentSectionsAction::class)
        ->and(data_get($manifest, 'capabilities'))->toContain(
            'structured-content-library',
            'structured-content-public-adapter',
            'structured-content-import',
        )
        ->and(data_get($manifest, 'capabilities'))->not->toContain(
            'structured-content-section-adapter',
            'structured-content-theme-adapter',
        )
        ->and($manifest['description'])->toBe('Structured Content Library stores portable reusable records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos.');
});

it('declares the built marketplace screenshot contract', function (): void {
    $manifest = capell_json_file_array(__DIR__ . '/../../../capell.json');
    $contract = capell_json_file_array(__DIR__ . '/../../../docs/screenshots.json');
    $screenshots = data_get($manifest, 'marketplace.screenshots', []);
    $entries = $contract['entries'] ?? [];

    throw_unless(is_array($screenshots), RuntimeException::class, 'Structured Content screenshots must be an array.');
    throw_unless(is_array($entries), RuntimeException::class, 'Structured Content screenshot contract entries must be an array.');

    $manifestPaths = collect($screenshots)
        ->pluck('path')
        ->all();

    expect($manifestPaths)->toBe([
        'docs/assets/marketplace/extension-card.jpg',
        'docs/screenshots/structured-content-list.png',
        'docs/screenshots/structured-content-form.png',
        'docs/screenshots/structured-content-theme-rendering.png',
    ]);

    foreach ($manifestPaths as $path) {
        throw_unless(is_string($path), RuntimeException::class, 'Structured Content screenshot paths must be strings.');

        expect(File::exists(__DIR__ . '/../../../' . $path))->toBeTrue();
    }

    expect(collect($entries)->pluck('id')->all())->toBe([
        'structured-content-list',
        'structured-content-form',
        'structured-content-theme-rendering',
    ]);
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
