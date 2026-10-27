<?php

declare(strict_types=1);

use BezhanSalleh\FilamentShield\FilamentShieldServiceProvider;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Actions\DescribeStructuredContentAvailabilityAction;
use Capell\StructuredContentLibrary\Actions\EnsurePortableContentHtmlAction;
use Capell\StructuredContentLibrary\Actions\UpdateStructuredContentItemAction;
use Capell\StructuredContentLibrary\Data\StructuredContentItemData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\CreateStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\EditStructuredContentItem;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\Pages\ListStructuredContentItems;
use Capell\StructuredContentLibrary\Filament\Resources\StructuredContentItems\StructuredContentItemResource;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\Fixtures\StructuredContentGlobalTestUser;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Dom\Element;
use Dom\HTMLDocument;
use Filament\Facades\Filament;
use Filament\Forms\Components\Field;
use Filament\Forms\Components\RichEditor;
use Filament\Forms\Components\Select;
use Illuminate\Auth\Access\AuthorizationException;
use Livewire\Livewire;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

beforeEach(function (): void {
    app()->register(FilamentShieldServiceProvider::class);
    Filament::setCurrentPanel(Filament::getPanel('scl-test'));
    Filament::bootCurrentPanel();
});

it('lets a site editor create reopen and update within their assigned site', function (): void {
    $siteA = $this->createStructuredContentSite('Authorised site');
    $siteB = $this->createStructuredContentSite('Other site');
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$siteA]));

    Livewire::test(CreateStructuredContentItem::class)
        ->assertSuccessful()
        ->set('data.type', 'service')
        ->fillForm(['title' => 'Site service', 'status' => 'published'])
        ->call('create')
        ->assertSuccessful()
        ->assertHasNoFormErrors();

    $item = StructuredContentItem::query()->sole();
    expect($item->site_id)->toBe($siteA);

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSuccessful()
        ->assertFormFieldIsVisible('site_id')
        ->assertFormFieldExists('site_id', fn (Select $field): bool => array_keys($field->getOptions()) === [$siteA])
        ->assertFormSet(['site_id' => $siteA])
        ->fillForm(['title' => 'Updated site service'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($item->refresh()->title)->toBe('Updated site service')
        ->and($item->site_id)->toBe($siteA)
        ->and($item->content)->toBeNull()
        ->and($item->summary)->toBeNull()
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service, $siteA))->toHaveCount(1)
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service, $siteB))->toBe([]);
});

it('offers portable formatting controls and saves editor document state through the public adapter', function (): void {
    $document = [
        'type' => 'doc',
        'content' => [
            ['type' => 'heading', 'attrs' => ['level' => 2], 'content' => [['type' => 'text', 'text' => 'Project outcome']]],
            ['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'First paragraph.']]],
            ['type' => 'bulletList', 'content' => [
                ['type' => 'listItem', 'content' => [
                    ['type' => 'paragraph', 'content' => [
                        ['type' => 'text', 'text' => 'Read the guide', 'marks' => [['type' => 'link', 'attrs' => ['href' => 'https://example.com/guide']]]],
                    ]],
                ]],
            ]],
        ],
    ];

    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'case_study')
        ->assertFormFieldExists('content', function (Field $field): bool {
            $this->assertInstanceOf(RichEditor::class, $field);
            $buttons = collect($field->getToolbarButtons())->flatten()->all();

            expect($buttons)->toContain('h2', 'h3', 'h4', 'bulletList', 'orderedList', 'link', 'bold', 'italic', 'blockquote')
                ->not->toContain('attachFiles', 'textColor', 'alignCenter', 'h1', 'underline', 'table')
                ->and($field->getFloatingToolbars())->toBe([]);

            return true;
        })
        ->assertFormFieldExists('summary', fn (RichEditor $field): bool => ! $field->hasFileAttachments())
        ->fillForm(['site_id' => 'all', 'title' => 'A project', 'status' => 'published', 'content' => $document])
        ->call('create')
        ->assertSuccessful()
        ->assertHasNoFormErrors();

    $item = StructuredContentItem::query()->sole();
    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->assertSuccessful()
        ->fillForm(['title' => 'Reopened project'])
        ->call('save')
        ->assertHasNoFormErrors();

    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::CaseStudy)[0];
    $dom = new DOMDocument;
    $html = $public->content;
    if ($html === null || $html === '') {
        throw new RuntimeException('The public adapter returned no formatted content.');
    }

    @$dom->loadHTML($html);
    $xpath = new DOMXPath($dom);

    expect($xpath->evaluate('string(//h2)'))->toBe('Project outcome')
        ->and($xpath->evaluate('string(//ul/li/p/a/@href)'))->toBe('https://example.com/guide')
        ->and($xpath->evaluate('string(//ul/li/p/a)'))->toBe('Read the guide')
        ->and($xpath->evaluate('count(//p)'))->toBe(2.0)
        ->and($public->title)->toBe('Reopened project')
        ->and(array_keys($public->toArray()))->not->toContain('id', 'site_id', 'site_scope_key')
        ->and($public->content)->not->toContain('style=', 'class=', 'data-', 'wire:', '<script');
});

