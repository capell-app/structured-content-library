<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Projects an already selected record; callers own visibility and authorisation.
 *
 * With a language, the record's translation for it (or along its fallback chain) replaces the
 * wording it fills in; anything it leaves blank keeps the record's own wording.
 *
 * @method static PublicStructuredContentItemData run(StructuredContentItem $item, ?Language $language = null)
 */
final class BuildPublicStructuredContentItemDataAction
{
    use AsFake;
    use AsObject;

    public function handle(StructuredContentItem $item, ?Language $language = null): PublicStructuredContentItemData
    {
        $translation = $language instanceof Language ? ResolveStructuredContentTranslationAction::run($item, $language) : null;
        $payload = $this->payload($item, $translation);
        $media = ResolvePublicStructuredContentMediaAction::run($item, $payload, $language);
        $mediaCollection = StructuredContentDefinitionData::forType($item->type)->mediaCollection();

        return new PublicStructuredContentItemData(
            type: $item->type,
            title: $this->translatedText($translation?->title) ?? $item->title,
            slug: $item->slug,
            summary: EnsurePortableContentHtmlAction::run($this->translatedText($this->meta($translation)['summary'] ?? null) ?? $item->summary, 'summary'),
            content: EnsurePortableContentHtmlAction::run($this->translatedText($translation?->content) ?? $item->content),
            payload: BuildPublicStructuredContentPayloadAction::run($payload, $item->type),
            image: $mediaCollection === MediaCollectionEnum::Image ? $media : null,
            logo: $mediaCollection === MediaCollectionEnum::Logo ? $media : null,
        );
    }

    private function payload(StructuredContentItem $item, ?Translation $translation): ?StructuredContentPayloadData
    {
        $translated = $this->meta($translation)['payload'] ?? null;

        if (! is_array($translated) || $translated === []) {
            return $item->payload;
        }

        $values = $item->payload?->toArray() ?? [];

        foreach ($translated as $field => $value) {
            if (is_string($field) && $this->translatedText($value) !== null) {
                $values[$field] = $value;
            }
        }

        return StructuredContentPayloadData::from($values);
    }

    /** @return array<array-key, mixed> */
    private function meta(?Translation $translation): array
    {
        $meta = $translation?->meta;

        return is_array($meta) ? $meta : [];
    }

    private function translatedText(mixed $value): ?string
    {
        return is_string($value) && trim($value) !== '' ? $value : null;
    }
}
