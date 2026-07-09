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
            && $this->canUseRecordSite($user, $record);
    }

    public function delete(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'delete')
            && $this->canUseRecordSite($user, $record);
    }

    public function deleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'delete_any');
    }

    public function restore(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'restore')
            && $this->canUseRecordSite($user, $record);
    }

    public function restoreAny(User $user): bool
    {
        return $this->hasPermission($user, 'restore_any');
    }

    public function forceDelete(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'force_delete')
            && $this->canUseRecordSite($user, $record);
    }

    public function forceDeleteAny(User $user): bool
    {
        return $this->hasPermission($user, 'force_delete_any');
    }

    public function replicate(User $user, StructuredContentItem $record): bool
    {
        return $this->hasPermission($user, 'replicate')
            && $this->canUseRecordSite($user, $record);
    }

    public function reorder(User $user): bool
    {
        return $this->hasPermission($user, 'reorder');
    }

    /**
     * @param  list<string>  $abilities
     */
    private function hasAnyPermission(User $user, array $abilities): bool
    {
        foreach ($abilities as $ability) {
            if ($this->hasPermission($user, $ability)) {
                return true;
            }
        }

        return false;
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
}
