<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Providers;

use Capell\Admin\Data\AdminSurfaceContributionData;
use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use Capell\Core\Support\Packages\AbstractPackageServiceProvider;
use Capell\Core\Support\TableName;
use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\StructuredContentLibrary\Actions\InvalidateStructuredContentPublicOutputAction;
use Capell\StructuredContentLibrary\Console\Commands\InvalidateDueScheduledStructuredContentCachesCommand;
use Capell\StructuredContentLibrary\Console\Commands\SeedStructuredContentScreenshotFixtureCommand;
use Capell\StructuredContentLibrary\Enums\ResourceEnum;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Observers\StructuredContentPublicOutputObserver;
use Capell\StructuredContentLibrary\Observers\StructuredContentRelatedRecordObserver;
use Capell\StructuredContentLibrary\Policies\StructuredContentItemPolicy;
use Capell\StructuredContentLibrary\Support\StructuredContentModelRegistrar;
use Filament\Support\Assets\Js;
use Filament\Support\Facades\FilamentAsset;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Override;
use Spatie\LaravelPackageTools\Package;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

final class StructuredContentLibraryServiceProvider extends AbstractPackageServiceProvider
{
    private const string FRONTEND_CACHE_INVALIDATION_REGISTRY = CacheInvalidationRegistry::class;

    private const string OBSERVERS_REGISTERED = 'capell-structured-content-library.public-output-observers';

    public static string $name = 'capell-structured-content-library';

    public static string $packageName = 'capell-app/structured-content-library';

    #[Override]
    public function configurePackage(Package $package): void
    {
        $package
            ->name(self::$name)
            ->hasConfigFile()
            ->hasTranslations()
            ->hasViews()
            ->hasCommands([
                SeedStructuredContentScreenshotFixtureCommand::class,
                InvalidateDueScheduledStructuredContentCachesCommand::class,
            ])
            ->hasMigrations([
                '2026_05_31_000001_create_structured_content_items_table',
                '2026_06_04_000001_add_unique_scope_slug_index_to_structured_content_items_table',
                '2026_07_10_000001_add_normalized_scope_key_to_structured_content_items',
                '2026_11_14_000001_add_expires_at_to_structured_content_items_table',
            ]);
    }

    #[Override]
    public function packageBooted(): void
    {
        FilamentAsset::register([
            Js::make('portable-list-semantics', __DIR__ . '/../../resources/js/portable-list-semantics.js')
                ->module()
                ->loadedOnRequest(),
        ], package: self::$name);
    }

    #[Override]
    protected function bootInstalledRuntime(): void
    {
        $this
            ->registerModels()
            ->registerPolicies()
            ->registerProtectedTables()
            ->registerAdminResources()
            ->registerCacheInvalidationDependencies()
            ->registerPublicOutputObservers()
            ->registerScheduledPublicationInvalidation();
    }

    private function registerModels(): self
    {
        StructuredContentModelRegistrar::register();

        return $this;
    }

    private function registerPolicies(): self
    {
        Gate::policy(StructuredContentItem::class, StructuredContentItemPolicy::class);

        return $this;
    }

    private function registerProtectedTables(): self
    {
        CapellCore::registerProtectedTable(TableName::resolve('structured_content_items'));

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
            $registry->registerDependency(StructuredContentItem::class, InvalidateStructuredContentPublicOutputAction::PAGE_CACHE_PATTERNS);
        }

        return $this;
    }

    private function registerPublicOutputObservers(): self
    {
        // The installed runtime can boot again in one application; observe once per dispatcher.
        if ($this->app->bound(self::OBSERVERS_REGISTERED)) {
            return $this;
        }

        $this->app->instance(self::OBSERVERS_REGISTERED, true);
        StructuredContentItem::observe(StructuredContentPublicOutputObserver::class);

        $mediaClass = config('media-library.media_model', Media::class);

        if (is_string($mediaClass) && is_a($mediaClass, Model::class, true)) {
            $mediaClass::observe(StructuredContentRelatedRecordObserver::class);
        }

        // Event::listen rather than observe(): observe() boots the model, which attributes Core's own
        // Translation listeners to this package only when nothing booted the model first.
        foreach (['saved', 'deleted', 'restored'] as $event) {
            Event::listen(sprintf('eloquent.%s: ', $event) . Translation::class, StructuredContentRelatedRecordObserver::class . '@' . $event);
        }

        Language::observe(StructuredContentRelatedRecordObserver::class);

        return $this;
    }

    private function registerScheduledPublicationInvalidation(): self
    {
        $this->registerSchedule(static function (Schedule $schedule): void {
            $schedule->command('structured-content-library:invalidate-due-caches')
                ->everyMinute()
                ->withoutOverlapping()
                ->onOneServer();
        });

        return $this;
    }
}