it('rejects forged site and shared scope on create and update', function (string $target): void {
    $siteA = $this->createStructuredContentSite('Editor site');
    $siteB = $this->createStructuredContentSite('Forbidden site');
    $item = StructuredContentItem::factory()->create(['site_id' => $siteA]);
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$siteA]));
    $scope = match ($target) {
        'other' => $siteB,
        'missing' => null,
        default => 'all',
    };

    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'service')
        ->fillForm(['title' => 'Forged create', 'site_id' => $scope])
        ->call('create')
        ->assertHasFormErrors(['site_id']);

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['title' => 'Forged update', 'site_id' => $scope])
        ->call('save')
        ->assertHasFormErrors(['site_id']);

    expect(StructuredContentItem::query()->count())->toBe(1)
        ->and($item->refresh()->site_id)->toBe($siteA)
        ->and($item->title)->not->toBe('Forged update');
})->with(['other', 'all', 'missing']);

it('requires a deliberate shared choice and preserves legacy global scope on edit', function (): void {
    $siteA = $this->createStructuredContentSite('A site');
    $siteB = $this->createStructuredContentSite('B site');
    $legacy = StructuredContentItem::factory()->create(['site_id' => null]);

    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'service')
        ->fillForm(['title' => 'Shared service', 'status' => 'published'])
        ->call('create')
        ->assertHasFormErrors(['site_id']);

    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'service')
        ->fillForm(['title' => 'Shared service', 'status' => 'published', 'site_id' => 'all'])
        ->call('create')
        ->assertHasNoFormErrors();

    Livewire::test(EditStructuredContentItem::class, ['record' => $legacy->getRouteKey()])
        ->assertFormSet(['site_id' => 'all'])
        ->fillForm(['title' => 'Legacy shared item'])
        ->call('save')
        ->assertHasNoFormErrors();

    expect($legacy->refresh()->site_id)->toBeNull()
        ->and(StructuredContentItem::query()->where('title', 'Shared service')->sole()->site_id)->toBeNull()
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service, $siteA))->toHaveCount(1)
        ->and(BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service, $siteB))->toHaveCount(1);
});

it('does not let a site editor take ownership of an existing shared item', function (): void {
    $siteA = $this->createStructuredContentSite('Assigned site');
    $item = StructuredContentItem::factory()->create(['site_id' => null]);
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$siteA]));

    expect(fn (): StructuredContentItem => UpdateStructuredContentItemAction::run($item, new StructuredContentItemData(
        type: $item->type,
        title: 'Stolen shared item',
        siteId: $siteA,
    )))->toThrow(AuthorizationException::class);

    expect($item->refresh()->site_id)->toBeNull();
});

