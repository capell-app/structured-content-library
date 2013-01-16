<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Carbon\CarbonInterface;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * @method static StructuredContentItem run(StructuredContentItem $item, StructuredContentItemData $data)
 */
class UpdateStructuredContentItemAction
{
    use AsFake;
    use AsObject;

    private const int MaxUniqueSlugAttempts = 5;

    public function handle(StructuredContentItem $item, StructuredContentItemData $data): StructuredContentItem
    {
        AuthorizeStructuredContentMutationAction::run($item, $data->siteId);
        $title = trim($data->title);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => __('capell-structured-content-library::validation.title_required'),
            ]);
        }

        ValidateStructuredContentPayloadAction::run($data->type, $data->payload);

        $content = EnsurePortableContentHtmlAction::run($data->content);
        $summary = EnsurePortableContentHtmlAction::run($data->summary, 'summary');
        $slugSource = $data->slug !== null && trim($data->slug) !== ''
            ? $data->slug
            : $title;

        for ($attempt = 1; $attempt <= self::MaxUniqueSlugAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($item, $data, $title, $slugSource, $summary, $content): StructuredContentItem {
                    $item = StructuredContentItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();
                    AuthorizeStructuredContentMutationAction::run($item, $data->siteId);

                    if ($item->type !== $data->type) {
                        throw ValidationException::withMessages([
                            'type' => __('capell-structured-content-library::validation.type_locked'),
                        ]);
                    }

                    $slug = ResolveUniqueStructuredContentSlugAction::run($data->type, $data->siteId, $slugSource, $item);

                    $item->update([
                        'site_id' => $data->siteId,
                        'site_scope_key' => StructuredContentItem::scopeKeyForSiteId($data->siteId),
                        'type' => $data->type,
                        'status' => $data->status,
                        'title' => $title,
                        'slug' => $slug,
                        'summary' => $summary !== '' ? $summary : null,
                        'content' => $content,
                        'payload' => $data->payload,
                        'published_at' => $this->resolvePublishedAt($item, $data),
                        'sort_order' => max(0, $data->sortOrder),
                    ]);

                    return $item->refresh();
                }, attempts: 5);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::MaxUniqueSlugAttempts) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('The structured content slug retry loop unexpectedly completed.');
    }

    private function resolvePublishedAt(StructuredContentItem $item, StructuredContentItemData $data): ?CarbonInterface
    {
        if ($data->publishedAt instanceof CarbonInterface) {
            return $data->publishedAt;
        }

        if ($data->status !== StructuredContentStatus::Published) {
            return $item->published_at;
        }

        if ($item->status !== StructuredContentStatus::Published) {
            return now();
        }

        return $item->published_at;
    }
}
