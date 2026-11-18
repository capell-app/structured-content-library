<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Bridges;

use Capell\Core\Facades\CapellCore;
use Capell\PublishingStudio\Filament\Actions\PublishingRevisionsHeaderAction;
use Filament\Actions\Action;

/**
 * Publishing Studio's revisions header action. Publishing Studio is optional, so callers reach
 * this class only after confirming `capell-app/publishing-studio` is available.
 */
final class PublishingStudioRevisionsHeaderActionBridge
{
    public static function make(): ?Action
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return null;
        }

        return PublishingRevisionsHeaderAction::make();
    }
}