it('retains legacy portable HTML when reopening and editing unrelated fields', function (): void {
    $legacy = '<h4>Existing heading</h4><p>Text <strong>bold</strong> <em>emphasis</em><br>next line '
        . '<a href="/guide">Guide</a> <code>inline</code></p><blockquote><p>Quote</p></blockquote>'
        . '<ol start="3"><li><p>Third item</p></li></ol><pre><code>sample &lt;code&gt;</code></pre>';
    $item = StructuredContentItem::factory()->create(['content' => $legacy, 'summary' => '<p>Existing summary</p>']);

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->fillForm(['title' => 'Changed title'])
        ->call('save')
        ->assertHasNoFormErrors();

    $dom = new DOMDocument;
    @$dom->loadHTML($item->refresh()->content ?? '');
    $xpath = new DOMXPath($dom);
    expect($xpath->evaluate('string(//h4)'))->toBe('Existing heading')
        ->and($xpath->evaluate('string(//ol/@start)'))->toBe('3')
        ->and($xpath->evaluate('string(//a/@href)'))->toBe('/guide')
        ->and($xpath->evaluate('string(//pre/code)'))->toBe('sample <code>')
        ->and($xpath->evaluate('count(//br | //strong | //em | //blockquote)'))->toBe(4.0)
        ->and($item->summary)->toBe('<p>Existing summary</p>');
});

it('requires resource permission even when the actor has an assigned site', function (): void {
    $site = $this->createStructuredContentSite('Assigned without permission');
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$site], permissionsAllowed: false));

    Livewire::test(CreateStructuredContentItem::class)->assertForbidden();
    expect(StructuredContentItem::query()->count())->toBe(0);
});

it('does not let site editors open shared or other site items for editing', function (): void {
    $siteA = $this->createStructuredContentSite('Assigned site');
    $siteB = $this->createStructuredContentSite('Unassigned site');
    $shared = StructuredContentItem::factory()->create(['site_id' => null]);
    $other = StructuredContentItem::factory()->create(['site_id' => $siteB]);
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$siteA]));

    Livewire::test(EditStructuredContentItem::class, ['record' => $shared->getRouteKey()])->assertForbidden();
    $this->get(StructuredContentItemResource::getUrl('edit', ['record' => $other]))->assertNotFound();
    Livewire::test(ListStructuredContentItems::class)
        ->assertCanSeeTableRecords([$shared])
        ->assertCanNotSeeTableRecords([$other]);
});

it('checks each record scope for bulk mutations', function (string $operation): void {
    $site = $this->createStructuredContentSite('Bulk editor site');
    $shared = StructuredContentItem::factory()->create(['site_id' => null]);
    $own = StructuredContentItem::factory()->create(['site_id' => $site]);
    if ($operation !== 'delete') {
        $shared->delete();
        $own->delete();
    }

    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$site]));

    $page = Livewire::test(ListStructuredContentItems::class);
    if ($operation !== 'delete') {
        $page->filterTable('trashed', 'only');
    }

    $page->callTableBulkAction($operation, [$shared, $own]);

    $storedShared = StructuredContentItem::withTrashed()->whereKey($shared->getKey())->first();
    expect($storedShared)->not->toBeNull()
        ->and($storedShared?->trashed())->toBe($operation !== 'delete');

    $storedOwn = StructuredContentItem::withTrashed()->whereKey($own->getKey())->first();
    if ($operation === 'forceDelete') {
        expect($storedOwn)->toBeNull();
    } else {
        expect($storedOwn?->trashed())->toBe($operation === 'delete');
    }
})->with(['delete', 'restore', 'forceDelete']);

it('does not reorder shared items through a mixed scope list', function (): void {
    $site = $this->createStructuredContentSite('Reorder editor site');
    $shared = StructuredContentItem::factory()->create(['site_id' => null, 'sort_order' => 77]);
    $own = StructuredContentItem::factory()->create(['site_id' => $site, 'sort_order' => 78]);
    $this->actingAs(new StructuredContentGlobalTestUser(global: false, assignedSiteIds: [$site]));

    Livewire::test(ListStructuredContentItems::class)->call('reorderTable', [$own->getKey(), $shared->getKey()]);

    expect($shared->refresh()->sort_order)->toBe(77);
});

