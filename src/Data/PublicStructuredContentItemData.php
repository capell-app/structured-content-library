<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class PublicStructuredContentItemData extends Data
{
    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public readonly StructuredContentType $type,
        public readonly string $title,
        public readonly ?string $slug = null,
        public readonly ?string $summary = null,
        public readonly ?string $content = null,
        public readonly array $payload = [],
    ) {}
}
