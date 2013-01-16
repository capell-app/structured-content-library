<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\PublicStructuredContentItemData;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Lorisleiva\Actions\Concerns\AsObject;

/**
 * Projects an already selected record; callers own visibility and authorisation.
 *
 * @method static PublicStructuredContentItemData run(StructuredContentItem $item)
 */
final class BuildPublicStructuredContentItemDataAction
{
    use AsObject;

    public function handle(StructuredContentItem $item): PublicStructuredContentItemData
    {
        return new PublicStructuredContentItemData(
            type: $item->type,
            title: $item->title,
            slug: $item->slug,
            summary: $item->summary,
            content: $item->content,
            payload: BuildPublicStructuredContentPayloadAction::run($item->payload, $item->type),
        );
    }
}