it('sanitises forged editor HTML before exposing it publicly', function (string $field): void {
    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'service')
        ->fillForm([
            'site_id' => 'all', 'title' => 'Safe service', 'status' => 'published',
            $field => '<script>alert(1)</script><p class="theme" id="secret" data-editor="secret" onclick="alert(1)">Safe text '
                . '<a href="javascript:alert(1)">unsafe link</a></p>',
        ])
        ->call('create')
        ->assertHasNoFormErrors();

    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Service)[0];
    $html = $public->{$field};
    expect($html)->toContain('Safe text', 'unsafe link')
        ->not->toContain('<script', 'class=', 'style=', 'id=', 'data-editor', 'onclick', 'javascript:');
})->with(['summary', 'content']);

it('rejects presentation styles forged into editor document state', function (string $field): void {
    Livewire::test(CreateStructuredContentItem::class)
        ->set('data.type', 'service')
        ->fillForm([
            'site_id' => 'all', 'title' => 'Designed service',
            $field => ['type' => 'doc', 'content' => [
                ['type' => 'paragraph', 'attrs' => ['textAlign' => 'center'], 'content' => [['type' => 'text', 'text' => 'Designed']]],
            ]],
        ])
        ->call('create')
        ->assertHasFormErrors([$field]);

    expect(StructuredContentItem::query()->count())->toBe(0);
})->with(['summary', 'content']);

it('rejects forged editor documents without changing public content', function (string $field, array $node, string $encoding): void {
    $item = StructuredContentItem::factory()->create([
        'status' => 'published',
        'content' => '<p>Safe content</p>',
        'summary' => '<p>Safe summary</p>',
    ]);

    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->set('data.' . $field, $encoding === 'json' ? json_encode(['type' => 'doc', 'content' => [$node]], JSON_THROW_ON_ERROR) : ['type' => 'doc', 'content' => [$node]])
        ->call('save')
        ->assertHasFormErrors([$field]);

    $public = BuildPublicStructuredContentItemsAction::run($item->type)[0];
    expect($public->content)->toBe('<p>Safe content</p>')
        ->and($public->summary)->toBe('<p>Safe summary</p>');
})->with(['content', 'summary'])->with([
    'raw HTML merge tag' => [['type' => 'rawHtmlMergeTag', 'html' => '<p x-data="{}" x-init="alert(1)" wire:click="doSomething">Text</p>']],
    'unsupported attribute' => [['type' => 'paragraph', 'attrs' => ['x-init' => 'alert(1)'], 'content' => [['type' => 'text', 'text' => 'Text']]]],
    'unsupported mark' => [['type' => 'paragraph', 'content' => [['type' => 'text', 'text' => 'Text', 'marks' => [['type' => 'underline']]]]]],
])->with(['array', 'json']);

it('preserves legacy list numbering through title only saves and reopening', function (string $field, int $start, int $value): void {
    $item = StructuredContentItem::factory()->create([
        'status' => 'published',
        $field => '<ol reversed start="' . $start . '" type="I"><li value="' . $value . '"><p>Tenth</p></li><li><p>Ninth</p></li></ol>',
    ]);

    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])
            ->call('save')
            ->assertHasNoFormErrors();

        $public = BuildPublicStructuredContentItemsAction::run($item->type)[0];
        $dom = new DOMDocument;
        @$dom->loadHTML($public->{$field} ?? '');
        $xpath = new DOMXPath($dom);
        expect($xpath->evaluate('count(//ol[@reversed])'))->toBe(1.0)
            ->and($xpath->evaluate('string(//ol/@start)'))->toBe((string) $start)
            ->and($xpath->evaluate('string(//ol/@type)'))->toBe('I')
            ->and($xpath->evaluate('string(//li[1]/@value)'))->toBe((string) $value)
            ->and($xpath->evaluate('string(//li[2])'))->toBe('Ninth');
    }
})->with(['content', 'summary'])->with([[3, 10], [0, 0], [-3, -10]]);

