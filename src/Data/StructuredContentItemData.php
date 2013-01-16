<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Spatie\LaravelData\Attributes\MapName;
use Spatie\LaravelData\Attributes\WithCast;
use Spatie\LaravelData\Casts\DateTimeInterfaceCast;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\Mappers\SnakeCaseMapper;

#[MapName(SnakeCaseMapper::class)]
class StructuredContentItemData extends Data
{
    public function __construct(
        public readonly StructuredContentType $type,
        public readonly string $title,
        public readonly ?int $siteId = null,
        public readonly StructuredContentStatus $status = StructuredContentStatus::Draft,
        public readonly ?string $slug = null,
        public readonly ?string $summary = null,
        public readonly ?string $content = null,
        public readonly ?StructuredContentPayloadData $payload = null,
        #[WithCast(DateTimeInterfaceCast::class, format: [DATE_ATOM, 'Y-m-d H:i:s'], type: CarbonImmutable::class)]
        public readonly ?CarbonInterface $publishedAt = null,
        public readonly int $sortOrder = 0,
    ) {}
}
