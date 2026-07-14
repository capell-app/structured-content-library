<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests;

use Aimeos\Nestedset\NestedSetServiceProvider;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Macros\BlueprintMacros;
use Capell\Core\Support\CapellCoreManager;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Capell\StructuredContentLibrary\Tests\Fixtures\StructuredContentGlobalTestUser;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Lorisleiva\Actions\ActionServiceProvider;
use Orchestra\Testbench\TestCase;
use Override;
use Spatie\LaravelData\LaravelDataServiceProvider;

spl_autoload_register(function (string $class): void {
    $prefix = 'Capell\\StructuredContentLibrary\\';

    if (! str_starts_with($class, $prefix)) {
        return;
    }

    $path = __DIR__ . '/../src/' . str_replace('\\', '/', substr($class, strlen($prefix))) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

class StructuredContentLibraryTestCase extends TestCase
{
    use RefreshDatabase;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        if (! Schema::hasTable('users')) {
            Schema::create('users', function (Blueprint $table): void {
                $table->id();
            });
        }

        $this->actingAs(new StructuredContentGlobalTestUser);
    }

    public function createStructuredContentSite(string $name): int
    {
        $timestamp = now();
        $key = str($name)->slug()->append('-', str()->random(8))->toString();
        $blueprintId = (int) DB::table('blueprints')->insertGetId([
            'name' => $name . ' blueprint',
            'type' => 'theme',
            'key' => $key,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $themeId = (int) DB::table('themes')->insertGetId([
            'name' => $name . ' theme',
            'blueprint_id' => $blueprintId,
            'key' => $key,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
        $languageId = (int) DB::table('languages')->insertGetId([
            'name' => $name . ' language',
            'code' => $key,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);

        return (int) DB::table('sites')->insertGetId([
            'name' => $name,
            'blueprint_id' => $blueprintId,
            'theme_id' => $themeId,
            'language_id' => $languageId,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ]);
    }

    /**
     * @param  Application  $app
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
            NestedSetServiceProvider::class,
            ActionServiceProvider::class,
            LaravelDataServiceProvider::class,
            StructuredContentLibraryServiceProvider::class,
        ];
    }

    /**
     * @param  Application  $app
     */
    #[Override]
    protected function defineEnvironment(mixed $app): void
    {
        /** @var array<string, mixed> $permissionConfig */
        $permissionConfig = require dirname(__DIR__, 3) . '/vendor/spatie/laravel-permission/config/permission.php';

        $app->singleton(CapellCoreManager::class);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('database.connections.sqlite.url');
        Config::set('app.key', 'base64:' . base64_encode(str_repeat('x', 32)));
        Config::set('permission', $permissionConfig);
        Blueprint::mixin(new BlueprintMacros);
        CapellCore::forcePackageInstalled(StructuredContentLibraryServiceProvider::$packageName);
    }

    protected function defineDatabaseMigrations(): void
    {
        if (! Schema::hasTable('blueprints')) {
            Schema::create('blueprints', static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('type');
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('themes')) {
            Schema::create('themes', static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('blueprint_id');
                $table->string('key')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('languages')) {
            Schema::create('languages', static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('sites')) {
            Schema::create('sites', static function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->unsignedBigInteger('blueprint_id')->nullable();
                $table->unsignedBigInteger('theme_id')->nullable();
                $table->unsignedBigInteger('language_id')->nullable();
                $table->timestamps();
            });
        }

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
