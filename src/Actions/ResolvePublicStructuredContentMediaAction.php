<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Models\Language;
use Capell\Core\Models\Media as CoreMedia;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentMediaData;
use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Support\Facades\Config;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * Resolves a record's own picture to public render data.
 *
 * Only the record's media collection for its type is read, so one record can never surface
 * another record's file. The picture resolves only when it is an image on a publicly visible
 * disk with a plain URL; a signed or tokenised URL would expire inside cached HTML and expose
 * its signature, so anything else returns null and the record renders without a picture.
 *
 * @method static PublicStructuredContentMediaData|null run(StructuredContentItem $item, ?StructuredContentPayloadData $payload = null, ?Language $language = null)
 */
final class ResolvePublicStructuredContentMediaAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  StructuredContentPayloadData|null  $payload  The record's payload in the rendered language; the saved payload when null.
     */
    public function handle(StructuredContentItem $item, ?StructuredContentPayloadData $payload = null, ?Language $language = null): ?PublicStructuredContentMediaData
    {
        $definition = StructuredContentDefinitionData::forType($item->type);
        $collection = $definition->mediaCollection();

        if ($collection === null) {
            return null;
        }

        $media = $item->getFirstMedia($collection->value);

        if (! $media instanceof Media || ! str_starts_with((string) $media->mime_type, 'image/')) {
            return null;
        }

        if (Config::get(sprintf('filesystems.disks.%s.visibility', $media->disk)) !== 'public') {
            return null;
        }

        $url = $media->getUrl();

        if ($url === '' || str_contains($url, '?') || str_contains($url, '#')) {
            return null;
        }

        return new PublicStructuredContentMediaData(
            url: $url,
            width: $this->dimension($media, 'width'),
            height: $this->dimension($media, 'height'),
            alt: $this->alt($item, $payload ?? $item->payload, $media, $definition, $language),
        );
    }

    private function dimension(Media $media, string $name): ?int
    {
        $value = $media->getCustomProperty($name);

        return is_numeric($value) && (int) $value > 0 ? (int) $value : null;
    }

    /**
     * The record's own alt text describes the picture in context and wins; otherwise the media
     * library's alt text applies, and a picture marked decorative there gets an empty alt.
     */
    private function alt(StructuredContentItem $item, ?StructuredContentPayloadData $payload, Media $media, StructuredContentDefinitionData $definition, ?Language $language): ?string
    {
        $altField = $definition->mediaAltField();
        $recordAlt = $altField === null ? null : BuildPublicStructuredContentPayloadAction::run($payload, $item->type)[$altField->value] ?? null;

        if (is_string($recordAlt) && $recordAlt !== '') {
            return $recordAlt;
        }

        if (! $media instanceof CoreMedia) {
            return null;
        }

        $metadata = $media->localizedMetadata($language);

        // A language without its own media alt text uses the default language's.
        if ($language instanceof Language && ($metadata->languageId === null || (! $metadata->decorative && trim((string) $metadata->alt) === ''))) {
            $metadata = $media->localizedMetadata();
        }

        if ($metadata->decorative) {
            return '';
        }

        $libraryAlt = trim((string) $metadata->alt);

        return $libraryAlt !== '' ? $libraryAlt : null;
    }
}
