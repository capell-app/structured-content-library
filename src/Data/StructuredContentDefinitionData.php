<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Capell\StructuredContentLibrary\Enums\StructuredContentPayloadField;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Spatie\LaravelData\Data;

final class StructuredContentDefinitionData extends Data
{
    /** @param list<StructuredContentPayloadField> $fields */
    public function __construct(
        public readonly StructuredContentType $type,
        public readonly array $fields,
    ) {}

    public static function forType(StructuredContentType $type): self
    {
        return new self($type, array_map(
            StructuredContentPayloadField::from(...),
            self::fieldNamesForType($type),
        ));
    }

    public function label(): string
    {
        return $this->type->getLabel();
    }

    public function description(): string
    {
        return __('capell-structured-content-library::admin.types.' . $this->type->value . '.description');
    }

    public function example(): string
    {
        return __('capell-structured-content-library::admin.types.' . $this->type->value . '.example');
    }

    public function groupLabel(): string
    {
        return __('capell-structured-content-library::admin.types.' . $this->type->value . '.group');
    }

    /** @return list<string> */
    public function fieldNames(): array
    {
        return array_map(static fn (StructuredContentPayloadField $field): string => $field->value, $this->fields);
    }

    /** @return list<string> */
    private static function fieldNamesForType(StructuredContentType $type): array
    {
        return match ($type) {
            StructuredContentType::CaseStudy => [
                'eyebrow',
                'subtitle',
                'company',
                'url',
                'image_alt',
            ],
            StructuredContentType::Testimonial => [
                'quote',
                'attribution',
                'role',
                'company',
                'image_alt',
            ],
            StructuredContentType::TeamMember => [
                'subtitle',
                'role',
                'company',
                'email',
                'phone',
                'url',
                'image_alt',
            ],
            StructuredContentType::Service => [
                'eyebrow',
                'subtitle',
                'url',
                'image_alt',
            ],
            StructuredContentType::Faq => [
                'question',
                'answer',
            ],
            StructuredContentType::Resource => [
                'resource_kind',
                'url',
                'image_alt',
            ],
            StructuredContentType::Partner => [
                'company',
                'url',
                'logo_alt',
            ],
            StructuredContentType::Location => [
                'email',
                'phone',
                'street_address',
                'locality',
                'region',
                'postal_code',
                'country_code',
                'url',
            ],
            StructuredContentType::Logo => [
                'company',
                'url',
                'logo_alt',
            ],
        };
    }
}
