<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Manifest\ManifestValidator;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentSectionData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Manifest\StructuredContentItemResourceContribution;
use Capell\StructuredContentLibrary\Manifest\StructuredContentModelsContribution;
use Capell\StructuredContentLibrary\Manifest\StructuredContentSectionAdapterContribution;
use Capell\StructuredContentLibrary\Manifest\StructuredContentThemeAdapterContribution;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\File;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('declares provider classes and package metadata', function (): void {
    $manifest = capell_json_file_array(__DIR__ . '/../../../capell.json');
    $composer = capell_json_file_array(__DIR__ . '/../../../composer.json');

    (new ManifestValidator)->validate($manifest, $composer, 'capell-app/structured-content-library', __DIR__ . '/../../../capell.json');

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
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'agent-capability',
            'class' => StructuredContentSectionAdapterContribution::class,
            'capability' => 'content-section-adapter',
            'adapter' => 'content-sections',
            'actionClass' => BuildStructuredContentSectionsAction::class,
            'outputDataClass' => StructuredContentSectionData::class,
            'consumerPackages' => ['capell-app/content-sections'],
            'publicOutputSafety' => 'Returns hydrated public DTOs only; no public route, Blade query, editor marker, model id, field path, signed URL, or package identifier is emitted.',
        ])
        ->and(data_get($manifest, 'contributes'))->toContain([
            'type' => 'agent-capability',
            'class' => StructuredContentThemeAdapterContribution::class,
            'capability' => 'theme-adapter',
            'adapter' => 'theme',
            'actionClass' => BuildPublicStructuredContentItemsAction::class,
            'outputDataClass' => PublicStructuredContentItemData::class,
            'consumerPackages' => ['capell-app/theme-foundation'],
            'publicOutputSafety' => 'Returns hydrated public DTOs only; no public route, Blade query, editor marker, model id, field path, signed URL, or package identifier is emitted.',
        ])
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->not->toContain('admin-resource')
        ->and(data_get($manifest, 'contributionTraceability.deferredContributions'))->toBe([])
        ->and(data_get($manifest, 'actions.buildStructuredContentSections'))
        ->toBe(BuildStructuredContentSectionsAction::class)
        ->and(data_get($manifest, 'capabilities'))->toContain(
            'structured-content-library',
        )
        ->and(data_get($manifest, 'capabilities'))->not->toContain(
            'structured-content-section-adapter',
            'structured-content-theme-adapter',
        )
        ->and($manifest['description'])->toBe('Structured Content Library stores portable reusable records for case studies, testimonials, team members, services, FAQs, resources, partners, locations, and logos.');
});

it('keeps adapter contribution markers aligned with public data actions', function (): void {
    expect(StructuredContentSectionAdapterContribution::adapterKey())->toBe('content-section-adapter')
        ->and(StructuredContentSectionAdapterContribution::actionClass())->toBe(BuildStructuredContentSectionsAction::class)
        ->and(StructuredContentSectionAdapterContribution::outputDataClass())->toBe(StructuredContentSectionData::class)
        ->and(StructuredContentSectionAdapterContribution::compatibleCapellApiVersion())->toBe('^1.0')
        ->and(StructuredContentThemeAdapterContribution::adapterKey())->toBe('theme-adapter')
        ->and(StructuredContentThemeAdapterContribution::actionClass())->toBe(BuildPublicStructuredContentItemsAction::class)
        ->and(StructuredContentThemeAdapterContribution::outputDataClass())->toBe(PublicStructuredContentItemData::class)
        ->and(StructuredContentThemeAdapterContribution::compatibleCapellApiVersion())->toBe('^1.0');
});

it('declares the marketplace screenshot contract without promoting mock captures', function (): void {
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
    ]);

    foreach ($manifestPaths as $path) {
        throw_unless(is_string($path), RuntimeException::class, 'Structured Content screenshot paths must be strings.');

        expect(File::exists(__DIR__ . '/../../../' . $path))->toBeTrue();
    }

    expect(collect($entries)->pluck('id')->all())->toBe([
        'structured-content-list',
        'structured-content-form',
        'structured-content-theme-rendering',
        'structured-content-testimonial-public',
        'structured-content-type-variation',
        'structured-content-empty-state',
        'structured-content-library-admin-sidebar-menu-open',
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

it('registers frontend cache invalidation dependencies when the registry is available', function (): void {
    $registry = new class
    {
        /** @var array<class-string, string|array<int, string>> */
        public array $dependencies = [];

        /**
         * @param  class-string  $modelClass
         * @param  string|array<int, string>  $cachePatterns
         */
        public function registerDependency(string $modelClass, string|array $cachePatterns): void
        {
            $this->dependencies[$modelClass] = $cachePatterns;
        }
    };

    app()->instance('Capell\\Frontend\\Support\\Cache\\CacheInvalidationRegistry', $registry);

    (new StructuredContentLibraryServiceProvider(app()))->packageRegistered();

    expect($registry->dependencies[StructuredContentItem::class] ?? null)
        ->toBe('structured-content-library-*');
});
