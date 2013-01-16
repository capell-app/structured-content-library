<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Actions\ImportStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('rejects type conversion without changing the original record or its payload', function (): void {
    $item = StructuredContentItem::factory()->create([
        'payload' => new StructuredContentPayloadData(subtitle: 'Keep this service detail'),
    ]);

    try {
        UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
            type: StructuredContentType::Faq,
            title: 'Changed meaning',
            payload: new StructuredContentPayloadData(question: 'Can I convert?', answer: 'No.'),
        ));
        $this->fail('Type conversion was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('type')
            ->and($item->refresh()->type)->toBe(StructuredContentType::Service)
            ->and($item->payload?->subtitle)->toBe('Keep this service detail')
            ->and($item->payload?->question)->toBeNull();
    }
});

it('checks the persisted type even when the caller supplies a stale model', function (): void {
    $item = StructuredContentItem::factory()->create();
    $item->type = StructuredContentType::Faq;

    expect(fn () => UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Faq,
        title: 'Tampered type',
    )))->toThrow(ValidationException::class);

    expect($item->refresh()->type)->toBe(StructuredContentType::Service);
});

it('rejects incompatible payload keys through creation and import', function (bool $import): void {
    $data = new StructuredContentItemData(
        type: StructuredContentType::Faq,
        title: 'Question',
        payload: new StructuredContentPayloadData(question: 'Question?', quote: 'Old testimonial'),
    );

    try {
        $import ? ImportStructuredContentItemsAction::run([$data]) : CreateStructuredContentItemAction::run($data);
        $this->fail('Incompatible payload was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('payload.quote')
            ->and(StructuredContentItem::query()->count())->toBe(0);
    }
})->with([false, true]);

it('rejects incompatible payload keys during an ordinary update', function (): void {
    $item = StructuredContentItem::factory()->create();

    expect(fn () => UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: StructuredContentType::Service,
        title: 'Service',
        payload: new StructuredContentPayloadData(question: 'Left over'),
    )))->toThrow(ValidationException::class);

    expect($item->refresh()->payload?->question)->toBeNull();
});

it('filters historical incompatible payload keys out of public adapters', function (): void {
    StructuredContentItem::factory()->published()->type(StructuredContentType::Faq)->create([
        'payload' => new StructuredContentPayloadData(question: 'Visible question?', quote: 'Old private quote'),
    ]);

    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Faq);

    expect($items[0]->payload)->toBe(['question' => 'Visible question?']);
});

it('validates link and email payloads at the action boundary', function (StructuredContentType $type, StructuredContentPayloadData $payload, string $field): void {
    try {
        CreateStructuredContentItemAction::run(new StructuredContentItemData(type: $type, title: 'Invalid contact', payload: $payload));
        $this->fail('Invalid contact was accepted.');
    } catch (ValidationException $exception) {
        expect($exception->errors())->toHaveKey('payload.' . $field);
    }
})->with([
    'unsafe URL' => [StructuredContentType::Resource, new StructuredContentPayloadData(url: 'javascript:alert(1)'), 'url'],
    'network URL' => [StructuredContentType::Resource, new StructuredContentPayloadData(url: '//example.com'), 'url'],
    'invalid email' => [StructuredContentType::TeamMember, new StructuredContentPayloadData(email: 'not-an-email'), 'email'],
]);
