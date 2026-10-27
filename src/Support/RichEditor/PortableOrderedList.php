<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use DOMElement;
use Override;
use Tiptap\Nodes\OrderedList;

/** TipTap's default PHP parser treats the valid start="0" as an absent start. */
final class PortableOrderedList extends OrderedList
{
    /** @return array<string, array<string, mixed>> */
    #[Override]
    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),
            // The PHP serializer treats integer zero as its content-hole marker.
            'start' => [
                'parseHTML' => static fn (DOMElement $element): ?int => PortableListSemanticsExtension::parseInteger($element, 'start'),
                'renderHTML' => static fn (object $attributes): array => isset($attributes->start) ? ['start' => (string) $attributes->start] : [],
            ],
        ];
    }
}
