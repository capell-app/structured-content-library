<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Capell\Core\Support\Security\PublicUrlSanitizer;
use DOMElement;
use Override;
use Tiptap\Marks\Link;

/** Named and metadata-only anchors are accepted portable HTML too. */
final class PortableLink extends Link
{
    /** @return array<string, array<string, mixed>> */
    #[Override]
    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),
            'href' => [
                'parseHTML' => static fn (DOMElement $element): ?string => PublicUrlSanitizer::sanitize($element->getAttribute('href')),
            ],
        ];
    }

    /** @return list<array{tag: string}> */
    #[Override]
    public function parseHTML(): array
    {
        return [['tag' => 'a']];
    }
}
