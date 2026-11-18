<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Support\StructuredContentRevisions;
use Carbon\CarbonInterface;
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
 * Saving a published record adds a revision to its history when Publishing Studio is installed;
 * a restore records its own revision instead and passes $recordRevision false.
 *
 * @method static StructuredContentItem run(StructuredContentItem $item, StructuredContentItemData $data, bool $authorize = true, bool $recordRevision = true)
 */
class UpdateStructuredContentItemAction
{
    use AsFake;
    use AsObject;

    private const int MaxUniqueSlugAttempts = 5;

    public function handle(StructuredContentItem $item, StructuredContentItemData $data, bool $authorize = true, bool $recordRevision = true): StructuredContentItem
    {
        if ($authorize) {
            AuthorizeStructuredContentMutationAction::run($item, $data->siteId);
        }

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
                return DB::transaction(function () use ($item, $data, $title, $slugSource, $summary, $content, $authorize, $recordRevision): StructuredContentItem {
                    $item = StructuredContentItem::query()->whereKey($item->getKey())->lockForUpdate()->firstOrFail();

                    if ($authorize) {
                        AuthorizeStructuredContentMutationAction::run($item, $data->siteId);
                    }

                    if ($item->type !== $data->type) {
                        throw ValidationException::withMessages([
                            'type' => __('capell-structured-content-library::validation.type_locked'),
                        ]);
                    }

                    $before = BuildStructuredContentRevisionSnapshotAction::run($item);
                    $slug = ResolveUniqueStructuredContentSlugAction::run($data->type, $data->siteId, $slugSource, $item);
                    $publishedAt = $this->resolvePublishedAt($item, $data);
                    ValidateStructuredContentScheduleAction::run($publishedAt, $data->expiresAt);

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
                        'published_at' => $publishedAt,
                        'expires_at' => $data->expiresAt,
                        'sort_order' => max(0, $data->sortOrder),
                    ]);

                    if ($data->translations !== null) {
                        SyncStructuredContentTranslationsAction::run($item, $data->translations);
                    }

                    $item->refresh();
                    $after = BuildStructuredContentRevisionSnapshotAction::run($item);

                    if ($recordRevision && $item->status === StructuredContentStatus::Published && $after !== $before) {
                        StructuredContentRevisions::recordPublished($item, $before, $after, auth()->user());
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
