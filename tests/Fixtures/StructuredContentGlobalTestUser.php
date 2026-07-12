<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures;

use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;

final class StructuredContentGlobalTestUser extends User
{
    /** @param list<int> $assignedSiteIds */
    public function __construct(
        private readonly bool $global = true,
        private readonly array $assignedSiteIds = [],
    ) {
        parent::__construct();
    }

    public function isGlobalAdmin(): bool
    {
        return $this->global;
    }

    public function checkPermissionTo(mixed $permission, mixed $guardName = null): bool
    {
        unset($permission, $guardName);

        return true;
    }

    /** @return Collection<int, int> */
    public function getAssignedSiteIds(): Collection
    {
        return collect($this->assignedSiteIds);
    }
}
