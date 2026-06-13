<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StructuredContentPayloadData extends Data
{
    public function __construct(
        public readonly ?string $eyebrow = null,
        public readonly ?string $subtitle = null,
        public readonly ?string $quote = null,
        public readonly ?string $attribution = null,
        public readonly ?string $role = null,
        public readonly ?string $company = null,
        public readonly ?string $question = null,
        public readonly ?string $answer = null,
        public readonly ?string $resourceKind = null,
        public readonly ?string $url = null,
        public readonly ?string $email = null,
        public readonly ?string $phone = null,
        public readonly ?string $streetAddress = null,
        public readonly ?string $locality = null,
        public readonly ?string $region = null,
        public readonly ?string $postalCode = null,
        public readonly ?string $countryCode = null,
        public readonly ?string $imageAlt = null,
        public readonly ?string $logoAlt = null,
    ) {}
}
