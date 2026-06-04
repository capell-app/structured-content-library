<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Lorisleiva\Actions\Concerns\AsObject;

final class BuildPublicStructuredContentPayloadAction
{
    use AsObject;

    /**
     * @return array<string, string>
     */
    public function handle(?StructuredContentPayloadData $payload): array
    {
        if (! $payload instanceof StructuredContentPayloadData) {
            return [];
        }

        $publicPayload = [];

        foreach ($payload->toArray() as $field => $value) {
            if (! is_string($value)) {
                continue;
            }

            $publicValue = match ($field) {
                'url' => $this->publicUrl($value),
                'email' => $this->publicEmail($value),
                default => $this->plainText($value),
            };

            if ($publicValue !== null) {
                $publicPayload[$field] = $publicValue;
            }
        }

        return $publicPayload;
    }

    private function plainText(string $value): ?string
    {
        $withoutDangerousBlocks = preg_replace(
            '/<\s*(script|style|iframe|object|embed)[^>]*>.*?<\s*\/\s*\1\s*>/is',
            '',
            $value,
        ) ?? $value;

        $plainText = trim(strip_tags($withoutDangerousBlocks));

        return $plainText !== '' ? $plainText : null;
    }

    private function publicEmail(string $value): ?string
    {
        $email = trim($value);

        if ($email === '') {
            return null;
        }

        return filter_var($email, FILTER_VALIDATE_EMAIL) !== false ? $email : null;
    }

    private function publicUrl(string $value): ?string
    {
        $url = trim($value);

        if ($url === '') {
            return null;
        }

        if (str_starts_with($url, '/') && ! str_starts_with($url, '//')) {
            return $url;
        }

        $scheme = parse_url($url, PHP_URL_SCHEME);

        if (! is_string($scheme) || ! in_array(strtolower($scheme), ['http', 'https'], true)) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) !== false ? $url : null;
    }
}
