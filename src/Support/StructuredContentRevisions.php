<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support;

use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Bridges\PublishingStudioStructuredContentRevisions;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Revision history for structured content, kept in Publishing Studio's revision log so records
 * share one history with pages and sections. Publishing Studio stays optional: without it, or
 * before its tables exist, nothing is recorded and no history is offered.
 */
final class StructuredContentRevisions
{
    public static function available(): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        return PublishingStudioStructuredContentRevisions::available();
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public static function recordPublished(StructuredContentItem $item, ?array $before, array $after, ?Authenticatable $actor): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        PublishingStudioStructuredContentRevisions::recordPublished($item, $before, $after, $actor);
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    public static function recordRestored(StructuredContentItem $item, ?array $before, array $after, ?Authenticatable $actor, int $version): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        PublishingStudioStructuredContentRevisions::recordRestored($item, $before, $after, $actor, $version);
    }

    /**
     * The record's revisions, newest first, with the fields each one changed.
     *
     * @return list<array{id: int, version: int, event: string, created_at: string|null, changed: list<string>}>
     */
    public static function history(StructuredContentItem $item): array
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return [];
        }

        return PublishingStudioStructuredContentRevisions::history($item);
    }

    /**
     * The snapshot a revision recorded for this record, or null when it is not one of its own.
     *
     * @return array{version: int, snapshot: array<array-key, mixed>}|null
     */
    public static function snapshot(StructuredContentItem $item, int $revisionId): ?array
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return null;
        }

        return PublishingStudioStructuredContentRevisions::snapshot($item, $revisionId);
    }
}
