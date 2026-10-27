<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Health\StructuredContentLibraryHealthCheck;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

uses(StructuredContentLibraryTestCase::class);

it('keeps diagnostic identities while naming checks by package and purpose', function (): void {
    $results = StructuredContentLibraryHealthCheck::runDiagnostics();
    $existingIds = [
        'legacy.structured.content.library.storage.table',
        'legacy.structured.content.library.model.registry',
        'legacy.structured.content.library.protected.table',
        'legacy.structured.content.library.admin.resource',
    ];

    expect($results)->not->toBeEmpty();

    foreach ($results as $result) {
        expect($result->label)->toStartWith('Structured Content Library: ')
            ->not->toMatch('/morph|contributor|registry|binding|runtime surfaces/i');
        expect($existingIds)->toContain($result->id);
    }
});
