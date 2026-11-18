<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use Capell\StructuredContentLibrary\Data\StructuredContentTranslationData;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Makes a record's saved translations match the given list: one per language, validated like
 * the record itself. A language left out of the list loses its translation.
 *
 * @method static void run(StructuredContentItem $item, list<StructuredContentTranslationData> $translations)
 */
final class SyncStructuredContentTranslationsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  list<StructuredContentTranslationData>  $translations
     */
    public function handle(StructuredContentItem $item, array $translations): void
    {
        $rows = [];

        foreach (array_values($translations) as $index => $translation) {
            $rows[$translation->languageId] = $this->validated($item, $translation, $index, $rows);
        }

        // A removed translation is soft deleted; adding its language back restores that row.
        /** @var array<int, Translation> $existing */
        $existing = [];

        foreach ($item->translations()->withTrashed()->get() as $translation) {
            if ($translation instanceof Translation) {
                $existing[$translation->language_id] = $translation;
            }
        }

        foreach ($existing as $languageId => $translation) {
            if (! array_key_exists($languageId, $rows) && ! $translation->trashed()) {
                $translation->delete();
            }
        }

        foreach ($rows as $languageId => $row) {
            $translation = $existing[$languageId] ?? null;

            if ($translation instanceof Translation) {
                if ($translation->trashed()) {
                    $translation->restore();
                }

                $translation->update($row);

                continue;
            }

            $item->translations()->create(['language_id' => $languageId, ...$row]);
        }

        $item->unsetRelation('translations');
    }

    /**
     * @param  array<int, array<string, mixed>>  $accepted
     * @return array{title: string, content: string|null, meta: array{summary: string|null, payload: array<string, string>}}
     */
    private function validated(StructuredContentItem $item, StructuredContentTranslationData $translation, int $index, array $accepted): array
    {
        $prefix = 'translations.' . $index . '.';

        if (array_key_exists($translation->languageId, $accepted)) {
            throw ValidationException::withMessages([$prefix . 'language_id' => __('capell-structured-content-library::validation.translation_language_repeated')]);
        }

        if (! Language::query()->whereKey($translation->languageId)->exists()) {
            throw ValidationException::withMessages([$prefix . 'language_id' => __('capell-structured-content-library::validation.translation_language_missing')]);
        }

        $title = trim($translation->title);

        if ($title === '') {
            throw ValidationException::withMessages([$prefix . 'title' => __('capell-structured-content-library::validation.title_required')]);
        }

        ValidateStructuredContentPayloadAction::run($item->type, $translation->payload);

        $content = EnsurePortableContentHtmlAction::run($translation->content);
        $summary = EnsurePortableContentHtmlAction::run($translation->summary, 'summary');
        $payload = array_filter(
            $translation->payload?->toArray() ?? [],
            static fn (mixed $value): bool => is_string($value) && trim($value) !== '',
        );

        return [
            'title' => $title,
            'content' => $content !== null && $content !== '' ? $content : null,
            'meta' => [
                'summary' => $summary !== null && $summary !== '' ? $summary : null,
                'payload' => $payload,
            ],
        ];
    }
}
