<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Everything an editor can restore about a record, as plain JSON-safe values: its fields, its
 * publishing window and its translations. The picture is a file in the media library and is not
 * part of the snapshot.
 *
 * @method static array<string, mixed> run(StructuredContentItem $item)
 */
final class BuildStructuredContentRevisionSnapshotAction
{
    use AsFake;
    use AsObject;

    /**
     * @return array<string, mixed>
     */
    public function handle(StructuredContentItem $item): array
    {
        return [
            'site_id' => $item->site_id,
            'type' => $item->type->value,
            'status' => $item->status->value,
            'title' => $item->title,
            'slug' => $item->slug,
            'summary' => $item->summary,
            'content' => $item->content,
            'payload' => array_filter($item->payload?->toArray() ?? [], static fn (mixed $value): bool => $value !== null),
            'published_at' => $item->published_at?->toIso8601String(),
            'expires_at' => $item->expires_at?->toIso8601String(),
            'sort_order' => $item->sort_order,
            'translations' => BuildStructuredContentTranslationFormStateAction::run($item),
        ];
    }
}
