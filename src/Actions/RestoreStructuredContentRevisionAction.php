<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentRevisions;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Puts a record back to one of its recorded revisions: its fields, publishing window and
 * translations. The restore is authorised and validated like an edit, and is itself recorded as
 * a revision, so it can be undone the same way.
 *
 * @method static StructuredContentItem run(StructuredContentItem $item, int $revisionId)
 */
final class RestoreStructuredContentRevisionAction
{
    use AsFake;
    use AsObject;

    public function handle(StructuredContentItem $item, int $revisionId): StructuredContentItem
    {
        $revision = StructuredContentRevisions::snapshot($item, $revisionId);

        if ($revision === null) {
            throw ValidationException::withMessages([
                'revision' => __('capell-structured-content-library::validation.revision_missing'),
            ]);
        }

        $snapshot = $revision['snapshot'];

        // The type is fixed after creation; a revision always carries the record's own type.
        $data = StructuredContentItemData::from([
            ...$snapshot,
            'type' => $item->type,
            'translations' => is_array($snapshot['translations'] ?? null) ? array_values($snapshot['translations']) : [],
        ]);

        return DB::transaction(function () use ($item, $data, $revision): StructuredContentItem {
            $before = BuildStructuredContentRevisionSnapshotAction::run($item);
            $restored = UpdateStructuredContentItemAction::run($item, $data, recordRevision: false);

            StructuredContentRevisions::recordRestored($restored, $before, BuildStructuredContentRevisionSnapshotAction::run($restored), auth()->user(), $revision['version']);

            return $restored;
        });
    }
}
