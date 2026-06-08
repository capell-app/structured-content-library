<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\StructuredContentLibrary\Enums\ResourceEnum;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Capell\StructuredContentLibrary\Support\StructuredContentModelRegistrar;
use Override;
use Spatie\LaravelPackageTools\Package;

class StructuredContentLibraryServiceProvider extends AbstractPackageServiceProvider
{
    private const string FRONTEND_CACHE_INVALIDATION_REGISTRY = 'Capell\\Frontend\\Support\\Cache\\CacheInvalidationRegistry';

    public static string $name = 'capell-structured-content-library';

    public static string $packageName = 'capell-app/structured-content-library';

    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasTranslations()
            ->hasMigrations([
                '2026_05_31_000001_create_structured_content_items_table',
                '2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table',
            ]);
    }

    public function packageRegistered(): void
    {
        $this->app->booted(function (): void {
            if (! $this->isPackageInstalled()) {
                return;
            }

            $this
                ->registerModels()
                ->registerProtectedTables()
                ->registerAdminResources()
                ->registerCacheInvalidationDependencies()
                ->registerStructuredContentCacheEvents();
        });
    }

    #[Override]
    protected function isPackageInstalled(): bool
    {
        return CapellCore::isPackageInstalled(static::$packageName);
    }

    private function registerModels(): self
    {
        StructuredContentModelRegistrar::register();

        return $this;
    }

    private function registerProtectedTables(): self
    {
        CapellCore::registerProtectedTable('structured_content_items');

        return $this;
    }

    private function registerAdminResources(): self
    {
        CapellAdmin::contributeToAdminSurface(AdminSurfaceContributionData::resource(
            class: ResourceEnum::StructuredContentItem->value,
            group: ResourceEnum::StructuredContentItem->name,
        ));

        return $this;
    }

    private function registerCacheInvalidationDependencies(): self
    {
        $registryClass = self::FRONTEND_CACHE_INVALIDATION_REGISTRY;

        if (! class_exists($registryClass) || ! $this->app->bound($registryClass)) {
            return $this;
        }

        $registry = $this->app->make($registryClass);

        if (is_object($registry) && method_exists($registry, 'registerDependency')) {
            $registry->registerDependency(StructuredContentItem::class, 'structured-content-library-*');
        }

        return $this;
    }

    private function registerStructuredContentCacheEvents(): self
    {
        StructuredContentItem::saved(static function (StructuredContentItem $item): void {
            StructuredContentCache::flush();
        });

        StructuredContentItem::deleted(static function (StructuredContentItem $item): void {
            StructuredContentCache::flush();
        });

        StructuredContentItem::restored(static function (StructuredContentItem $item): void {
            StructuredContentCache::flush();
        });

        StructuredContentItem::forceDeleted(static function (StructuredContentItem $item): void {
            StructuredContentCache::flush();
        });

        return $this;
    }
}
