<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Capell\StructuredContentLibrary\Actions\EnsurePortableContentHtmlAction;
use DOMElement;
use Override;
use Tiptap\Core\Extension;

final class PortableListSemanticsExtension extends Extension
{
    /** @var string */
    public static $name = 'portableListSemantics';

    /** @return array<string, string>|null */
    public static function parseAttributes(DOMElement $element, bool $innerCode = false): ?array
    {
        $attributes = [];
        foreach ($element->attributes as $attribute) {
            if (! EnsurePortableContentHtmlAction::isPortableAttributeName($attribute->name)) {
                continue;
            }

            // Preserve values outside the native schema as raw HTML attributes, with
            // a null native value. Native controls can then replace them when edited.
            $native = match ($attribute->name) {
                'dir' => in_array(strtolower($attribute->value), ['', 'ltr', 'rtl', 'auto'], true),
                'lang' => true,
                'href', 'target', 'rel' => $element->tagName === 'a',
                'start' => $element->tagName === 'ol' && self::parseInteger($element, 'start') !== null,
                'value' => $element->tagName === 'li' && self::parseInteger($element, 'value') !== null,
                'type' => $element->tagName === 'ol' && in_array($attribute->value, ['1', 'a', 'A', 'i', 'I'], true),
                'reversed' => $element->tagName === 'ol',
                default => false,
            };
            if (! $innerCode && $native) {
                continue;
            }

            if ($attribute->name !== 'dir' || $attribute->value !== '') {
                $attributes[$attribute->name] = $attribute->value;
            }
        }

        return $attributes === [] ? null : $attributes;
    }

    public static function parseInteger(DOMElement $element, string $name): ?int
    {
        $value = $element->getAttribute($name);
        $integer = filter_var($value, FILTER_VALIDATE_INT);

        return is_int($integer) && abs($integer) <= 9007199254740991 && (string) $integer === $value ? $integer : null;
    }

    /** @return list<array{types: list<string>, attributes: array<string, array<string, mixed>>}> */
    #[Override]
    public function addGlobalAttributes(): array
    {
        return [
            [
                'types' => ['paragraph', 'heading', 'blockquote', 'bulletList', 'orderedList', 'listItem', 'codeBlock', 'hardBreak', 'emptyInline', 'nestedInline', 'bold', 'italic', 'code', 'link'],
                'attributes' => [
                    'dir' => [
                        'default' => null,
                        'parseHTML' => static fn (DOMElement $element): ?string => in_array(strtolower($element->getAttribute('dir')), ['ltr', 'rtl', 'auto'], true) ? $element->getAttribute('dir') : null,
                        'renderHTML' => static fn (object $attributes): array => isset($attributes->dir) ? ['dir' => $attributes->dir] : [],
                    ],
                    'lang' => [
                        'default' => null,
                        'parseHTML' => static fn (DOMElement $element): ?string => $element->hasAttribute('lang') ? $element->getAttribute('lang') : null,
                        'renderHTML' => static fn (object $attributes): array => isset($attributes->lang) ? ['lang' => $attributes->lang] : [],
                    ],
                    'portableAttributes' => [
                        'default' => null,
                        'parseHTML' => self::parseAttributes(...),
                        'renderHTML' => static fn (object $attributes): array => (array) ($attributes->portableAttributes ?? []),
                    ],
                ],
            ],
            [
                'types' => ['orderedList'],
                'attributes' => [
                    'reversed' => [
                        'default' => false,
                        'parseHTML' => static fn (DOMElement $element): bool => $element->hasAttribute('reversed'),
                        'renderHTML' => static fn (object $attributes): array => ($attributes->reversed ?? false) ? ['reversed' => 'reversed'] : [],
                    ],
                    'type' => [
                        'default' => null,
                        'parseHTML' => static fn (DOMElement $element): ?string => in_array($element->getAttribute('type'), ['1', 'a', 'A', 'i', 'I'], true) ? $element->getAttribute('type') : null,
                        'renderHTML' => static fn (object $attributes): array => isset($attributes->type) ? ['type' => $attributes->type] : [],
                    ],
                ],
            ],
            [
                'types' => ['listItem'],
                'attributes' => [
                    'value' => [
                        'default' => null,
                        'parseHTML' => static fn (DOMElement $element): ?int => self::parseInteger($element, 'value'),
                        'renderHTML' => static fn (object $attributes): array => isset($attributes->value) ? ['value' => (string) $attributes->value] : [],
                    ],
                ],
            ],
        ];
    }
}