it('retains nested ordered list structure through title only saves and reopening', function (string $field): void {
    $item = StructuredContentItem::factory()->published()->create([
        $field => '<ol><li><p>Outer one</p><ol start="5"><li><p>Inner</p></li></ol></li><li><p>Outer two</p></li></ol>',
    ]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $public = BuildPublicStructuredContentItemsAction::run($item->type)[0];
        $html = $public->{$field} ?? '';
        $dom = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
        expect(substr_count($html, '</ol>'))->toBe(2)
            ->and($dom->querySelectorAll('body > ol > li')->length)->toBe(2)
            ->and($dom->querySelector('body > ol > li:nth-child(2) > p')?->textContent)->toBe('Outer two')
            ->and($dom->querySelector('body > ol > li > ol')?->getAttribute('start'))->toBe('5')
            ->and($dom->querySelectorAll('body > li')->length)->toBe(0);
    }
})->with(['content', 'summary']);

it('opens saves and reopens legacy text resembling JSON without losing its text', function (string $field, string $legacy, string $expected): void {
    $item = StructuredContentItem::factory()->published()->create([$field => $legacy]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->assertSuccessful()->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $public = BuildPublicStructuredContentItemsAction::run($item->type)[0];
        $dom = HTMLDocument::createFromString($public->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        expect($dom->body?->textContent)->toBe($expected)
            ->and($item->refresh()->title)->toBe($title);
        if ($legacy === '"Hello <strong>world</strong>"') {
            expect($dom->querySelector('strong')?->textContent)->toBe('world');
        }
    }
})->with(['content', 'summary'])->with([
    ['123', '123'], ['"Hello"', '"Hello"'], ['{}', '{}'], ['[]', '[]'], ['null', 'null'], ['true', 'true'],
    ['{"type":"doc","content":[]}', '{"type":"doc","content":[]}'],
    ['"Hello &amp; goodbye"', '"Hello & goodbye"'],
    ['"Hello <strong>world</strong>"', '"Hello world"'],
]);

it('retains semantic direction and language through title only saves and reopening', function (string $field, string $direction, string $language): void {
    $item = StructuredContentItem::factory()->published()->create([
        $field => '<p dir="rtl" lang="ar">Arabic: مرحبا 123</p>'
            . '<h2 dir="rtl" lang="ar">Heading</h2><blockquote dir="rtl" lang="ar"><p>Quote</p></blockquote>'
            . '<ul dir="rtl" lang="ar"><li dir="rtl" lang="ar"><p>Bullet</p></li></ul>'
            . '<ol dir="rtl" lang="ar"><li dir="rtl" lang="ar"><p>Number</p></li></ol>'
            . '<pre dir="rtl" lang="ar"><code>Code</code></pre>'
            . '<p><strong dir="rtl" lang="ar">Bold</strong> <em dir="rtl" lang="ar">Emphasis</em> '
            . '<code dir="rtl" lang="ar">Inline code</code><br dir="rtl" lang="ar">'
            . '<a href="https://example.com" dir="rtl" lang="ar">Link</a></p>',
    ]);
    $item->update([$field => str_replace(['dir="rtl"', 'lang="ar"'], ['dir="' . $direction . '"', 'lang="' . $language . '"'], $item->{$field} ?? '')]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $public = BuildPublicStructuredContentItemsAction::run($item->type)[0];
        expect($public->{$field})->not->toContain('capell-empty-language-');
        $dom = HTMLDocument::createFromString($public->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        $paragraph = $dom->querySelector('p');
        expect($paragraph?->getAttribute('dir'))->toBe($direction)
            ->and($paragraph?->getAttribute('lang'))->toBe($language)
            ->and($paragraph?->textContent)->toBe('Arabic: مرحبا 123');
        foreach (['h2', 'blockquote', 'ul', 'ul > li', 'ol', 'ol > li', 'pre', 'strong', 'em', 'p > code', 'br', 'a'] as $selector) {
            $element = $dom->querySelector($selector);
            expect($element?->getAttribute('dir'))->toBe($direction, $selector)
                ->and($element?->getAttribute('lang'))->toBe($language, $selector);
        }
    }
})->with(['content', 'summary'])->with([['rtl', 'ar'], ['RTL', 'ar'], ['auto', ''], ['ltr', 'en-GB']]);

it('retains legacy mixed list blocks through saves and reopening', function (string $field, string $html, array $selectors): void {
    $item = StructuredContentItem::factory()->published()->create([$field => $html]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        foreach ($selectors as $selector => $text) {
            expect($dom->querySelector($selector)?->textContent)->toBe($text, $selector);
        }
    }
})->with(['content', 'summary'])->with([
    'bare nested ordered' => ['<ol><li>Outer one<ol start="5"><li>Inner</li></ol></li><li>Outer two</li></ol>', ['ol > li > p' => 'Outer one', 'ol > li > ol[start="5"] > li > p' => 'Inner', 'body > ol > li:nth-child(2) > p' => 'Outer two']],
    'marked nested bullet' => ['<ul><li><strong>Intro</strong><br>line<ul><li><em>Inner</em></li></ul>After<p>Detail</p>Tail</li></ul>', ['li > p > strong' => 'Intro', 'li > ul > li > p > em' => 'Inner', 'body > ul > li > p:nth-of-type(2)' => 'After', 'body > ul > li > p:nth-of-type(3)' => 'Detail', 'body > ul > li > p:nth-of-type(4)' => 'Tail']],
    'subsequent paragraph' => ['<ul><li>Intro<p>Detail</p></li></ul>', ['li > p:first-child' => 'Intro', 'li > p:nth-child(2)' => 'Detail']],
    'block first' => ['<ul><li><ul><li>Inner</li></ul>Tail</li></ul>', ['body > ul > li > ul > li > p' => 'Inner', 'body > ul > li > p:last-child' => 'Tail']],
    'bare quote and root' => ['Intro<blockquote>Quote<strong> bold</strong><p>Detail</p>Tail</blockquote>After', ['body > p:first-child' => 'Intro', 'blockquote > p:first-child' => 'Quote bold', 'blockquote > p:nth-child(2)' => 'Detail', 'blockquote > p:last-child' => 'Tail', 'body > p:last-child' => 'After']],
]);

it('retains all accepted legacy attributes through saves and reopening', function (string $field, string $html): void {
    $original = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    $item = StructuredContentItem::factory()->published()->create([$field => $html]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        $indices = [];
        foreach ($original->body?->querySelectorAll('*') ?? [] as $element) {
            $index = $indices[$element->tagName] ?? 0;
            $indices[$element->tagName] = $index + 1;
            $saved = $dom->querySelectorAll($element->tagName)->item($index);
            $this->assertInstanceOf(Element::class, $saved);
            foreach ($element->attributes as $attribute) {
                if ($attribute->name === 'reversed') {
                    expect($saved->hasAttribute('reversed'))->toBeTrue();
                } elseif ($attribute->name === 'dir' && $attribute->value === '') {
                    expect($saved->getAttribute('dir'))->toBeIn([null, '']);
                } else {
                    expect($saved->getAttribute($attribute->name))->toBe($attribute->value, $element->tagName . '@' . $attribute->name);
                }
            }
        }

        expect($dom->body?->textContent)->toBe($original->body?->textContent);
    }
})->with(['content', 'summary'])->with([
    'inner code' => ['<pre dir="ltr" lang="en" title="Example"><code dir="rtl" lang="ar" translate="no">Arabic: مرحبا 123</code></pre>'],
    'link' => ['<p><a href="/guide" title="Help" hreflang="fr" download="guide.pdf" rel="help" target="_self" type="text/html" referrerpolicy="no-referrer">Guide</a></p>'],
    'global semantic' => ['<p translate="no" title="API" role="note" aria-label="API reference" hidden="" spellcheck="false" contenteditable="false" accesskey="a" tabindex="0" draggable="false" custom="preserved">OpenAI API</p>'],
    'anchor without href' => ['<p><a title="Destination" name="chapter">Chapter</a></p>'],
    'nonstandard direction' => ['<p dir="inherit">Inherited</p>'],
    'nonstandard numbering' => ['<ol start="" type="z"><li value="">Entry</li></ol>'],
    'empty direction' => ['<p dir="">Inherited</p>'],
    'empty attributes' => ['<p title="" lang=""><a href="/" download="" title="">Home</a></p>'],
    'all elements' => ['<h2 title="Heading">Heading</h2><h3 title="Subheading">Subheading</h3><h4 title="Detail">Detail</h4><blockquote cite="/source" translate="no"><p>Quote</p></blockquote><ul title="Bullets"><li title="Item"><p>Item</p></li></ul><ol start="0" reversed="" type="I" title="Numbers"><li value="0"><p>Zero</p></li></ol><p><strong title="Bold">Bold</strong> <em title="Emphasis">Emphasis</em> <code title="Inline" translate="no">inline</code><br title="Break">Next</p>'],
]);

it('retains formatted soft breaks through title saves and reopening', function (string $field, string $html, string $selector): void {
    $item = StructuredContentItem::factory()->published()->create([$field => $html]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        expect($dom->querySelector($selector))->not->toBeNull()
            ->and($dom->body?->textContent)->toBe('BoldNext');
    }
})->with(['content', 'summary'])->with([
    'bold' => ['<p><strong>Bold<br>Next</strong></p>', 'strong > br'],
    'italic' => ['<p><em>Bold<br>Next</em></p>', 'em > br'],
    'link' => ['<p><a href="/guide">Bold<br>Next</a></p>', 'a[href="/guide"] > br'],
    'code' => ['<p><code>Bold<br>Next</code></p>', 'code > br'],
]);

it('retains empty semantic anchors through title saves and reopening', function (string $field, string $html, array $attributes): void {
    $item = StructuredContentItem::factory()->published()->create([$field => $html]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        $anchor = $dom->querySelector('a');
        $this->assertInstanceOf(Element::class, $anchor);
        foreach ($attributes as $name => $value) {
            expect($anchor->getAttribute($name))->toBe($value);
        }

        expect($anchor->textContent)->toBe('')
            ->and($dom->body?->textContent)->toBe('StartEnd');
    }
})->with(['content', 'summary'])->with([
    'named' => ['<p>Start<a name="chapter"></a>End</p>', ['name' => 'chapter']],
    'linked metadata' => ['<p>Start<a href="#chapter" name="" title="" lang="fr" translate="no"></a>End</p>', ['href' => '#chapter', 'name' => '', 'title' => '', 'lang' => 'fr', 'translate' => 'no']],
    'bare root' => ['Start<a name="chapter"></a>End', ['name' => 'chapter']],
    'bare list' => ['<ul><li>Start<a name="chapter"></a>End</li></ul>', ['name' => 'chapter']],
    'marked' => ['<p><strong>Start<a name="chapter"></a>End</strong></p>', ['name' => 'chapter']],
]);

it('round trips the complete portable legacy HTML corpus through both configured fields', function (string $field, string $html): void {
    EnsurePortableContentHtmlAction::run($html, $field);
    $original = HTMLDocument::createFromString($html, LIBXML_NOERROR, 'UTF-8');
    $item = StructuredContentItem::factory()->published()->create([$field => $html]);
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        expect($dom->body?->textContent)->toBe($original->body?->textContent);
        foreach (['a', 'strong', 'em', 'code', 'br', 'h2', 'h3', 'h4', 'blockquote', 'ul', 'ol', 'li', 'pre'] as $tag) {
            expect($dom->querySelectorAll($tag)->length)->toBe($original->querySelectorAll($tag)->length, $tag);
        }

        $indices = [];
        foreach ($original->body?->querySelectorAll('*') ?? [] as $element) {
            $index = $indices[$element->tagName] ?? 0;
            $indices[$element->tagName] = $index + 1;
            $saved = $dom->querySelectorAll($element->tagName)->item($index);
            $this->assertInstanceOf(Element::class, $saved, $element->tagName);
            foreach ($element->attributes as $attribute) {
                if ($attribute->name === 'reversed') {
                    expect($saved->hasAttribute('reversed'))->toBeTrue();
                } elseif ($attribute->name === 'dir' && $attribute->value === '') {
                    expect($saved->getAttribute('dir'))->toBeIn([null, '']);
                } else {
                    expect($saved->getAttribute($attribute->name))->toBe($attribute->value, $element->tagName . '@' . $attribute->name);
                }
            }
        }
    }
})->with(['content', 'summary'])->with(function (): iterable {
    $json = file_get_contents(__DIR__ . '/../../Fixtures/portable-legacy-html.json');
    if ($json === false) {
        throw new RuntimeException('Unable to read the portable legacy HTML corpus.');
    }

    $corpus = json_decode($json, true, flags: JSON_THROW_ON_ERROR);
    if (! is_array($corpus)) {
        throw new UnexpectedValueException('The portable legacy HTML corpus must be a map.');
    }

    foreach ($corpus as $name => $html) {
        if (! is_string($html)) {
            throw new UnexpectedValueException('Portable legacy HTML fixtures must be strings.');
        }

        yield $name => [$html];
    }
});

it('retains older in-flight code attribute documents through saves and reopening', function (string $field): void {
    $item = StructuredContentItem::factory()->published()->create();
    $document = ['type' => 'doc', 'content' => [[
        'type' => 'codeBlock', 'attrs' => ['codeAttributes' => ['lang' => 'fr', 'title' => '']],
        'content' => [['type' => 'text', 'text' => "First\nSecond"]],
    ]]];
    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        // The browser replaces the document; fillForm() merges nested keys from the old paragraph.
        ->set('data.' . $field, $document)->call('save')->assertHasNoFormErrors();
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        $code = $dom->querySelector('pre > code');
        $this->assertInstanceOf(Element::class, $code);
        expect($code->textContent)->toBe("First\nSecond")
            ->and($code->getAttribute('lang'))->toBe('fr')
            ->and($code->getAttribute('title'))->toBe('');
    }
})->with(['content', 'summary']);

