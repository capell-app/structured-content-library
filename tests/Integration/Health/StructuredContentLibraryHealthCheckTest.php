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
    expect(StructuredContentLibraryHealthCheck::compatibleCapellApiVersion())->toBe('^4.0');
});

it('runs real diagnostics returning check results', function (): void {
    $results = StructuredContentLibraryHealthCheck::runDiagnostics();

    expect($results)->toHaveCount(4)
        ->and($results->every(static fn (mixed $result): bool => $result instanceof DoctorCheckResultData))->toBeTrue();
});

it('passes when storage, model, protected table, and admin resource registrations are present', function (): void {
    $results = StructuredContentLibraryHealthCheck::runDiagnostics();

    expect(StructuredContentLibraryHealthCheck::passed())->toBeTrue()
        ->and($results->every(static fn (DoctorCheckResultData $result): bool => $result->passed))->toBeTrue();
});

it('fails when the storage table is missing', function (): void {
    Schema::drop('structured_content_items');

    $check = new StructuredContentLibraryHealthCheck;

    expect($check->hasStorageTable())->toBeFalse()
        ->and($check->storageTableCheck()->passed)->toBeFalse()
        ->and(StructuredContentLibraryHealthCheck::passed())->toBeFalse();
});

it('fails when provider registrations are missing', function (): void {
    CapellAdmin::clearAdminSurfaceContributions();
    CapellCore::swap(new CapellCoreManager);

    $check = new StructuredContentLibraryHealthCheck;

    expect($check->hasModelRegistered())->toBeFalse()
        ->and($check->modelRegistryCheck()->passed)->toBeFalse()
        ->and($check->hasProtectedTable())->toBeFalse()
        ->and($check->protectedTableCheck()->passed)->toBeFalse()
        ->and($check->hasAdminResource())->toBeFalse()
        ->and($check->adminResourceCheck()->passed)->toBeFalse()
        ->and(StructuredContentLibraryHealthCheck::passed())->toBeFalse();
});
