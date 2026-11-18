<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentRevisions;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Signed-in authoring is authorised against the actor; a trusted system caller (an import run from
 * the console, queue or a seeder) passes $authorize false because there is no actor to check.
 *
 * @method static StructuredContentItem run(StructuredContentItemData $data, bool $authorize = true)
 */
class CreateStructuredContentItemAction
{
    use AsFake;
    use AsObject;

    private const int MaxUniqueSlugAttempts = 5;

    public function handle(StructuredContentItemData $data, bool $authorize = true): StructuredContentItem
    {
        if ($authorize) {
            AuthorizeStructuredContentMutationAction::run(null, $data->siteId);
        }

        $title = trim($data->title);

        if ($title === '') {
            throw ValidationException::withMessages([
                'title' => __('capell-structured-content-library::validation.title_required'),
            ]);
        }

        ValidateStructuredContentPayloadAction::run($data->type, $data->payload);
        ValidateStructuredContentScheduleAction::run($data->status === StructuredContentStatus::Published ? ($data->publishedAt ?? now()) : $data->publishedAt, $data->expiresAt);

        $content = EnsurePortableContentHtmlAction::run($data->content);
        $summary = EnsurePortableContentHtmlAction::run($data->summary, 'summary');
        $slugSource = $data->slug !== null && trim($data->slug) !== ''
            ? $data->slug
            : $title;
        for ($attempt = 1; $attempt <= self::MaxUniqueSlugAttempts; $attempt++) {
            try {
                return DB::transaction(function () use ($data, $title, $slugSource, $summary, $content): StructuredContentItem {
                    $slug = ResolveUniqueStructuredContentSlugAction::run($data->type, $data->siteId, $slugSource);

                    $item = StructuredContentItem::query()->create([
                        'site_id' => $data->siteId,
                        'site_scope_key' => StructuredContentItem::scopeKeyForSiteId($data->siteId),
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
                        'expires_at' => $data->expiresAt,
                        'sort_order' => max(0, $data->sortOrder),
                    ]);

                    if ($data->translations !== null) {
                        SyncStructuredContentTranslationsAction::run($item, $data->translations);
                    }

                    if ($item->status === StructuredContentStatus::Published) {
                        StructuredContentRevisions::recordPublished($item, null, BuildStructuredContentRevisionSnapshotAction::run($item), auth()->user());
                    }

                    return $item;
                }, attempts: 5);
            } catch (UniqueConstraintViolationException $exception) {
                if ($attempt === self::MaxUniqueSlugAttempts) {
                    throw $exception;
                }
            }
        }

        throw new LogicException('The structured content slug retry loop unexpectedly completed.');
    }
}
