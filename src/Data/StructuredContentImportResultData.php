<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Data;

use Spatie\LaravelData\Data;

final class StructuredContentImportResultData extends Data
{
    public function __construct(
        public readonly int $created,
        public readonly int $updated,
        public readonly int $skipped,
    ) {}
}
