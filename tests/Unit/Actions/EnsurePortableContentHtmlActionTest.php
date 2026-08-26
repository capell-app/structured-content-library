<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\EnsurePortableContentHtmlAction;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('strips a javascript: href scheme while preserving the surrounding markup', function (): void {
    $result = EnsurePortableContentHtmlAction::run(
        '<p><a href="javascript:alert(document.cookie)">Click</a></p>',
    );

    expect($result)->toBe('<p><a>Click</a></p>');
});

it('strips a data: href scheme', function (): void {
    $result = EnsurePortableContentHtmlAction::run(
        '<p><a href="data:text/plain,hello">Click</a></p>',
    );

    expect($result)->toBe('<p><a>Click</a></p>');
});

it('strips an unquoted javascript: href scheme', function (): void {
    $result = EnsurePortableContentHtmlAction::run(
        '<p><a href=javascript:alert(1)>Click</a></p>',
    );

    expect($result)->toBe('<p><a>Click</a></p>');
});

it('strips an HTML-entity-encoded javascript: href scheme', function (): void {
    $result = EnsurePortableContentHtmlAction::run(
        '<p><a href="&#106;avascript:alert(1)">Click</a></p>',
    );

    expect($result)->toBe('<p><a>Click</a></p>');
});

it('preserves a safe https href', function (): void {
    $result = EnsurePortableContentHtmlAction::run(
        '<p><a href="https://example.com/pricing">visit</a></p>',
    );

    expect($result)->toBe('<p><a href="https://example.com/pricing">visit</a></p>');
});

it('preserves safe relative, hash, http, and mailto href schemes', function (): void {
    $result = EnsurePortableContentHtmlAction::run(
        '<p><a href="/pricing">relative</a>, <a href="#section">hash</a>, '
        . '<a href="http://example.com">http</a>, <a href="mailto:hi@example.com">mail</a></p>',
    );

    expect($result)->toBe(
        '<p><a href="/pricing">relative</a>, <a href="#section">hash</a>, '
        . '<a href="http://example.com">http</a>, <a href="mailto:hi@example.com">mail</a></p>',
    );
});

it('still rejects disallowed tags entirely', function (): void {
    expect(fn (): ?string => EnsurePortableContentHtmlAction::run(
        '<script>alert(1)</script>',
    ))->toThrow(ValidationException::class);
});

it('still rejects blocked attributes such as onclick', function (): void {
    expect(fn (): ?string => EnsurePortableContentHtmlAction::run(
        '<p onclick="alert(1)">Unsafe</p>',
    ))->toThrow(ValidationException::class);
});
