<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\StructuredContentLibrary\Actions\BuildStructuredContentSectionsAction;
use Capell\StructuredContentLibrary\Data\StructuredContentSectionData;

final class StructuredContentSectionAdapterContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^0.0';
    }

    public static function adapterKey(): string
    {
        return 'content-section-adapter';
    }

    public static function actionClass(): string
    {
        return BuildStructuredContentSectionsAction::class;
    }

    public static function outputDataClass(): string
    {
        return StructuredContentSectionData::class;
    }
}
