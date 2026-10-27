<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Tests\Fixtures;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Collection;

final class StructuredContentGlobalTestUser extends User
{
    /** @use HasFactory<Factory<static>> */
    use HasFactory;

    /** @param list<int> $assignedSiteIds */
    public function __construct(
        private readonly bool $global = true,
        private readonly array $assignedSiteIds = [],
        private readonly bool $permissionsAllowed = true,
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

        return $this->permissionsAllowed;
    }

    /** @return Collection<int, int> */
    public function getAssignedSiteIds(): Collection
    {
        return collect($this->assignedSiteIds);
    }
}
