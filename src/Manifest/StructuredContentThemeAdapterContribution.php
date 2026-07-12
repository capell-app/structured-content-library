<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Manifest;

use Capell\Core\Contracts\Extensions\ExtensionContribution;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;

final class StructuredContentThemeAdapterContribution implements ExtensionContribution
{
    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    public static function adapterKey(): string
    {
        return 'theme-adapter';
    }

    public static function actionClass(): string
    {
        return BuildPublicStructuredContentItemsAction::class;
    }

    public static function outputDataClass(): string
    {
        return PublicStructuredContentItemData::class;
    }
}
