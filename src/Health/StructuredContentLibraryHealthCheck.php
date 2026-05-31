<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Health;

use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;

final class StructuredContentLibraryHealthCheck implements ChecksExtensionHealth
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }
}
