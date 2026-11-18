<?php

declare(strict_types=1);

use Capell\Core\Facades\CapellCore;
use Capell\Core\Models\Language;
use Capell\Core\Support\TableName;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\CreateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Actions\RestoreStructuredContentRevisionAction;
use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Data\StructuredContentTranslationData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Support\StructuredContentRevisions;
use Capell\StructuredContentLibrary\Tests\Fixtures\StructuredContentGlobalTestUser;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

function structuredContentLanguage(string $code, ?int $fallbackLanguageId = null): Language
{
    $id = DB::table('languages')->insertGetId([
        'name' => strtoupper($code),
        'code' => $code,
        'locale' => $code,
        'fallback_language_id' => $fallbackLanguageId,
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    return Language::query()->findOrFail($id);
}

/**
 * @param  list<StructuredContentTranslationData>|null  $translations
 */
function testimonialData(string $title, ?array $translations = null, string $quote = 'Capell saves us hours.'): StructuredContentItemData
{
    return new StructuredContentItemData(
        type: StructuredContentType::Testimonial,
        title: $title,
        status: StructuredContentStatus::Published,
        summary: '<p>English summary</p>',
        payload: new StructuredContentPayloadData(quote: $quote, attribution: 'Ada Example'),
        translations: $translations,
    );
}

it('serves a record in the rendered language and keeps its own wording for blank fields', function (): void {
    $french = structuredContentLanguage('fr');
    CreateStructuredContentItemAction::run(testimonialData('Ada Example', [
        new StructuredContentTranslationData(
            languageId: (int) $french->id,
            title: 'Ada Exemple',
            payload: new StructuredContentPayloadData(quote: 'Capell nous fait gagner des heures.'),
        ),
    ]));

    app()->setLocale('en');
    $english = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial)[0];
    app()->setLocale('fr');
    $translated = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial)[0];

    expect($english->title)->toBe('Ada Example')
        ->and($english->payload['quote'])->toBe('Capell saves us hours.')
        ->and($translated->title)->toBe('Ada Exemple')
        ->and($translated->payload['quote'])->toBe('Capell nous fait gagner des heures.')
        ->and($translated->payload['attribution'])->toBe('Ada Example')
        ->and($translated->summary)->toBe('<p>English summary</p>')
        ->and($translated->slug)->toBe($english->slug);
});

it('follows the language fallback chain to a translation', function (): void {
    $french = structuredContentLanguage('fr');
    $canadianFrench = structuredContentLanguage('fr-CA', (int) $french->id);
    CreateStructuredContentItemAction::run(testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $french->id, title: 'Ada Exemple'),
    ]));

    app()->setLocale((string) $canadianFrench->locale);

    expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial)[0]->title)->toBe('Ada Exemple');
});

it('updates, removes and validates translations through the record actions', function (): void {
    $french = structuredContentLanguage('fr');
    $german = structuredContentLanguage('de');
    $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $french->id, title: 'Ada Exemple'),
    ]));

    UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $german->id, title: 'Ada Beispiel'),
    ]));

    expect($item->translations()->pluck('title', 'language_id')->all())->toBe([(int) $german->id => 'Ada Beispiel']);

    UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example'));

    expect($item->translations()->count())->toBe(1);

    expect(fn () => UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $german->id, title: 'Eins'),
        new StructuredContentTranslationData(languageId: (int) $german->id, title: 'Zwei'),
    ])))->toThrow(ValidationException::class);

    expect(fn () => UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $german->id, title: 'Ada', payload: new StructuredContentPayloadData(question: 'Not a testimonial field')),
    ])))->toThrow(ValidationException::class);
});

it('removes a record translations when the record is permanently deleted', function (): void {
    $french = structuredContentLanguage('fr');
    $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $french->id, title: 'Ada Exemple'),
    ]));

    $item->forceDelete();

    expect(DB::table('translations')->where('translatable_type', $item->getMorphClass())->count())->toBe(0);
});

it('clears the public list when a translation changes', function (): void {
    $french = structuredContentLanguage('fr');
    $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example', [
        new StructuredContentTranslationData(languageId: (int) $french->id, title: 'Ada Exemple'),
    ]));
    app()->setLocale('fr');
    expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial)[0]->title)->toBe('Ada Exemple');

    $item->translations()->sole()->update(['title' => 'Ada Exemple mise à jour']);

    expect(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial)[0]->title)->toBe('Ada Exemple mise à jour');
});

it('keeps no history and offers none without Publishing Studio', function (): void {
    $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example'));

    expect(StructuredContentRevisions::available())->toBeFalse()
        ->and(StructuredContentRevisions::history($item))->toBe([]);
});

describe('with Publishing Studio', function (): void {
    beforeEach(function (): void {
        // The revision log references Publishing Studio's workspaces and versions; revisions of
        // structured content belong to neither, so bare stand-ins satisfy the foreign keys.
        foreach (['workspaces', 'versions'] as $table) {
            Schema::create(TableName::resolve($table), static function (Blueprint $blueprint): void {
                $blueprint->id();
            });
        }

        $migration = require dirname(__DIR__, 4) . '/publishing-studio/database/migrations/2026_05_10_190866_11_create_publishing_revisions_table.php';
        $migration->up();
        CapellCore::forcePackageInstalled('capell-app/publishing-studio');
        // Revisions record their actor by morph type, as an application's user model has.
        Relation::morphMap(['structured_content_test_user' => StructuredContentGlobalTestUser::class]);
    });

    it('records a revision for each published save and none for drafts', function (): void {
        $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example'));
        UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example', quote: 'Capell saves us days.'));
        UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example', quote: 'Capell saves us days.'));
        UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(type: StructuredContentType::Testimonial, title: 'Draft wording'));

        $history = StructuredContentRevisions::history($item);

        expect(StructuredContentRevisions::available())->toBeTrue()
            ->and(array_column($history, 'version'))->toBe([2, 1])
            ->and($history[0]['changed'])->toContain('payload');
    });

    it('restores a revision and records the restore', function (): void {
        $french = structuredContentLanguage('fr');
        $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example', [
            new StructuredContentTranslationData(languageId: (int) $french->id, title: 'Ada Exemple'),
        ]));
        $first = StructuredContentRevisions::history($item)[0];
        UpdateStructuredContentItemAction::run($item, testimonialData('Ada Example renamed', [], quote: 'Changed quote'));

        $restored = RestoreStructuredContentRevisionAction::run($item, $first['id']);

        expect($restored->title)->toBe('Ada Example')
            ->and($restored->payload?->quote)->toBe('Capell saves us hours.')
            ->and($restored->translations()->pluck('title')->all())->toBe(['Ada Exemple'])
            ->and(StructuredContentRevisions::history($restored)[0]['event'])->toBe('restored');
    });

    it('refuses to restore another record revision', function (): void {
        $item = CreateStructuredContentItemAction::run(testimonialData('Ada Example'));
        $other = CreateStructuredContentItemAction::run(testimonialData('Grace Example'));

        RestoreStructuredContentRevisionAction::run($item, StructuredContentRevisions::history($other)[0]['id']);
    })->throws(ValidationException::class);
});
