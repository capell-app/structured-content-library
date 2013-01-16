<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures\Filament;

use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Filament\Panel;
use Filament\PanelProvider;
use Override;

final class StructuredContentTestPanelProvider extends PanelProvider
{
    #[Override]
    public function panel(Panel $panel): Panel
    {
        return $panel->id('scl-test')->path('scl-test')->default()
            ->resources([StructuredContentItemResource::class]);
    }
}