it('uses the declared inline element default when a document omits its tag', function (string $field, string $type): void {
    $item = StructuredContentItem::factory()->published()->create();
    $document = ['type' => 'doc', 'content' => [['type' => 'paragraph', 'content' => [
        ['type' => 'text', 'text' => 'Start'],
        ['type' => $type, 'attrs' => ['portableAttributes' => ['name' => 'chapter']]],
        ['type' => 'text', 'text' => 'End'],
    ]]]];
    Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
        ->set('data.' . $field, $document)->call('save')->assertHasNoFormErrors();
    foreach (['Changed title', 'Reopened title'] as $title) {
        Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()])
            ->fillForm(['title' => $title])->call('save')->assertHasNoFormErrors();
        $dom = HTMLDocument::createFromString($item->refresh()->{$field} ?? '', LIBXML_NOERROR, 'UTF-8');
        expect($dom->querySelector('a')?->getAttribute('name'))->toBe('chapter')
            ->and($dom->querySelectorAll('a')->length)->toBe(1)
            ->and($dom->body?->textContent)->toBe('StartEnd');
    }
})->with(['content', 'summary'])->with(['emptyInline', 'nestedInline']);

it('shows saved availability on the real editor and previews saved content rather than unsaved form state', function (): void {
    $this->actingAs(new StructuredContentGlobalTestUser);
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::Service)->create([
        'title' => 'Saved service',
        'published_at' => now()->addDay(),
    ]);
    $component = Livewire::test(EditStructuredContentItem::class, ['record' => $item->getRouteKey()]);
    $component->assertSee(DescribeStructuredContentAvailabilityAction::run($item));
    $component->fillForm(['title' => 'Unsaved service'])
        ->assertActionVisible('preview')
        ->assertActionEnabled('preview')
        ->mountAction('preview')
        ->assertActionMounted('preview');
    $dom = HTMLDocument::createFromString($component->getMountedActionModalHtml(), LIBXML_NOERROR, 'UTF-8');
    expect($dom->querySelector('article h2')?->textContent)->toBe('Saved service')
        ->and($dom->querySelector('article a'))->toBeNull();
    $component->assertMountedActionModalSee(__('capell-structured-content-library::admin.preview_help'));
});
