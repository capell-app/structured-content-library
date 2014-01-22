<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\SeedStructuredContentScreenshotFixtureAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;

uses(StructuredContentLibraryTestCase::class);

it('reaches an empty screenshot state and restores the same six records', function (): void {
    withStructuredContentScreenshotFixtureEnvironment(function (): void {
        SeedStructuredContentScreenshotFixtureAction::run();
        $ids = StructuredContentItem::query()->orderBy('id')->pluck('id')->all();

        expect($ids)->toHaveCount(6);
        SeedStructuredContentScreenshotFixtureAction::prepareEmpty();
        expect(StructuredContentItem::query()->count())->toBe(0)
            ->and(StructuredContentItem::onlyTrashed()->count())->toBe(6);

        SeedStructuredContentScreenshotFixtureAction::run();
        expect(StructuredContentItem::query()->orderBy('id')->pluck('id')->all())->toBe($ids);
    });
});

it('refuses to empty foreign content', function (): void {
    withStructuredContentScreenshotFixtureEnvironment(function (): void {
        $item = SeedStructuredContentScreenshotFixtureAction::run();
        $item->update(['slug' => 'customer-owned-testimonial']);

        expect(fn () => SeedStructuredContentScreenshotFixtureAction::prepareEmpty())->toThrow(RuntimeException::class, 'non-fixture content');
        expect(StructuredContentItem::query()->count())->toBe(6);
    });
});

function withStructuredContentScreenshotFixtureEnvironment(Closure $callback): void
{
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('APP_ENV=testing');

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
        putenv('APP_ENV');
    }
}

it('seeds a published testimonial plus supporting typed records for the disposable screenshot app', function (): void {
    withStructuredContentScreenshotFixtureEnvironment(function (): void {
        $first = SeedStructuredContentScreenshotFixtureAction::run();
        $second = SeedStructuredContentScreenshotFixtureAction::run();

        expect($second->getKey())->toBe($first->getKey())
            ->and(StructuredContentItem::query()->count())->toBe(6)
            ->and(StructuredContentItem::query()->orderBy('id')->value('id'))->toBe($first->getKey())
            ->and($second->status)->toBe(StructuredContentStatus::Published)
            ->and($second->payload?->quote)->toBe('The content workflow gives every team a clear publishing path.');
    });
});

it('refuses to seed outside the explicit disposable screenshot environment', function (): void {
    expect(fn (): StructuredContentItem => SeedStructuredContentScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});
