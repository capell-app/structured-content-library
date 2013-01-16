<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Filament\Facades\Filament;
use Livewire\Livewire;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

beforeEach(function (): void {
    Filament::setCurrentPanel(Filament::getPanel('scl-test'));
    Filament::bootCurrentPanel();
});

it('starts with nine accessible type cards before showing the common form', function (): void {
    $page = Livewire::test(CreateStructuredContentItem::class)
        ->assertSuccessful()
        ->assertSee('What are you adding?')
        ->assertFormFieldIsHidden('title');

    $dom = new DOMDocument;
    $html = $page->html();

    if ($html === '') {
        throw new RuntimeException('The type-choice page rendered no HTML.');
    }

    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);
    $radios = $xpath->query('//input[@type="radio"]');

    if ($radios === false) {
        throw new RuntimeException('The type-card DOM query failed.');
    }

    expect($radios->length)->toBe(9);

    foreach (StructuredContentType::cases() as $type) {
        $definition = StructuredContentDefinitionData::forType($type);
        $page->assertSee($definition->label())->assertSee($definition->description())->assertSee($definition->example());
    }
});

it('shows common details first and only the chosen payload fields', function (StructuredContentType $type): void {
    $definition = StructuredContentDefinitionData::forType($type);
    $page = Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', $type->value)
        ->assertFormFieldIsVisible('title')
        ->assertFormFieldIsVisible('status')
        ->assertSeeInOrder(['Title and status', $definition->groupLabel(), 'Advanced', 'Publishing']);

    foreach ($definition->fields as $field) {
        $page->assertFormFieldIsVisible('payload.' . $field->value);
    }

    if ($type !== StructuredContentType::Faq) {
        $page->assertFormFieldIsHidden('payload.question');
    }
})->with(StructuredContentType::cases());

it('clears unsaved type-specific fields when a different intent is selected', function (): void {
    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'testimonial')
        ->set('data.title', 'Keep the common title')
        ->set('data.payload.quote', 'Do not carry this into a FAQ')
        ->set('data.type', 'faq')
        ->assertSet('data.title', 'Keep the common title')
        ->assertSet('data.payload', [])
        ->assertFormFieldIsHidden('payload.quote')
        ->assertFormFieldIsVisible('payload.question');
});

it('creates and reopens type-specific content with advanced and publishing values intact', function (): void {
    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'faq')
        ->fillForm([
            'title' => 'Delivery', 'status' => 'published', 'slug' => 'delivery-help', 'sort_order' => 12,
            'published_at' => '2026-08-01 10:00:00',
            'payload' => ['question' => 'When will it arrive?', 'answer' => 'Within three days.'],
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $item = StructuredContentItem::query()->sole();
    expect($item->type)->toBe(StructuredContentType::Faq)
        ->and($item->slug)->toBe('delivery-help')
        ->and($item->sort_order)->toBe(12)
        ->and($item->published_at?->toDateTimeString())->toBe('2026-08-01 10:00:00');

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldIsDisabled('type')
        ->assertSee('The type is fixed after creation.')
        ->assertFormSet(['payload.question' => 'When will it arrive?', 'sort_order' => 12, 'slug' => 'delivery-help'])
        ->fillForm(['payload.answer' => 'Within two days.'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->payload?->answer)->toBe('Within two days.');
});

it('rejects forged edit type state without mutating the record', function (): void {
    $item = StructuredContentItem::factory()->create();

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->set('data.type', 'faq')
        ->call('save')
        ->assertHasFormErrors(['type']);

    expect($item->refresh()->type)->toBe(StructuredContentType::Service);
});

it('validates the common title and renders both empty and populated lists', function (): void {
    Livewire::test(ListStructuredContentItems::class)
        ->assertSuccessful()
        ->assertSee('Your reusable content starts here')
        ->assertSee('Create structured content');

    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'service')
        ->call('create')
        ->assertHasFormErrors(['title' => 'required']);

    $item = StructuredContentItem::factory()->create(['title' => 'Website support']);

    Livewire::test(ListStructuredContentItems::class)
        ->assertCanSeeTableRecords([$item])
        ->assertSee('Website support');
});

it('edits every type using its named group and saves only compatible fields', function (StructuredContentType $type): void {
    $definition = StructuredContentDefinitionData::forType($type);
    $field = $definition->fields[0];
    $value = $field->value === 'email' ? 'office@example.com' : 'Updated detail';
    $item = StructuredContentItem::factory()->type($type)->create(['payload' => null]);

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSee($definition->groupLabel())
        ->assertFormFieldIsDisabled('type')
        ->fillForm(['payload.' . $field->value => $value])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->payload?->toArray()[$field->value])->toBe($value);
})->with(StructuredContentType::cases());

it('previews saved draft content through the hydrated public adapter', function (): void {
    $item = StructuredContentItem::factory()->type(StructuredContentType::Testimonial)->create([
        'title' => 'Saved feedback',
        'payload' => ['quote' => '<strong>Saved safe quote</strong>', 'question' => 'Incompatible historical value'],
    ]);

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['title' => 'Unsaved feedback'])
        ->assertActionVisible('preview')
        ->mountAction('preview')
        ->assertActionMounted('preview')
        ->assertMountedActionModalSee('Saved safe quote')
        ->assertMountedActionModalSee('Saved feedback')
        ->assertMountedActionModalDontSee(['Incompatible historical value', 'Unsaved feedback']);

    expect($item->refresh()->status->value)->toBe('draft');
});
