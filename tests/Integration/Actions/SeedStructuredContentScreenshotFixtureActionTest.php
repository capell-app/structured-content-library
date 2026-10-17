<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\SeedStructuredContentScreenshotFixtureAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Auth\User;

uses(StructuredContentLibraryTestCase::class);

beforeEach(function (): void {
    $actor = auth()->user();
    throw_unless($actor instanceof Model, RuntimeException::class, 'Expected the authenticated fixture actor.');
    $actor->forceFill(['email' => 'admin@example.com']);
});

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

it('preserves a foreign record occupying a screenshot slug in both fixture states', function (): void {
    withStructuredContentScreenshotFixtureEnvironment(function (): void {
        $item = SeedStructuredContentScreenshotFixtureAction::run();
        $item->update(['title' => 'A customer authored testimonial']);

        $before = $item->fresh()?->getRawOriginal();

        expect(fn (): StructuredContentItem => SeedStructuredContentScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'slug belongs to another record');
        expect(fn () => SeedStructuredContentScreenshotFixtureAction::prepareEmpty())->toThrow(RuntimeException::class, 'slug belongs to another record');
        expect($item->fresh()?->getRawOriginal())->toBe($before)
            ->and(StructuredContentItem::query()->count())->toBe(6);
    });
});

it('rejects a foreign supporting item before creating the testimonial', function (): void {
    withStructuredContentScreenshotFixtureEnvironment(function (): void {
        $foreign = StructuredContentItem::factory()->create(['slug' => 'capell-screenshot-team-member', 'title' => 'Customer team member']);
        $before = $foreign->fresh()?->getRawOriginal();

        expect(fn (): StructuredContentItem => SeedStructuredContentScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'slug belongs to another record');
        expect($foreign->fresh()?->getRawOriginal())->toBe($before)
            ->and(StructuredContentItem::query()->count())->toBe(1);
    });
});

function withStructuredContentScreenshotFixtureEnvironment(Closure $callback): void
{
    $previousEmail = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL');
    $actor = auth()->user();
    if ($actor !== null) {
        $email = data_get($actor, 'email');
        throw_unless(is_string($email), RuntimeException::class, 'Expected the capture actor email.');
        putenv('CAPELL_SCREENSHOT_ADMIN_EMAIL=' . $email);
    }

    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('APP_ENV=testing');

    try {
        $callback();
    } finally {
        putenv('CAPELL_SCREENSHOT_FIXTURE');
        putenv('CAPELL_SCREENSHOT_APP_PATH');
        putenv('APP_ENV');
        putenv($previousEmail === false ? 'CAPELL_SCREENSHOT_ADMIN_EMAIL' : 'CAPELL_SCREENSHOT_ADMIN_EMAIL=' . $previousEmail);
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

it('refuses an authenticated actor other than the selected capture identity before writes', function (): void {
    $previous = [];
    foreach (['CAPELL_SCREENSHOT_FIXTURE', 'CAPELL_SCREENSHOT_APP_PATH', 'CAPELL_SCREENSHOT_ADMIN_EMAIL'] as $key) {
        $previous[$key] = getenv($key);
    }

    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    putenv('CAPELL_SCREENSHOT_APP_PATH=' . base_path());
    putenv('CAPELL_SCREENSHOT_ADMIN_EMAIL=selected-capture@example.test');
    $actor = new User;
    $actor->forceFill(['id' => 987654, 'email' => 'another-actor@example.test']);
    $this->actingAs($actor);
    $before = StructuredContentItem::query()->count();

    try {
        expect(fn (): StructuredContentItem => SeedStructuredContentScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'selected capture actor');
        expect(StructuredContentItem::query()->count())->toBe($before);
    } finally {
        foreach ($previous as $key => $value) {
            putenv($value === false ? $key : $key . '=' . $value);
        }
    }
});

it('rejects a different physical checkout and production even when fixture mode is selected', function (): void {
    $previous = [getenv('CAPELL_SCREENSHOT_FIXTURE'), getenv('CAPELL_SCREENSHOT_APP_PATH')];
    $environment = config('app.env');
    $runtimeEnvironment = app()->environment();
    $actor = new User;
    $before = StructuredContentItem::query()->count();
    putenv('CAPELL_SCREENSHOT_FIXTURE=record-state');
    try {
        foreach (['foreign-checkout', 'production'] as $state) {
            putenv('CAPELL_SCREENSHOT_APP_PATH=' . ($state === 'foreign-checkout' ? __DIR__ : base_path()));
            config()->set('app.env', $state === 'production' ? 'production' : 'testing');
            app()->instance('env', $state === 'production' ? 'production' : 'testing');
            expect(fn (): StructuredContentItem => SeedStructuredContentScreenshotFixtureAction::run())->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
            expect(StructuredContentItem::query()->count())->toBe($before);
        }
    } finally {
        config()->set('app.env', $environment);
        app()->instance('env', $runtimeEnvironment);
        foreach (['CAPELL_SCREENSHOT_FIXTURE', 'CAPELL_SCREENSHOT_APP_PATH'] as $index => $key) {
            putenv($previous[$index] === false ? $key : $key . '=' . $previous[$index]);
        }
    }
});
