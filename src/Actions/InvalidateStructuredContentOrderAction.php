<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Frontend\Support\Cache\CacheInvalidationRegistry;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentCache;
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
        StructuredContentCache::flush();

        if (! class_exists(CacheInvalidationRegistry::class) || ! app()->bound(CacheInvalidationRegistry::class)) {
            return;
        }

        $registry = resolve(CacheInvalidationRegistry::class);

        foreach (StructuredContentItem::query()->whereKey(array_values($itemKeys))->get() as $item) {
            $registry->invalidateChangedModel($item);
        }
    }
}
