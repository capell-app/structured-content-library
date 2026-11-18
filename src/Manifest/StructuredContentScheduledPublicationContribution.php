<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\Core\Contracts\Extensions\RunsScheduledExtensionJob;
use Override;

final class StructuredContentScheduledPublicationContribution implements ExtensionContribution, RunsScheduledExtensionJob
{
    #[Override]
    public static function compatibleCapellApiVersion(): string
    {
        return '^1.0';
    }
}
