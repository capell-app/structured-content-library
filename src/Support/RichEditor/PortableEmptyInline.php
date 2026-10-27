<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Capell\Core\Support\Security\PublicUrlSanitizer;
use DOMElement;
use Override;
use stdClass;
use Tiptap\Core\Node;

/** Marks cannot represent empty elements, including named link destinations. */
class PortableEmptyInline extends Node
{
    /** @var string */
    public static $name = 'emptyInline';

    /** @return array<string, array<string, mixed>> */
    #[Override]
    public function addAttributes(): array
    {
        return [
            'tag' => [
                'default' => 'a',
                'rendered' => false,
                'parseHTML' => static fn (DOMElement $element): string => $element->tagName,
            ],
            'href' => [
                'default' => null,
                'parseHTML' => static fn (DOMElement $element): ?string => PublicUrlSanitizer::sanitize($element->getAttribute('href')),
            ],
            'target' => ['default' => null],
            'rel' => ['default' => null],
        ];
    }

    /** @return list<array{tag: string, getAttrs: callable}> */
    #[Override]
    public function parseHTML(): array
    {
        return array_map(static fn (string $tag): array => [
            'tag' => $tag,
            'getAttrs' => static fn (DOMElement $element): ?bool => $element->hasChildNodes() ? false : null,
        ], ['a', 'strong', 'em', 'code']);
    }

    /**
     * @param  stdClass  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    #[Override]
    public function renderHTML(mixed $node, $HTMLAttributes = []): array
    {
        return [$node->attrs->tag ?? 'a', $HTMLAttributes];
    }
}
