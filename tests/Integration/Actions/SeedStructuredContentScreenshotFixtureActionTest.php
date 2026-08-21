<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Actions\SeedStructuredContentScreenshotFixtureAction;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Capell\StructuredContentLibrary\Tests\StructuredContentLibraryTestCase;
use RuntimeException;

uses(StructuredContentLibraryTestCase::class);

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

it('seeds one published typed record for the disposable screenshot app', function (): void {
    withStructuredContentScreenshotFixtureEnvironment(function (): void {
        $first = SeedStructuredContentScreenshotFixtureAction::run();
        $second = SeedStructuredContentScreenshotFixtureAction::run();

        expect($second->getKey())->toBe($first->getKey())
            ->and(StructuredContentItem::query()->count())->toBe(1)
            ->and($second->status)->toBe(StructuredContentStatus::Published)
            ->and($second->payload?->quote)->toBe('The content workflow gives every team a clear publishing path.');
    });
});

it('refuses to seed outside the explicit disposable screenshot environment', function (): void {
    expect(fn (): StructuredContentItem => SeedStructuredContentScreenshotFixtureAction::run())
        ->toThrow(RuntimeException::class, 'explicit disposable local screenshot environment');
});
