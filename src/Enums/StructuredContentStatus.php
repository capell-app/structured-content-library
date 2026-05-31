<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Enums;

use Filament\Support\Contracts\HasLabel;

enum StructuredContentStatus: string implements HasLabel
{
    case Draft = 'draft';
    case Published = 'published';
    case Archived = 'archived';

    public function getLabel(): string
    {
        return __('capell-structured-content-library::status.' . $this->value);
    }

    public function isPubliclyVisible(): bool
    {
        return $this === self::Published;
    }
}
