<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\Admin\Support\SiteScope;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Gate;
use Lorisleiva\Actions\Concerns\AsFake;
use Lorisleiva\Actions\Concerns\AsObject;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;

final class AuthorizeStructuredContentMutationAction
{
    use AsFake;
    use AsObject;

    public function handle(?StructuredContentItem $item, ?int $targetSiteId): User
    {
        $actor = auth()->user();

        throw_unless($actor instanceof User, AccessDeniedHttpException::class, 'Structured content mutation requires an authenticated actor.');

        if ($item instanceof StructuredContentItem) {
            Gate::forUser($actor)->authorize('update', $item);
        } else {
            Gate::forUser($actor)->authorize('create', StructuredContentItem::class);
        }

        throw_unless($this->canUseTargetSite($actor, $targetSiteId), AccessDeniedHttpException::class, 'Structured content actor cannot mutate the target site scope.');

        return $actor;
    }

    private function canUseTargetSite(Authenticatable $actor, ?int $siteId): bool
    {
        if (SiteScope::isGlobalActor($actor)) {
            return true;
        }

        if ($siteId === null || ! method_exists($actor, 'getAssignedSiteIds')) {
            return false;
        }

        return $actor->getAssignedSiteIds()->contains($siteId);
    }
}
