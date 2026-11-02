<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

final class MergeStructuredContentReorderAction
{
    use AsFake;
    use AsObject;

    /**
     * Reassign only the submitted records' existing positions. Hidden records
     * keep their exact sort values, including records in other site scopes.
     *
     * @param  Builder<StructuredContentItem>  $reorderable
     * @param  array<int|string>  $submittedOrder
     * @return list<int|string>
     */
    public function handle(Builder $reorderable, array $submittedOrder): array
    {
        $order = array_values($submittedOrder);
        if (count(array_unique(array_map(strval(...), $order))) !== count($order)) {
            return [];
        }

        return DB::transaction(function () use ($reorderable, $order): array {
            $items = $reorderable->clone()->whereNull('deleted_at')->whereKey($order)
                ->reorder()->ordered()->lockForUpdate()->get();
            if ($items->count() !== count($order)) {
                return [];
            }

            $positions = $this->distinctPositions(array_values($items->map(static fn (StructuredContentItem $item): int => (int) $item->sort_order)->all()));
            foreach ($order as $index => $key) {
                $reorderable->clone()->whereNull('deleted_at')->whereKey($key)->update(['sort_order' => $positions[$index]]);
            }

            return $order;
        });
    }

    /**
     * Existing positions can contain duplicates, which would make the requested
     * order unrepresentable. Keep the ascending positions but make each one
     * strictly greater than the last within the affected set.
     *
     * @param  list<int>  $positions
     * @return list<int>
     */
    private function distinctPositions(array $positions): array
    {
        sort($positions);
        $previous = null;

        return array_map(static function (int $position) use (&$previous): int {
            $previous = $previous === null ? $position : max($position, $previous + 1);

            return $previous;
        }, $positions);
    }
}
