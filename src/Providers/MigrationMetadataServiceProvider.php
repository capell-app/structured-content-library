<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Providers;

use Capell\Core\Support\Migration\MigrationRootRegistry;
use Illuminate\Support\ServiceProvider;
use Override;

final class MigrationMetadataServiceProvider extends ServiceProvider
{
    #[Override]
    public function register(): void
    {
        $this->callAfterResolving(MigrationRootRegistry::class, static function (MigrationRootRegistry $registry): void {
            $registry->register('capell-app/structured-content-library', __DIR__ . '/../../database/migrations', [
                '2026_05_31_000001_create_structured_content_items_table' => 'structured_content_items',
            ]);
        });
    }
}
