<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Actions\ImportStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentPayloadField;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Foundation\Auth\User;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\DB;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

it('shares each typed definition between imports and public adapter projections', function (StructuredContentType $type): void {
    $definition = StructuredContentDefinitionData::forType($type);
    $values = [];

    foreach ($definition->fields as $field) {
        expect($field->isRequired())->toBeFalse()
            ->and($field->label())->not->toContain('capell-structured-content-library::');
        $values[$field->value] = match ($field) {
            StructuredContentPayloadField::Url => '/help',
            StructuredContentPayloadField::Email => 'hello@example.com',
            default => '<strong>Reusable text</strong>',
        };
    }

    $result = ImportStructuredContentItemsAction::run([[
        'type' => $type->value,
        'title' => 'Reusable item',
        'status' => StructuredContentStatus::Published->value,
        'payload' => $values,
    ]]);
    $items = BuildPublicStructuredContentItemsAction::run($type);

    expect($result->created)->toBe(1)
        ->and(array_keys($items[0]->payload))->toBe($definition->fieldNames());

    foreach ($definition->fields as $field) {
        expect($items[0]->payload[$field->value])->toBe(match ($field) {
            StructuredContentPayloadField::Url => '/help',
            StructuredContentPayloadField::Email => 'hello@example.com',
            default => 'Reusable text',
        });
    }

    // Existing integrations may save a title first and fill the optional fields later.
    expect(CreateStructuredContentItemAction::run(new StructuredContentItemData(type: $type, title: 'Draft'))->exists)->toBeTrue();
})->with(StructuredContentType::cases());

it('renders hydrated cached adapter output without queries or authoring internals', function (bool $anonymous): void {
    CreateStructuredContentItemAction::run(new StructuredContentItemData(
        type: StructuredContentType::Testimonial,
        title: 'Customer feedback',
        status: StructuredContentStatus::Published,
        payload: new StructuredContentPayloadData(quote: '&lt;script&gt;alert(1)&lt;/script&gt;Helpful support', attribution: 'Alex'),
    ));

    $anonymous ? auth()->forgetGuards() : $this->actingAs(new User);
    $items = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial);
    DB::enableQueryLog();
    DB::flushQueryLog();

    $cached = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial);
    $html = Blade::render('<article><h2>{{ $item->title }}</h2><blockquote>{{ $item->payload["quote"] }}</blockquote><cite>{{ $item->payload["attribution"] }}</cite></article>', ['item' => $items[0]]);

    expect(DB::getQueryLog())->toBe([])
        ->and($cached[0]->payload)->toBe($items[0]->payload)
        ->and($html)->toContain('Helpful support', '<cite>Alex</cite>')
        ->not->toContain('script', 'wire:', 'data-field', 'payload.', 'site_id', 'signed', 'capell-', 'Filament');
    DB::disableQueryLog();
})->with([true, false]);
