<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Bridges;

use Capell\Core\Facades\CapellCore;
use Capell\PublishingStudio\Actions\ComparePublishingRevisionAction;
use Capell\PublishingStudio\Actions\ListPublishingRevisionsAction;
use Capell\PublishingStudio\Actions\RecordPublishingRevisionAction;
use Capell\PublishingStudio\Enums\PublishingRevisionEventEnum;
use Capell\PublishingStudio\Models\PublishingRevision;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * Revision history for structured content, kept in Publishing Studio's revision log so records
 * share one history with pages and sections. Publishing Studio stays optional: without it, or
 * before its tables exist, nothing is recorded and no history is offered.
 */
final class PublishingStudioStructuredContentRevisions
{
    private const string PUBLISHING_STUDIO = 'capell-app/publishing-studio';

    public static function available(): bool
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return false;
        }

        if (! CapellCore::isPackageEnabled(self::PUBLISHING_STUDIO)) {
            return false;
        }

        try {
            return Schema::hasTable(new PublishingRevision()->getTable());
        } catch (Throwable) {
            return false;
        }
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

        self::record($item, PublishingRevisionEventEnum::Published, $before, $after, $actor);
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

        self::record($item, PublishingRevisionEventEnum::Restored, $before, $after, $actor, (string) __('capell-structured-content-library::admin.revision_restored_note', ['version' => $version]));
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

        if (! self::available()) {
            return [];
        }

        $history = [];

        foreach (ListPublishingRevisionsAction::run(revisionableType: StructuredContentItem::class, revisionableId: $item->id) as $revision) {
            $history[] = [
                'id' => self::key($revision->getKey()),
                'version' => $revision->version,
                'event' => $revision->event_type->value,
                'created_at' => $revision->created_at?->toIso8601String(),
                'changed' => array_map(strval(...), array_keys(ComparePublishingRevisionAction::run($revision)['changes'])),
            ];
        }

        return $history;
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

        if (! self::available()) {
            return null;
        }

        $revision = PublishingRevision::query()
            ->whereKey($revisionId)
            ->where('revisionable_type', StructuredContentItem::class)
            ->where('revisionable_id', $item->id)
            ->first();

        if (! $revision instanceof PublishingRevision || ! is_array($revision->after_payload)) {
            return null;
        }

        return ['version' => $revision->version, 'snapshot' => $revision->after_payload];
    }

    /**
     * @param  array<string, mixed>|null  $before
     * @param  array<string, mixed>  $after
     */
    private static function record(StructuredContentItem $item, PublishingRevisionEventEnum $event, ?array $before, array $after, ?Authenticatable $actor, ?string $notes = null): void
    {
        if (! CapellCore::isPackageAvailable('capell-app/publishing-studio')) {
            return;
        }

        if (! self::available()) {
            return;
        }

        RecordPublishingRevisionAction::run(
            revisionableType: StructuredContentItem::class,
            revisionableId: $item->id,
            revisionableUuid: null,
            eventType: $event,
            beforePayload: $before,
            afterPayload: $after,
            actor: $actor,
            notes: $notes,
        );
    }

    private static function key(mixed $key): int
    {
        return is_numeric($key) ? (int) $key : 0;
    }
}
