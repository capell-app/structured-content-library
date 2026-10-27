<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** Validate the wire document before TipTap can render or discard unsupported input. */
final class EnsurePortableEditorDocumentAction
{
    use AsFake;
    use AsObject;

    public function handle(mixed $document): void
    {
        if ($document === null) {
            return;
        }

        if (is_string($document)) {
            if (! json_validate($document)) {
                return; // Legacy HTML is converted by the editor and checked separately.
            }

            $document = json_decode($document, true, flags: JSON_THROW_ON_ERROR);
        }

        $this->ensureNode($document, ['doc']);
    }

    /** @param list<string> $allowedTypes */
    private function ensureNode(mixed $node, array $allowedTypes): void
    {
        if (! is_array($node) || ! in_array($node['type'] ?? null, $allowedTypes, true)
            || array_diff(array_keys($node), ['type', 'attrs', 'content', 'text', 'marks']) !== []) {
            $this->reject();
        }

        $type = $node['type'];
        $attributes = $node['attrs'] ?? [];
        $allowedAttributes = match ($type) {
            'heading' => ['level', 'textAlign'],
            'paragraph' => ['textAlign'],
            'orderedList' => ['start', 'reversed', 'type'],
            'listItem' => ['value'],
            'codeBlock' => ['language', 'codeAttributes'],
            'emptyInline', 'nestedInline' => ['tag', 'href', 'target', 'rel'],
            'link' => ['href', 'target', 'rel', 'class'],
            default => [],
        };

        if (! in_array($type, ['doc', 'text'], true)) {
            $allowedAttributes = [...$allowedAttributes, 'dir', 'lang', 'portableAttributes'];
        }

        if (! is_array($attributes) || array_diff(array_keys($attributes), $allowedAttributes) !== []) {
            $this->reject();
        }

        foreach ($attributes as $name => $value) {
            $valid = match ($name) {
                'tag' => in_array($value, ['a', 'strong', 'em', 'code'], true),
                'level' => in_array($value, [2, 3, 4], true),
                'textAlign' => $value === null || $value === 'start',
                'start', 'value' => $value === null || is_int($value),
                'reversed' => $value === null || is_bool($value),
                'type' => $value === null || in_array($value, ['1', 'a', 'A', 'i', 'I'], true),
                'dir' => $value === null || (is_string($value) && in_array(strtolower($value), ['ltr', 'rtl', 'auto'], true)),
                'portableAttributes', 'codeAttributes' => $this->validPortableAttributes($value),
                'lang', 'href', 'target', 'rel' => $value === null || is_string($value),
                // Default browser attributes that must never carry presentation.
                'class', 'language' => $value === null,
                default => false,
            };
            if (! $valid) {
                $this->reject();
            }
        }

        if (($type === 'text' && ! is_string($node['text'] ?? null))
            || (isset($node['text']) && $type !== 'text')) {
            $this->reject();
        }

        $children = $node['content'] ?? [];
        $marks = $node['marks'] ?? [];
        if (! is_array($children) || ! array_is_list($children) || ! is_array($marks) || ! array_is_list($marks)) {
            $this->reject();
        }

        $allowedChildren = match ($type) {
            'doc', 'blockquote', 'listItem' => ['paragraph', 'heading', 'bulletList', 'orderedList', 'blockquote', 'codeBlock'],
            'bulletList', 'orderedList' => ['listItem'],
            'paragraph', 'heading', 'nestedInline' => ['text', 'hardBreak', 'emptyInline', 'nestedInline'],
            'codeBlock' => ['text', 'hardBreak', 'emptyInline', 'nestedInline'],
            default => [],
        };
        foreach ($children as $child) {
            $this->ensureNode($child, $allowedChildren);
        }

        foreach ($marks as $mark) {
            $this->ensureNode($mark, in_array($type, ['text', 'hardBreak', 'emptyInline', 'nestedInline'], true) ? ['bold', 'italic', 'code', 'link'] : []);
        }
    }

    private function validPortableAttributes(mixed $attributes): bool
    {
        if ($attributes === null) {
            return true;
        }

        if (! is_array($attributes)) {
            return false;
        }

        return array_all($attributes, static fn (mixed $value, int|string $name): bool => is_string($name)
            && EnsurePortableContentHtmlAction::isPortableAttributeName($name)
            && is_string($value));
    }

    private function reject(): never
    {
        throw ValidationException::withMessages([
            'content' => __('capell-structured-content-library::validation.portable_content'),
        ]);
    }
}
