<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\StructuredContentLibrary\Enums\ResourceEnum;
use Capell\StructuredContentLibrary\Support\StructuredContentModelRegistrar;
use Override;
use Spatie\LaravelPackageTools\Package;

class StructuredContentLibraryServiceProvider extends AbstractPackageServiceProvider
{
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
                ->registerAdminResources();
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
}
