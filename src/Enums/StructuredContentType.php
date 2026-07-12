<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Enums;

use Filament\Support\Contracts\HasLabel;

enum StructuredContentType: string implements HasLabel
{
    case CaseStudy = 'case_study';
    case Testimonial = 'testimonial';
    case TeamMember = 'team_member';
    case Service = 'service';
    case Faq = 'faq';
    case Resource = 'resource';
    case Partner = 'partner';
    case Location = 'location';
    case Logo = 'logo';

    public function getLabel(): string
    {
        return __('capell-structured-content-library::type.' . $this->value);
    }
}
