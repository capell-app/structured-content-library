<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Support\RichEditor;

use Filament\Forms\Components\RichEditor\StateCasts\RichEditorStateCast;
use Illuminate\Contracts\Support\Htmlable;
use Override;
use ReflectionClass;
use stdClass;
use Tiptap\Core\DOMParser;
use Tiptap\Editor;

final class PortableRichEditorStateCast extends RichEditorStateCast
{
    /** @return array<string, mixed> */
    #[Override]
    public function set(mixed $state): array
    {
        if ($state instanceof Htmlable) {
            $state = $state->toHtml();
        }

        if (is_string($state)) {
            $document = json_validate($state) ? json_decode($state, true, flags: JSON_THROW_ON_ERROR) : null;
            $state = is_array($document) && ($document['type'] ?? null) === 'doc'
                ? $document
                : new DOMParser($this->richEditor->getTipTapEditor()->schema)->process($state);
        }

        $document = parent::set($state);

        // Filament 5.10.3 removed the parent's normalizeListItemContent() hook, so nothing calls
        // the override below any more; run it explicitly there. Earlier versions call the hook.
        if (! new ReflectionClass(parent::class)->hasMethod('normalizeListItemContent')) {
            $editor = $this->richEditor->getTipTapEditor()->setContent($document);
            $this->normalizeListItemContent($editor);
            $document = $editor->getDocument();
        }

        return $document;
    }

    /** @return string|array<string, mixed> */
    #[Override]
    public function get(mixed $state): string|array
    {
        if (is_string($state)) {
            $document = json_validate($state) ? json_decode($state, true, flags: JSON_THROW_ON_ERROR) : null;
            // Submitted editor documents retain Filament's JSON support; other strings are HTML/text.
            if (! is_array($document) || ($document['type'] ?? null) !== 'doc') {
                $state = new DOMParser($this->richEditor->getTipTapEditor()->schema)->process($state);
            }
        }

        return parent::get($state);
    }

    // No #[Override]: the parent hook exists up to Filament 5.10.2 and was removed in 5.10.3,
    // and the attribute fatals at class load when the parent method is absent.
    protected function normalizeListItemContent(Editor $editor): void
    {
        $editor->descendants(function (stdClass &$node): void {
            if ($node->type === 'nestedInline') {
                PortableNestedInline::removeInheritedMarks($node);
            }

            if (! in_array($node->type, ['doc', 'blockquote', 'listItem'], true) || ! isset($node->content)) {
                return;
            }

            $content = [];
            $inline = [];
            foreach ($node->content as $child) {
                if (in_array($child->type, ['text', 'hardBreak', 'emptyInline', 'nestedInline'], true)) {
                    $inline[] = $child;

                    continue;
                }

                if ($inline !== []) {
                    $content[] = (object) ['type' => 'paragraph', 'content' => $inline];
                    $inline = [];
                }

                $content[] = $child;
            }

            if ($inline !== []) {
                $content[] = (object) ['type' => 'paragraph', 'content' => $inline];
            }

            // TipTap list items require a leading paragraph even when legacy HTML starts with a block.
            if ($node->type === 'listItem' && ($content[0]->type ?? null) !== 'paragraph') {
                array_unshift($content, (object) ['type' => 'paragraph', 'content' => []]);
            }

            $node->content = $content;
        });
    }
}
