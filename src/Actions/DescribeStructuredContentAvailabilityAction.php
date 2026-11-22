<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Carbon\CarbonInterface;
use Filament\Support\Facades\FilamentTimezone;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/** @method static string run(StructuredContentItem $item) */
final class DescribeStructuredContentAvailabilityAction
{
    use AsFake;
    use AsObject;

    public function handle(StructuredContentItem $item): string
    {
        if ($item->status !== StructuredContentStatus::Published) {
            return $item->status->getLabel();
        }

        if ($item->expires_at !== null && ! $item->expires_at->isFuture()) {
            return __('capell-structured-content-library::admin.expired', ['time' => $this->format($item->expires_at)]);
        }

        if ($item->published_at !== null && $item->published_at->isFuture()) {
            return $item->expires_at === null
                ? __('capell-structured-content-library::admin.scheduled', ['time' => $this->format($item->published_at)])
                : __('capell-structured-content-library::admin.scheduled_until', [
                    'time' => $this->format($item->published_at),
                    'until' => $this->format($item->expires_at),
                ]);
        }

        if ($item->expires_at !== null) {
            return __('capell-structured-content-library::admin.available_until', ['time' => $this->format($item->expires_at)]);
        }

        return __('capell-structured-content-library::admin.available');
    }

    private function format(CarbonInterface $moment): string
    {
        return $moment->setTimezone(FilamentTimezone::get())->settings(['locale' => app()->getLocale()])->translatedFormat('j M Y H:i:s T');
    }
}
