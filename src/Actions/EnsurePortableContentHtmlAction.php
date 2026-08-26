<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Core\Support\Security\PublicUrlSanitizer;
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

        if (preg_match('/<[^>]+\\s(?:class|style|id|data-[a-z0-9_-]+|wire:|x-|on[a-z]+)\\s*=/i', $trimmedContent) === 1) {
            $this->throwPortableContentException($field);
        }

        return $this->stripUnsafeHrefAttributes($trimmedContent, $field);
    }

    /**
     * The tag allow-list above lets `<a>` through, but neither strip_tags()
     * nor the attribute-blocking regex inspects the *value* of a surviving
     * `href`. Without this pass, `<a href="javascript:...">` is portable
     * content as far as this action is concerned, and is later rendered raw
     * via PublicStructuredContentItemData->content. Reuse the platform's
     * scheme allow-list and drop the whole attribute (not just neutralise
     * it) when the value fails it.
     */
    private function stripUnsafeHrefAttributes(string $content, string $field): string
    {
        $sanitisedContent = preg_replace_callback(
            '/\\s+href\\s*=\\s*("[^"]*"|\'[^\']*\'|[^\\s>]+)/i',
            static function (array $matches): string {
                $rawValue = $matches[1];

                if ($rawValue !== '' && ($rawValue[0] === '"' || $rawValue[0] === '\'')) {
                    $rawValue = substr($rawValue, 1, -1);
                }

                $decodedValue = html_entity_decode($rawValue, ENT_QUOTES | ENT_HTML5);

                return PublicUrlSanitizer::sanitize($decodedValue) === null ? '' : $matches[0];
            },
            $content,
        );

        if ($sanitisedContent === null) {
            $this->throwPortableContentException($field);
        }

        return $sanitisedContent;
    }

    private function throwPortableContentException(string $field): never
    {
        throw ValidationException::withMessages([
            $field => __('capell-structured-content-library::validation.portable_content'),
        ]);
    }
}
