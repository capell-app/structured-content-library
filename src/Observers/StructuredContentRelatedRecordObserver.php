<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Observers;

use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use Capell\StructuredContentLibrary\Actions\InvalidateStructuredContentPublicOutputAction;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;
use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Rows stored beside a record also shape its public output: its picture (saved after the record
 * itself, once the record exists), the picture's alt text in the media's translations, the
 * record's own translations, and the languages and fallbacks that pick a translation. A change
 * to any of them is cleared the same way as a change to the record.
 */
final class StructuredContentRelatedRecordObserver implements ShouldHandleEventsAfterCommit
{
    public function saved(Model $model): void
    {
        if ($model instanceof Language) {
            StructuredContentCache::flush();

            return;
        }

        $item = $this->owner($model);

        if ($item instanceof StructuredContentItem) {
            InvalidateStructuredContentPublicOutputAction::run([$item]);
        }
    }

    public function deleted(Model $model): void
    {
        $this->saved($model);
    }

    public function restored(Model $model): void
    {
        $this->saved($model);
    }

    private function owner(Model $model): ?StructuredContentItem
    {
        $itemMorph = new StructuredContentItem()->getMorphClass();

        if ($model instanceof Translation && $model->translatable_type === $itemMorph) {
            return $this->item($model->translatable_id);
        }

        $media = $model instanceof Translation ? $this->translatedMedia($model) : $model;

        if (! $media instanceof Media || $media->model_type !== $itemMorph) {
            return null;
        }

        return $this->item($media->model_id);
    }

    private function item(mixed $id): ?StructuredContentItem
    {
        if (! is_numeric($id)) {
            return null;
        }

        $item = StructuredContentItem::query()->withTrashed()->find((int) $id);

        return $item instanceof StructuredContentItem ? $item : null;
    }

    private function translatedMedia(Translation $translation): ?Media
    {
        $mediaClass = config('media-library.media_model', Media::class);

        if (! is_string($mediaClass) || ! is_a($mediaClass, Media::class, true)) {
            return null;
        }

        if ($translation->translatable_type !== new $mediaClass()->getMorphClass()) {
            return null;
        }

        $media = $mediaClass::query()->find($translation->translatable_id);

        return $media instanceof Media ? $media : null;
    }
}
