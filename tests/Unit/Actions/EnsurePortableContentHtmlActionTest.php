<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\EnsurePortableContentHtmlAction;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Dom\HTMLDocument;
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

it('rejects complete authoring attribute names', function (string $attribute): void {
    expect(fn (): ?string => EnsurePortableContentHtmlAction::run('<p ' . $attribute . '>Text</p>'))
        ->toThrow(ValidationException::class);
})->with(['x-data="{}"', 'x-init="alert(1)"', 'wire:click="doSomething"', 'WIRE:click.prevent="save"', 'x-cloak']);

it('rejects authoring shorthand and attributes after quoted tag delimiters', function (string $html): void {
    expect(fn (): ?string => EnsurePortableContentHtmlAction::run($html))->toThrow(ValidationException::class);
})->with(['<p @click="alert(1)">Text</p>', '<p :class="secret">Text</p>', '<p title=">" x-init="alert(1)">Text</p>']);

it('sanitises parsed href attributes even without separating whitespace', function (string $html): void {
    $safe = EnsurePortableContentHtmlAction::run($html);
    $dom = HTMLDocument::createFromString($safe ?? '', LIBXML_NOERROR, 'UTF-8');
    expect($dom->getElementsByTagName('a')->item(0)?->hasAttribute('href'))->toBeFalse()
        ->and($dom->getElementsByTagName('a')->item(0)?->textContent)->toBe('test');
})->with([
    '<a title="safe"href="javascript:alert(1)">test</a>',
    '<a title="safe"HREF="&#106;avascript:alert(1)">test</a>',
    '<a title="safe"href=data:text/plain,hello>test</a>',
    '<a title="safe"href="javascript:alert(1)" href="https://example.com">test</a>',
]);
