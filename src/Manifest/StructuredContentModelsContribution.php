<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;

final class StructuredContentModelsContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
