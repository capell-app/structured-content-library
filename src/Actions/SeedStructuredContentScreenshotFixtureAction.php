<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Actions;

use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use RuntimeException;

final class SeedStructuredContentScreenshotFixtureAction
{
    private const string Slug = 'capell-screenshot-testimonial';

    public static function run(): StructuredContentItem
    {
        self::assertDisposableScreenshotEnvironment();

        /** @var StructuredContentItem $item */
        $item = (new StructuredContentItem)->getConnection()->transaction(static fn (): StructuredContentItem => StructuredContentItem::withTrashed()->updateOrCreate(
            [
                'type' => StructuredContentType::Testimonial,
                'site_scope_key' => StructuredContentItem::scopeKeyForSiteId(null),
                'slug' => self::Slug,
            ],
            [
                'site_id' => null,
                'status' => StructuredContentStatus::Published,
                'title' => 'A clearer editorial workflow',
                'summary' => 'A deterministic published testimonial for the authenticated screenshot queue.',
                'content' => '<p>Our editors can review, refine, and publish reusable content with confidence.</p>',
                'payload' => new StructuredContentPayloadData(
                    quote: 'The content workflow gives every team a clear publishing path.',
                    attribution: 'Morgan Lee',
                    role: 'Content Operations Lead',
                    company: 'Northstar Studio',
                ),
                'published_at' => now()->subDay(),
                'sort_order' => 0,
                'deleted_at' => null,
            ],
        ));

        return $item;
    }

    private static function assertDisposableScreenshotEnvironment(): void
    {
        $configuredAppPath = getenv('CAPELL_SCREENSHOT_APP_PATH');
        $basePath = realpath(base_path());
        $appPath = is_string($configuredAppPath) ? realpath($configuredAppPath) : false;

        throw_unless(
            self::isLocalOrTestingEnvironment()
                && in_array(getenv('CAPELL_SCREENSHOT_FIXTURE'), ['1', 'true', 'record-state'], true)
                && is_string($basePath)
                && is_string($appPath)
                && $basePath === $appPath,
            RuntimeException::class,
            'Structured Content Library screenshot fixtures require the explicit disposable local screenshot environment.',
        );
    }

    private static function isLocalOrTestingEnvironment(): bool
    {
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');

        return in_array($environment, ['local', 'testing'], true);
    }
}
