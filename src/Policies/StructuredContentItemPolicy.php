<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Policies;

use Capell\Admin\Policies\Concerns\ResolvesShieldPermission;
use Capell\Admin\Support\SiteScope;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Foundation\Auth\User;
use Throwable;

final class StructuredContentItemPolicy
{
    use ResolvesShieldPermission;

    public function viewAny(User $user): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view']);
    }

    public function view(User $user, StructuredContentItem $record): bool
    {
        return $this->hasAnyPermission($user, ['view_any', 'view'])
            && $this->canUseRecordSite($user, $record);
    }

    public function create(User $user): bool
    {
        return $this->hasPermission($user, 'create');
    }

    public function update(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'update')
            && $this->canMutateRecordSite($user, $record);
    }

    public function delete(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'delete')
            && $this->canMutateRecordSite($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'delete_any');
    }

    public function restore(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'restore')
            && $this->canMutateRecordSite($user, $record);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasPermission($user, 'restore_any');
    }

    public function forceDelete(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'force_delete')
            && $this->canMutateRecordSite($user, $record);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'force_delete_any');
    }

    public function replicate(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'replicate')
            && $this->canMutateRecordSite($user, $record);
    }

    public function reorder(User $user): bool
    {
        // The list includes shared records; editors set their own items' sort order in the form.
        return SiteScope::isGlobalActor($user) && $this->hasPermission($user, 'reorder');
    }

    /**
     * @param  list<string>  $abilities
     */
    private function hasAnyPermission(User $user, array $abilities): bool
    {
        return array_any($abilities, fn (string $ability): bool => $this->hasPermission($user, $ability));
    }

    private function hasPermission(User $user, string $ability): bool
    {
        if (SiteScope::isGlobalActor($user)) {
            return true;
        }

        try {
            return $user->checkPermissionTo(self::permission($ability, 'StructuredContentItem'));
        } catch (Throwable) {
            return false;
        }
    }

    private function canUseRecordSite(User $user, StructuredContentItem $record): bool
    {
        $siteId = $record->site_id;

        if ($siteId === null || SiteScope::isGlobalActor($user)) {
            return true;
        }

        return $user->getAssignedSiteIds()->contains((int) $siteId);
    }

    private function canMutateRecordSite(User $user, StructuredContentItem $record): bool
    {
        return SiteScope::isGlobalActor($user)
            || ($record->site_id !== null && $this->canUseRecordSite($user, $record));
    }
}
