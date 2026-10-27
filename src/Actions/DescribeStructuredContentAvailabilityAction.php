<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
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

        if ($item->published_at !== null && $item->published_at->isFuture()) {
            return __('capell-structured-content-library::admin.scheduled', [
                'time' => $item->published_at->setTimezone(FilamentTimezone::get())->format('j M Y H:i:s T'),
            ]);
        }

        return __('capell-structured-content-library::admin.available');
    }
}
