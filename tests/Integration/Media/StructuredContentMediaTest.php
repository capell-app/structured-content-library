<?php

declare(strict_types=1);

use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Media;
use Capell\Core\Support\TableName;
use Capell\StructuredContentLibrary\Actions\BuildPublicStructuredContentItemsAction;
use Capell\StructuredContentLibrary\Data\PublicStructuredContentMediaData;
use Capell\StructuredContentLibrary\Data\StructuredContentDefinitionData;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Spatie\MediaLibrary\MediaCollections\MediaCollection;

require_once dirname(__DIR__, 2) . '/StructuredContentLibraryTestCase.php';

uses(StructuredContentLibraryTestCase::class);

beforeEach(function (): void {
    Config::set('filesystems.disks.public', ['driver' => 'local', 'root' => storage_path('app/public'), 'url' => '/storage', 'visibility' => 'public']);
    Config::set('filesystems.disks.local', ['driver' => 'local', 'root' => storage_path('app/private'), 'visibility' => 'private']);
});

/**
 * @param  array<string, mixed>  $attributes
 */
function structuredContentMedia(StructuredContentItem $item, MediaCollectionEnum $collection, array $attributes = []): Media
{
    return Media::factory()->model($item)->create([
        'collection_name' => $collection->value,
        'name' => 'Ada Example',
        'file_name' => 'ada-example.png',
        'mime_type' => 'image/png',
        'custom_properties' => ['width' => 640, 'height' => 480],
        ...$attributes,
    ]);
}

it('gives each picture-carrying type a media collection and alt field', function (StructuredContentType $type, ?MediaCollectionEnum $collection): void {
    expect(StructuredContentDefinitionData::forType($type)->mediaCollection())->toBe($collection);
})->with([
    'case study' => [StructuredContentType::CaseStudy, MediaCollectionEnum::Image],
    'testimonial' => [StructuredContentType::Testimonial, MediaCollectionEnum::Image],
    'team member' => [StructuredContentType::TeamMember, MediaCollectionEnum::Image],
    'service' => [StructuredContentType::Service, MediaCollectionEnum::Image],
    'resource' => [StructuredContentType::Resource, MediaCollectionEnum::Image],
    'partner' => [StructuredContentType::Partner, MediaCollectionEnum::Logo],
    'logo' => [StructuredContentType::Logo, MediaCollectionEnum::Logo],
    'faq' => [StructuredContentType::Faq, null],
    'location' => [StructuredContentType::Location, null],
]);

it('registers single-file image and logo collections on the record', function (): void {
    $collections = collect(new StructuredContentItem()->getRegisteredMediaCollections())->keyBy('name');

    expect($collections->keys()->all())->toEqualCanonicalizing([MediaCollectionEnum::Image->value, MediaCollectionEnum::Logo->value])
        ->and($collections->every(static fn (MediaCollection $collection): bool => $collection->singleFile))->toBeTrue();
});

it('projects a team member photo into public data with its size and the record alt text', function (): void {
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::TeamMember)->create([
        'payload' => new StructuredContentPayloadData(role: 'Support lead', imageAlt: 'Ada smiling at her desk'),
    ]);
    $media = structuredContentMedia($item, MediaCollectionEnum::Image);

    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::TeamMember)[0];

    expect($public->image)->toBeInstanceOf(PublicStructuredContentMediaData::class)
        ->and($public->image?->url)->toBe($media->getUrl())
        ->and($public->image?->width)->toBe(640)
        ->and($public->image?->height)->toBe(480)
        ->and($public->image?->alt)->toBe('Ada smiling at her desk')
        ->and($public->logo)->toBeNull();

    $encoded = json_encode($public->toArray(), JSON_THROW_ON_ERROR);

    expect($encoded)->not->toContain($media->uuid)
        ->and($encoded)->not->toContain(storage_path())
        ->and($encoded)->not->toContain('"disk"')
        ->and(array_keys($public->image?->toArray() ?? []))->toBe(['url', 'width', 'height', 'alt']);
});

it('projects a partner logo and falls back to the media library alt text', function (): void {
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::Partner)->create([
        'payload' => new StructuredContentPayloadData(company: 'Acme'),
    ]);
    $media = structuredContentMedia($item, MediaCollectionEnum::Logo);
    // Written directly: the Translation model's activity log needs an application the package tests do not boot.
    DB::table(TableName::resolve('translations'))->insert([
        'language_id' => structured_content_media_test_int(DB::table('sites')->where('id', $this->createStructuredContentSite('Alt text site'))->value('language_id')),
        'translatable_type' => $media->getMorphClass(),
        'translatable_id' => $media->getKey(),
        'title' => $media->name,
        'meta' => json_encode(['alt' => 'Acme wordmark'], JSON_THROW_ON_ERROR),
        'created_at' => now(),
        'updated_at' => now(),
    ]);

    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Partner)[0];

    expect($public->logo?->alt)->toBe('Acme wordmark')
        ->and($public->image)->toBeNull();
});

it('serves a cached picture without reading media again', function (): void {
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::Logo)->create();
    structuredContentMedia($item, MediaCollectionEnum::Logo);

    $first = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Logo)[0];
    $cached = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Logo)[0];

    expect($cached->logo?->toArray())->toBe($first->logo?->toArray())
        ->and($cached->logo)->toBeInstanceOf(PublicStructuredContentMediaData::class);
});

it('withholds pictures that are private, not images, or filed under another type collection', function (array $attributes, MediaCollectionEnum $collection): void {
    $item = StructuredContentItem::factory()->published()->type(StructuredContentType::Testimonial)->create();
    structuredContentMedia($item, $collection, $attributes);

    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::Testimonial)[0];

    expect($public->image)->toBeNull()
        ->and($public->logo)->toBeNull();
})->with([
    'private disk' => [['disk' => 'local', 'conversions_disk' => 'local'], MediaCollectionEnum::Image],
    'not an image' => [['mime_type' => 'application/pdf', 'file_name' => 'brochure.pdf'], MediaCollectionEnum::Image],
    'logo on a testimonial' => [[], MediaCollectionEnum::Logo],
]);

it('never surfaces another record picture', function (): void {
    $withPhoto = StructuredContentItem::factory()->published()->type(StructuredContentType::TeamMember)->create(['sort_order' => 1]);
    StructuredContentItem::factory()->published()->type(StructuredContentType::TeamMember)->create(['sort_order' => 2]);
    structuredContentMedia($withPhoto, MediaCollectionEnum::Image);

    $public = BuildPublicStructuredContentItemsAction::run(StructuredContentType::TeamMember);

    expect($public[0]->image)->toBeInstanceOf(PublicStructuredContentMediaData::class)
        ->and($public[1]->image)->toBeNull();
});

function structured_content_media_test_int(mixed $value): int
{
    return is_numeric($value) ? (int) $value : 0;
}
