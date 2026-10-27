<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Support\Security\PublicUrlSanitizer;
use Dom\HTMLDocument;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

class EnsurePortableContentHtmlAction
{
    use AsFake;
    use AsObject;

    /** @var list<string> */
    private const array ALLOWED_TAGS = [
        'a',
        'blockquote',
        'br',
        'code',
        'em',
        'h2',
        'h3',
        'h4',
        'li',
        'ol',
        'p',
        'pre',
        'strong',
        'ul',
    ];

    public static function isPortableAttributeName(string $name): bool
    {
        return preg_match('/^[^\s"\'<>\/=\x00-\x1f]+$/u', $name) === 1
            && preg_match('/^(?:class|style|id|data-.*|wire:.*|x-.*|on[a-z]+|[@:].*)$/i', $name) !== 1;
    }

    public function handle(?string $content, string $field = 'content'): ?string
    {
        if ($content === null) {
            return null;
        }

        $trimmedContent = trim($content);

        if ($trimmedContent === '') {
            return null;
        }

        $allowedTags = collect(self::ALLOWED_TAGS)
            ->map(fn (string $tag): string => '<' . $tag . '>')
            ->implode('');

        if (strip_tags($trimmedContent, $allowedTags) !== $trimmedContent) {
            $this->throwPortableContentException($field);
        }

        // Parse attributes: a tag regex can stop at a quoted ">" and miss later attributes.
        $dom = HTMLDocument::createFromString($trimmedContent, LIBXML_NOERROR, 'UTF-8');
        $hasUnsafeHref = false;
        foreach ($dom->getElementsByTagName('*') as $element) {
            foreach ($element->attributes as $attribute) {
                if (! self::isPortableAttributeName($attribute->name)) {
                    $this->throwPortableContentException($field);
                }
            }

            if ($element->hasAttribute('href') && PublicUrlSanitizer::sanitize($element->getAttribute('href')) === null) {
                $element->removeAttribute('href');
                $hasUnsafeHref = true;
            }
        }

        if (! $hasUnsafeHref) {
            return $trimmedContent;
        }

        // Serialise the same parsed attributes we inspected; regexes miss adjacent attributes.
        $html = '';
        foreach ($dom->body->childNodes ?? [] as $node) {
            $html .= $dom->saveHtml($node);
        }

        return $html;
    }

    private function throwPortableContentException(string $field): never
    {
        throw ValidationException::withMessages([
            $field => __('capell-structured-content-library::validation.portable_content'),
        ]);
    }
}
