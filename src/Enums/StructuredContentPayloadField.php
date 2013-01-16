<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Enums;

use Closure;

enum StructuredContentPayloadField: string
{
    case Eyebrow = 'eyebrow';
    case Subtitle = 'subtitle';
    case Quote = 'quote';
    case Attribution = 'attribution';
    case Role = 'role';
    case Company = 'company';
    case Question = 'question';
    case Answer = 'answer';
    case ResourceKind = 'resource_kind';
    case Url = 'url';
    case Email = 'email';
    case Phone = 'phone';
    case StreetAddress = 'street_address';
    case Locality = 'locality';
    case Region = 'region';
    case PostalCode = 'postal_code';
    case CountryCode = 'country_code';
    case ImageAlt = 'image_alt';
    case LogoAlt = 'logo_alt';

    public function label(): string
    {
        return __('capell-structured-content-library::admin.payload_' . $this->value);
    }

    public function isRequired(): bool
    {
        // All payload fields remain optional: title-only drafts are valid for every type.
        return false;
    }

    public function isMultiline(): bool
    {
        return in_array($this, [self::Quote, self::Answer], true);
    }

    /** @return list<string|Closure> */
    public function rules(): array
    {
        $rules = [$this->isRequired() ? 'required' : 'nullable', 'string'];

        if ($this === self::Email) {
            $rules[] = 'email';
        }

        if ($this === self::Url) {
            $rules[] = function (string $attribute, mixed $value, Closure $fail): void {
                if (is_string($value) && $this->publicUrl($value) === null) {
                    $fail(__('capell-structured-content-library::validation.url'));
                }
            };
        }

        return $rules;
    }

    public function publicValue(string $value): ?string
    {
        return match ($this) {
            self::Url => $this->publicUrl($value),
            self::Email => $this->publicEmail($value),
            default => $this->plainText($value),
        };
    }

    private function plainText(string $value): ?string
    {
        $decodedValue = html_entity_decode($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $withoutDangerousBlocks = preg_replace(
            '/<\s*(script|style|iframe|object|embed)[^>]*>.*?<\s*\/\s*\1\s*>/is',
            '',
            $decodedValue,
        ) ?? $decodedValue;

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
