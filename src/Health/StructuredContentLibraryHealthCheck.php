<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Health;

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Contracts\Extensions\ChecksExtensionHealth;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Capell\StructuredContentLibrary\Enums\ResourceEnum;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Throwable;

final class StructuredContentLibraryHealthCheck implements ChecksExtensionHealth
{
    private const string StorageTableName = 'structured_content_items';

    public static function compatibleCapellApiVersion(): string
    {
        return '^4.0';
    }

    /**
     * @return Collection<int, DoctorCheckResultData>
     */
    public static function runDiagnostics(): Collection
    {
        $check = new self;

        return collect([
            $check->storageTableCheck(),
            $check->modelRegistryCheck(),
            $check->protectedTableCheck(),
            $check->adminResourceCheck(),
        ]);
    }

    public static function passed(): bool
    {
        return self::runDiagnostics()
            ->every(static fn (DoctorCheckResultData $result): bool => $result->passed);
    }

    public function storageTableCheck(): DoctorCheckResultData
    {
        $tableExists = $this->hasStorageTable();

        return new DoctorCheckResultData(
            label: 'Structured Content Library storage table',
            passed: $tableExists,
            message: $tableExists
                ? 'The structured_content_items table is present.'
                : 'The structured_content_items table is missing.',
            remediation: $tableExists
                ? null
                : 'Run the Capell migrations to create the structured content library table.',
        );
    }

    public function modelRegistryCheck(): DoctorCheckResultData
    {
        $modelRegistered = $this->hasModelRegistered();

        return new DoctorCheckResultData(
            label: 'Structured Content Library model registry',
            passed: $modelRegistered,
            message: $modelRegistered
                ? 'StructuredContentItem is registered with Capell Core.'
                : 'StructuredContentItem is not registered with Capell Core.',
            remediation: $modelRegistered
                ? null
                : 'Ensure StructuredContentLibraryServiceProvider registers the structured content model.',
        );
    }

    public function protectedTableCheck(): DoctorCheckResultData
    {
        $tableProtected = $this->hasProtectedTable();

        return new DoctorCheckResultData(
            label: 'Structured Content Library protected table',
            passed: $tableProtected,
            message: $tableProtected
                ? 'The structured_content_items table is registered as protected.'
                : 'The structured_content_items table is not registered as protected.',
            remediation: $tableProtected
                ? null
                : 'Ensure StructuredContentLibraryServiceProvider registers structured_content_items as a protected table.',
        );
    }

    public function adminResourceCheck(): DoctorCheckResultData
    {
        $resourceRegistered = $this->hasAdminResource();

        return new DoctorCheckResultData(
            label: 'Structured Content Library admin resource',
            passed: $resourceRegistered,
            message: $resourceRegistered
                ? 'The structured content item admin resource is registered.'
                : 'The structured content item admin resource is not registered.',
            remediation: $resourceRegistered
                ? null
                : 'Ensure StructuredContentLibraryServiceProvider contributes the admin resource.',
        );
    }

    public function hasStorageTable(): bool
    {
        return Schema::hasTable(self::StorageTableName);
    }

    public function hasModelRegistered(): bool
    {
        return in_array(StructuredContentItem::class, CapellCore::getModels(), true);
    }

    public function hasProtectedTable(): bool
    {
        return in_array(self::StorageTableName, CapellCore::getProtectedTables(), true);
    }

    public function hasAdminResource(): bool
    {
        try {
            return CapellAdmin::hasResource(ResourceEnum::StructuredContentItem->name);
        } catch (Throwable) {
            return false;
        }
    }
}
