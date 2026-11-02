<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests;

use Capell\Frontend\Providers\FrontendServiceProvider;
use Capell\StructuredContentLibrary\Providers\StructuredContentLibraryServiceProvider;
use Illuminate\Foundation\Application;
use Override;

/**
 * Boots the frontend package ahead of this package, as a site install does,
 * so cache dependencies registered at boot reach the frontend registry.
 */
class StructuredContentLibraryWithFrontendTestCase extends StructuredContentLibraryTestCase
{
    /**
     * @param  Application  $app
     * @return class-string[]
     */
    #[Override]
    protected function getPackageProviders(mixed $app): array
    {
        $providers = parent::getPackageProviders($app);
        $position = array_search(StructuredContentLibraryServiceProvider::class, $providers, true);
        array_splice($providers, (int) $position, 0, [FrontendServiceProvider::class]);

        return $providers;
    }
}
