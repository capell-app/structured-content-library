<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Override;

final class StructuredContentConsoleCommandsContribution implements ExtensionContribution
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
