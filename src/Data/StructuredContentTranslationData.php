<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * A record's wording in one language. Blank fields fall back to the record's own wording, so a
 * translator only fills in what reads differently; the type, site, picture, key and publishing
 * dates stay shared across languages.
 */
#[MapName(SnakeCaseMapper::class)]
final class StructuredContentTranslationData extends Data
{
    public function __construct(
        public readonly int $languageId,
        public readonly string $title,
        public readonly ?string $summary = null,
        public readonly ?string $content = null,
        public readonly ?StructuredContentPayloadData $payload = null,
    ) {}
}
