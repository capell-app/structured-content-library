<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use DOMElement;
use Illuminate\Support\Arr;
use Override;
use stdClass;

/** Retain repeated formatting elements that cannot share one mark-set entry. */
final class PortableNestedInline extends PortableEmptyInline
{
    /** @var string */
    public static $name = 'nestedInline';

    /** PHP's parser repeats surrounding marks on an inline node and its descendants. */
    public static function removeInheritedMarks(stdClass $node): void
    {
        foreach ($node->content ?? [] as $child) {
            if (! $child instanceof stdClass) {
                continue;
            }

            if (isset($child->marks)) {
                $child->marks = array_values(array_filter($child->marks, static fn (stdClass $mark): bool => ! array_any(
                    $node->marks ?? [],
                    static fn (stdClass $inherited): bool => self::markKey($mark) === self::markKey($inherited),
                )));
            }

            // Carry the surrounding set down without removing distinct formatting.
            $descendant = clone $child;
            $descendant->marks = $node->marks ?? [];
            self::removeInheritedMarks($descendant);
        }
    }

    /** @return list<array{tag: string, getAttrs: callable}> */
    #[Override]
    public function parseHTML(): array
    {
        return array_map(static fn (string $tag): array => [
            'tag' => $tag,
            'getAttrs' => static function (DOMElement $element): ?bool {
                $parent = $element->parentNode;
                while ($parent instanceof DOMElement) {
                    if ($parent->tagName === $element->tagName) {
                        return null;
                    }

                    $parent = $parent->parentNode;
                }

                return false;
            },
        ], ['strong', 'em', 'code']);
    }

    /**
     * @param  stdClass  $node
     * @param  array<string, mixed>  $HTMLAttributes
     * @return array<mixed>
     */
    #[Override]
    public function renderHTML(mixed $node, $HTMLAttributes = []): array
    {
        return [...parent::renderHTML($node, $HTMLAttributes), 0];
    }

    private static function markKey(stdClass $mark): string
    {
        /** @var array<string, mixed> $data */
        $data = json_decode(json_encode($mark, JSON_THROW_ON_ERROR), true, flags: JSON_THROW_ON_ERROR);

        return json_encode(Arr::sortRecursive($data), JSON_THROW_ON_ERROR);
    }
}
