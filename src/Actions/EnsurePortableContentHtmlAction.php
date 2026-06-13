<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class EnsurePortableContentHtmlAction
{
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

        return $trimmedContent;
    }

    private function throwPortableContentException(string $field): never
    {
        throw ValidationException::withMessages([
            $field => __('capell-structured-content-library::validation.portable_content'),
        ]);
    }
}
