<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\EnsurePortableEditorDocumentAction;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';
uses(StructuredContentLibraryTestCase::class);

it('rejects unsupported nodes in a JSON encoded editor document', function (): void {
    expect(function (): void {
        EnsurePortableEditorDocumentAction::run(json_encode([
            'type' => 'doc', 'content' => [['type' => 'rawHtmlMergeTag', 'html' => '<p>Text</p>']],
        ], JSON_THROW_ON_ERROR));
    })->toThrow(ValidationException::class);
});

it('rejects malformed and unsupported document shapes before conversion', function (array $document): void {
    expect(function () use ($document): void {
        EnsurePortableEditorDocumentAction::run($document);
    })->toThrow(ValidationException::class);
})->with([
    'unknown root' => [['type' => 'paragraph']],
    'invalid content' => [['type' => 'doc', 'content' => 'invalid']],
    'invalid attributes' => [['type' => 'doc', 'attrs' => true]],
    'unknown key' => [['type' => 'doc', 'html' => '<p>Text</p>']],
    'invalid marks' => [['type' => 'doc', 'marks' => 'invalid']],
    'missing text' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text']]]]]],
    'block text' => [['type' => 'doc', 'text' => 'invalid']],
    'root mark' => [['type' => 'doc', 'marks' => [['type' => 'bold']]]],
    'unsupported heading' => [['type' => 'doc', 'content' => [['type' => 'heading', 'attrs' => ['level' => 1]]]]],
    'invalid reversed' => [['type' => 'doc', 'content' => [['type' => 'orderedList', 'attrs' => ['reversed' => 'false']]]]],
    'invalid numbering' => [['type' => 'doc', 'content' => [['type' => 'orderedList', 'attrs' => ['type' => 'z']]]]],
    'invalid start' => [['type' => 'doc', 'content' => [['type' => 'orderedList', 'attrs' => ['start' => []]]]]],
    'code presentation' => [['type' => 'doc', 'content' => [['type' => 'codeBlock', 'attrs' => ['language' => 'php']]]]],
    'link presentation' => [['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text', 'marks' => [['type' => 'link', 'attrs' => ['href' => '/', 'class' => 'private']]]]]]]]],
]);

it('rejects authoring attributes hidden inside portable attribute maps', function (string $name, mixed $value, string $bag): void {
    $node = $bag === 'codeAttributes'
        ? ['type' => 'codeBlock', 'attrs' => [$bag => [$name => $value]], 'content' => [['type' => 'text', 'text' => 'Code']]]
        : ['type' => 'paragraph', 'attrs' => [$bag => [$name => $value]], 'content' => [['type' => 'text', 'text' => 'Text']]];
    expect(function () use ($node): void {
        EnsurePortableEditorDocumentAction::run(['type' => 'doc', 'content' => [$node]]);
    })
        ->toThrow(ValidationException::class);
})->with([
    ['class', 'private'], ['style', 'display:none'], ['id', 'editor'], ['data-editor', 'private'],
    ['wire:click', 'save'], ['x-init', 'alert(1)'], ['onclick', 'alert(1)'], ['@click', 'save'],
    [':class', 'private'], ['title" onclick="', 'alert(1)'], ['title', ['invalid']],
])->with(['portableAttributes', 'codeAttributes']);

it('rejects unsupported marks and attributes on inline leaves', function (string $type, array $extra): void {
    expect(function () use ($type, $extra): void {
        EnsurePortableEditorDocumentAction::run([
            'type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [['type' => $type, ...$extra]]]],
        ]);
    })->toThrow(ValidationException::class);
})->with(['hardBreak', 'emptyInline'])->with([
    'unsupported mark' => [['marks' => [['type' => 'underline']]]],
    'unsupported mark attribute' => [['marks' => [['type' => 'bold', 'attrs' => ['class' => 'private']]]]],
    'unsupported node attribute' => [['attrs' => ['class' => 'private']]],
    'forbidden portable attribute' => [['attrs' => ['portableAttributes' => ['onclick' => 'bad']]]],
    'children on a leaf' => [['content' => [['type' => 'text', 'text' => 'hidden']]]],
]);

it('rejects unsupported empty inline element tags', function (): void {
    expect(function (): void {
        EnsurePortableEditorDocumentAction::run([
            'type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
                ['type' => 'emptyInline', 'attrs' => ['tag' => 'script']],
            ]]],
        ]);
    })->toThrow(ValidationException::class);
});
