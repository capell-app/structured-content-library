<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * A drag reorder is a bulk query update, so no model events fire. Refresh the
 * structured-content cache and the public output that depends on these items.
 *
 * @method static void run(array<int|string> $itemKeys)
 */
final class InvalidateStructuredContentOrderAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  array<int|string>  $itemKeys
     */
    public function handle(array $itemKeys): void
    {
        InvalidateStructuredContentPublicOutputAction::run(
            StructuredContentItem::query()->whereKey(array_values($itemKeys))->get()->all(),
        );
    }
}
