<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
final class StructuredContentSectionData extends Data
{
    /**
     * @param  list<PublicStructuredContentItemData>  $items
     */
    public function __construct(
        public readonly string $key,
        public readonly StructuredContentType $type,
        public readonly string $label,
        public readonly array $items,
    ) {}
}
