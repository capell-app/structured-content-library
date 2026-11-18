<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Actions\Translations\ResolveLanguageFallbackChainAction;
use Capell\Core\Models\Language;
use Capell\Core\Models\Translation;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * The record's translation for a language: that language's own, otherwise the first one along
 * the language's configured fallback chain. Null means the record's own wording applies.
 *
 * @method static Translation|null run(StructuredContentItem $item, Language $language)
 */
final class ResolveStructuredContentTranslationAction
{
    use AsFake;
    use AsObject;

    public function handle(StructuredContentItem $item, Language $language): ?Translation
    {
        $translations = $item->translations;

        if ($translations->isEmpty()) {
            return null;
        }

        foreach (ResolveLanguageFallbackChainAction::run($language) as $languageId) {
            $translation = $translations->first(
                static fn (Translation $candidate): bool => (int) $candidate->language_id === $languageId,
            );

            if ($translation instanceof Translation) {
                return $translation;
            }
        }

        return null;
    }
}
