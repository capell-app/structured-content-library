<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests;

use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\CapellCoreManager;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
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

    /**
     * @param  Application  $app
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        return [
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
        $app->singleton(CapellCoreManager::class);

        Config::set('database.default', 'sqlite');
        Config::set('database.connections.sqlite.database', ':memory:');
        Config::set('database.connections.sqlite.url');
        Config::set('app.key', 'base64:' . base64_encode(str_repeat('x', 32)));
        CapellCore::forcePackageInstalled(StructuredContentLibraryServiceProvider::$packageName);
    }

    protected function defineDatabaseMigrations(): void
    {
        Schema::create('sites', function (Blueprint $table): void {
            $table->id();
        });

        $this->loadMigrationsFrom(__DIR__ . '/../database/migrations');
    }
}
