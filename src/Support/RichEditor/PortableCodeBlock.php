<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Override;
use stdClass;
use Tiptap\Nodes\CodeBlock;

/** The inner code element has semantics independent of its pre container. */
final class PortableCodeBlock extends CodeBlock
{
    /** @var string */
    public static $marks = '_';

    /** @return array<string, array<string, mixed>> */
    #[Override]
    public function addAttributes(): array
    {
        return [
            ...parent::addAttributes(),
            'codeAttributes' => [
                'default' => null,
                'rendered' => false,
            ],
        ];
    }

    /**
     * @param  stdClass  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    #[Override]
    public function renderHTML(mixed $node, $HTMLAttributes = []): array
    {
        // Older in-flight documents may still carry the separate code-attribute map.
        return isset($node->attrs->codeAttributes)
            ? ['pre', $HTMLAttributes, ['code', (array) $node->attrs->codeAttributes, 0]]
            : ['pre', $HTMLAttributes, 0];
    }
}
