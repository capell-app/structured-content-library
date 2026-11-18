<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentImportResultData;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Support\Str;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Imports run from the console, a queued job or a seeder with nobody signed in, so they write as a
 * trusted system operation. A signed-in actor, or any web request, is still authorised record by
 * record against the policy and site scope.
 *
 * @method static StructuredContentImportResultData run(iterable<int, array<string, mixed>|StructuredContentItemData> $items, bool $updateExisting = true)
 */
final class ImportStructuredContentItemsAction
{
    use AsFake;
    use AsObject;

    /**
     * @param  iterable<int, array<string, mixed>|StructuredContentItemData>  $items
     */
    public function handle(iterable $items, bool $updateExisting = true): StructuredContentImportResultData
    {
        $created = 0;
        $updated = 0;
        $skipped = 0;
        $authorize = auth()->user() !== null || ! app()->runningInConsole();

        foreach ($items as $item) {
            $data = $item instanceof StructuredContentItemData
                ? $item
                : StructuredContentItemData::from($item);

            $existing = $this->existingItem($data);

            if ($existing instanceof StructuredContentItem && ! $updateExisting) {
                $skipped++;

                continue;
            }

            if ($existing instanceof StructuredContentItem) {
                UpdateStructuredContentItemAction::run($existing, $data, $authorize);
                $updated++;

                continue;
            }

            CreateStructuredContentItemAction::run($data, $authorize);
            $created++;
        }

        return new StructuredContentImportResultData(
            created: $created,
            updated: $updated,
            skipped: $skipped,
        );
    }

    private function existingItem(StructuredContentItemData $data): ?StructuredContentItem
    {
        $slugSource = $data->slug !== null && trim($data->slug) !== ''
            ? $data->slug
            : $data->title;

        $slug = Str::slug($slugSource);

        if ($slug === '') {
            return null;
        }

        return StructuredContentItem::query()
            ->where('type', $data->type)
            ->where('site_id', $data->siteId)
            ->where('slug', $slug)
            ->first();
    }
}
