<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Observers;

use Capell\StructuredContentLibrary\Actions\InvalidateStructuredContentPublicOutputAction;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

/**
 * Clears public output once a record change has committed, so a reader cannot refill a cache
 * from the old row between the clear and the commit.
 */
final class StructuredContentPublicOutputObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(StructuredContentItem $item): void
    {
        $previous = $item->getPrevious();
        $previousSiteIds = array_key_exists('site_id', $previous)
            ? [is_numeric($previous['site_id']) ? (int) $previous['site_id'] : null]
            : [];

        InvalidateStructuredContentPublicOutputAction::run([$item], $previousSiteIds);
    }

    public function deleted(StructuredContentItem $item): void
    {
        InvalidateStructuredContentPublicOutputAction::run([$item]);
    }

    public function restored(StructuredContentItem $item): void
    {
        InvalidateStructuredContentPublicOutputAction::run([$item]);
    }

    public function forceDeleted(StructuredContentItem $item): void
    {
        InvalidateStructuredContentPublicOutputAction::run([$item]);
    }
}
