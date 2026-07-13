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
        return '^1.0';
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
            label: (string) __('capell-structured-content-library::health.storage_table_label'),
            passed: $tableExists,
            message: $tableExists
                ? (string) __('capell-structured-content-library::health.storage_table_passed')
                : (string) __('capell-structured-content-library::health.storage_table_failed'),
            remediation: $tableExists
                ? null
                : (string) __('capell-structured-content-library::health.storage_table_remediation'),
        );
    }

    public function modelRegistryCheck(): DoctorCheckResultData
    {
        $modelRegistered = $this->hasModelRegistered();

        return new DoctorCheckResultData(
            label: (string) __('capell-structured-content-library::health.model_registry_label'),
            passed: $modelRegistered,
            message: $modelRegistered
                ? (string) __('capell-structured-content-library::health.model_registry_passed')
                : (string) __('capell-structured-content-library::health.model_registry_failed'),
            remediation: $modelRegistered
                ? null
                : (string) __('capell-structured-content-library::health.model_registry_remediation'),
        );
    }

    public function protectedTableCheck(): DoctorCheckResultData
    {
        $tableProtected = $this->hasProtectedTable();

        return new DoctorCheckResultData(
            label: (string) __('capell-structured-content-library::health.protected_table_label'),
            passed: $tableProtected,
            message: $tableProtected
                ? (string) __('capell-structured-content-library::health.protected_table_passed')
                : (string) __('capell-structured-content-library::health.protected_table_failed'),
            remediation: $tableProtected
                ? null
                : (string) __('capell-structured-content-library::health.protected_table_remediation'),
        );
    }

    public function adminResourceCheck(): DoctorCheckResultData
    {
        $resourceRegistered = $this->hasAdminResource();

        return new DoctorCheckResultData(
            label: (string) __('capell-structured-content-library::health.admin_resource_label'),
            passed: $resourceRegistered,
            message: $resourceRegistered
                ? (string) __('capell-structured-content-library::health.admin_resource_passed')
                : (string) __('capell-structured-content-library::health.admin_resource_failed'),
            remediation: $resourceRegistered
                ? null
                : (string) __('capell-structured-content-library::health.admin_resource_remediation'),
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
