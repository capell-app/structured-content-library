<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

/**
 * Public-safe description of a record's picture: a plain public URL, its intrinsic size when
 * known, and alt text. An empty alt marks a decorative picture; a null alt means none was given.
 */
#[MapName(SnakeCaseMapper::class)]
final class PublicStructuredContentMediaData extends Data
{
    public function __construct(
        public readonly string $url,
        public readonly ?int $width = null,
        public readonly ?int $height = null,
        public readonly ?string $alt = null,
    ) {}
}
