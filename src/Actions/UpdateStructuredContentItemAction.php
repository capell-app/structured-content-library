<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Lorisleiva\Actions\Concerns\AsObject;

class UpdateStructuredContentItemAction
{
    use AsObject;

    public function handle(StructuredContentItem $item, StructuredContentItemData $data): StructuredContentItem
    {
        $title = trim($data->title);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => __('capell-structured-content-library::validation.title_required'),
            ]);
        }

        $content = EnsurePortableContentHtmlAction::run($data->content);
        $summary = EnsurePortableContentHtmlAction::run($data->summary, 'summary');
        $slugSource = $data->slug !== null && trim($data->slug) !== ''
            ? $data->slug
            : $title;
        $slug = ResolveUniqueStructuredContentSlugAction::run($data->type, $data->siteId, $slugSource, $item);

        return DB::transaction(function () use ($item, $data, $title, $slug, $summary, $content): StructuredContentItem {
            $item->update([
                'site_id' => $data->siteId,
                'type' => $data->type,
                'status' => $data->status,
                'title' => $title,
                'slug' => $slug,
                'summary' => $summary !== '' ? $summary : null,
                'content' => $content,
                'payload' => $data->payload,
                'published_at' => $data->status === StructuredContentStatus::Published
                    ? ($data->publishedAt ?? now())
                    : null,
                'sort_order' => max(0, $data->sortOrder),
            ]);

            return $item->refresh();
        });
    }
}
