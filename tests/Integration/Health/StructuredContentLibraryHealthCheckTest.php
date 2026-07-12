<?php

declare(strict_types=1);

use Capell\Admin\Facades\CapellAdmin;
use Capell\Core\Data\Diagnostics\DoctorCheckResultData;
use Capell\Core\Facades\CapellCore;
use Capell\Core\Support\CapellCoreManager;
use Capell\StructuredContentLibrary\Health\StructuredContentLibraryHealthCheck;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\Schema;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('reports a compatible capell api version', function (): void {
    expect(StructuredContentLibraryHealthCheck::compatibleCapellApiVersion())->toBe('^0.0');
});

it('runs real diagnostics returning check results', function (): void {
    $results = StructuredContentLibraryHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(4)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('passes when storage, model, protected table, and admin resource registrations are present', function (): void {
    $results = StructuredContentLibraryHealthCheck::runDiagnostics();

    expect(StructuredContentLibraryHealthCheck::passed())->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue()
        ->and($results->pluck('label')->all())->toBe([
            (string) __('capell-structured-content-library::health.storage_table_label'),
            (string) __('capell-structured-content-library::health.model_registry_label'),
            (string) __('capell-structured-content-library::health.protected_table_label'),
            (string) __('capell-structured-content-library::health.admin_resource_label'),
        ]);
});

it('fails when the storage table is missing', function (): void {
    Schema::drop('structured_content_items');

    $check = new StructuredContentLibraryHealthCheck;
    $result = $check->storageTableCheck();

    expect($check->hasStorageTable())->toBeFalse()
        ->and($result->passed)->toBeFalse()
        ->and($result->message)->toBe((string) __('capell-structured-content-library::health.storage_table_failed'))
        ->and($result->remediation)->toBe((string) __('capell-structured-content-library::health.storage_table_remediation'))
        ->and(StructuredContentLibraryHealthCheck::passed())->toBeFalse();
});

it('fails when provider registrations are missing', function (): void {
    CapellAdmin::clearAdminSurfaceContributions();
    CapellCore::swap(new CapellCoreManager);

    $check = new StructuredContentLibraryHealthCheck;
    $modelResult = $check->modelRegistryCheck();
    $protectedTableResult = $check->protectedTableCheck();
    $adminResourceResult = $check->adminResourceCheck();

    expect($check->hasModelRegistered())->toBeFalse()
        ->and($modelResult->passed)->toBeFalse()
        ->and($modelResult->message)->toBe((string) __('capell-structured-content-library::health.model_registry_failed'))
        ->and($check->hasProtectedTable())->toBeFalse()
        ->and($protectedTableResult->passed)->toBeFalse()
        ->and($protectedTableResult->message)->toBe((string) __('capell-structured-content-library::health.protected_table_failed'))
        ->and($check->hasAdminResource())->toBeFalse()
        ->and($adminResourceResult->passed)->toBeFalse()
        ->and($adminResourceResult->message)->toBe((string) __('capell-structured-content-library::health.admin_resource_failed'))
        ->and(StructuredContentLibraryHealthCheck::passed())->toBeFalse();
});
