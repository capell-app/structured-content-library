<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A record's saved translations in the shape the edit form's translations list holds them.
 *
 * @method static list<array{language_id: int, title: string|null, summary: string|null, content: string|null, payload: array<string, string>}> run(StructuredContentItem $item)
 */
final class BuildStructuredContentTranslationFormStateAction
{
    use AsFake;
    use AsObject;

    /**
     * @return list<array{language_id: int, title: string|null, summary: string|null, content: string|null, payload: array<string, string>}>
     */
    public function handle(StructuredContentItem $item): array
    {
        $state = [];

        foreach ($item->translations()->orderBy('language_id')->get() as $translation) {
            $meta = is_array($translation->meta) ? $translation->meta : [];
            $payload = is_array($meta['payload'] ?? null) ? $meta['payload'] : [];

            $state[] = [
                'language_id' => (int) $translation->language_id,
                'title' => $translation->title,
                'summary' => $this->text($meta['summary'] ?? null),
                'content' => $this->text($translation->content),
                'payload' => array_filter($payload, static fn (mixed $value, mixed $key): bool => is_string($key) && is_string($value), ARRAY_FILTER_USE_BOTH),
            ];
        }

        return $state;
    }

    private function text(mixed $value): ?string
    {
        return is_string($value) && $value !== '' ? $value : null;
    }
}
