<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Enums;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;

enum ResourceEnum: string
{
    case StructuredContentItem = StructuredContentItemResource::class;
}
