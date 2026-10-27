<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Dom\HTMLDocument;
use Override;
use stdClass;
use Tiptap\Editor;

/** Adapt TipTap's serializer, which otherwise drops the meaningful empty HTML attributes. */
final class PortableTipTapEditor extends Editor
{
    #[Override]
    public function getHTML(): string
    {
        $this->descendants(static function (stdClass $node): void {
            if ($node->type === 'nestedInline') {
                PortableNestedInline::removeInheritedMarks($node);
            }
        });

        /** @var array<string, mixed> $document */
        $document = $this->getDocument();
        $token = 'capell-empty-attribute-' . bin2hex(random_bytes(16));

        if (! $this->markEmptyAttributes($document, $token)) {
            return parent::getHTML();
        }

        // Use a temporary document so browser state keeps the actual empty attribute values.
        $html = new Editor($this->configuration)->setContent($document)->getHTML();
        $dom = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        foreach ($dom->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if ($attribute->value === $token) {
                    $element->setAttribute($attribute->name, '');
                }
            }
        }

        $html = '';
        foreach ($dom->body->childNodes ?? [] as $node) {
            $html .= $dom->saveHtml($node);
        }

        return $html;
    }

    /** @param array<array-key, mixed> $node */
    private function markEmptyAttributes(array &$node, string $token): bool
    {
        $marked = false;
        if (is_array($node['attrs'] ?? null)) {
            foreach ($node['attrs'] as &$value) {
                if ($value === '') {
                    $value = $token;
                    $marked = true;
                } elseif (is_array($value)) {
                    foreach ($value as &$attribute) {
                        if ($attribute === '') {
                            $attribute = $token;
                            $marked = true;
                        }
                    }

                    unset($attribute);
                }
            }

            unset($value);
        }

        foreach (['content', 'marks'] as $key) {
            if (! is_array($node[$key] ?? null)) {
                continue;
            }

            foreach ($node[$key] as &$child) {
                if (is_array($child)) {
                    $marked = $this->markEmptyAttributes($child, $token) || $marked;
                }
            }

            unset($child);
        }

        return $marked;
    }
}
