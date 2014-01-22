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

    public static function prepareEmpty(): void
    {
        self::assertDisposableScreenshotEnvironment();

        $slugs = [self::Slug, 'capell-screenshot-team-member', 'capell-screenshot-faq', 'capell-screenshot-service', 'capell-screenshot-location', 'capell-screenshot-partner'];

        (new StructuredContentItem)->getConnection()->transaction(static function () use ($slugs): void {
            throw_if(
                StructuredContentItem::query()->whereNotIn('slug', $slugs)->orWhereNull('slug')->exists()
                    || StructuredContentItem::query()->whereNotNull('site_id')->exists(),
                RuntimeException::class,
                'Refusing to empty a screenshot database containing non-fixture content.',
            );

            StructuredContentItem::query()->whereIn('slug', $slugs)->delete();
        });
    }

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
            ],
        ));

        // deleted_at is guarded; restoring a fixture must use the model lifecycle.
        if ($item->trashed()) {
            $item->restore();
        }

        self::seedSupportingItems();

        return $item;
    }

    /**
     * The testimonial above stays the first record (the edit capture opens it);
     * these extra typed records keep the admin index from rendering near-empty.
     */
    private static function seedSupportingItems(): void
    {
        $items = [
            [StructuredContentType::TeamMember, 'capell-screenshot-team-member', StructuredContentStatus::Published, 'Priya Raman', 'Head of editorial operations.', new StructuredContentPayloadData(role: 'Head of Editorial Operations', company: 'Northstar Studio')],
            [StructuredContentType::Faq, 'capell-screenshot-faq', StructuredContentStatus::Published, 'Can editors schedule updates?', 'Scheduling answer for the help section.', new StructuredContentPayloadData(question: 'Can editors schedule updates?', answer: 'Yes. Every item can be scheduled and reviewed before it goes live.')],
            [StructuredContentType::Service, 'capell-screenshot-service', StructuredContentStatus::Draft, 'Content migration support', 'Guided migration from a legacy CMS.', new StructuredContentPayloadData(subtitle: 'Guided migration from a legacy CMS')],
            [StructuredContentType::Location, 'capell-screenshot-location', StructuredContentStatus::Published, 'Manchester studio', 'Our northern office.', new StructuredContentPayloadData(streetAddress: '12 Tariff Street', locality: 'Manchester', postalCode: 'M1 2FF', countryCode: 'GB')],
            [StructuredContentType::Partner, 'capell-screenshot-partner', StructuredContentStatus::Archived, 'Brightline Agency', 'Former implementation partner.', new StructuredContentPayloadData(url: 'https://brightline.example.test')],
        ];

        foreach ($items as $sortOrder => [$type, $slug, $status, $title, $summary, $payload]) {
            $item = StructuredContentItem::withTrashed()->updateOrCreate(
                [
                    'type' => $type,
                    'site_scope_key' => StructuredContentItem::scopeKeyForSiteId(null),
                    'slug' => $slug,
                ],
                [
                    'site_id' => null,
                    'status' => $status,
                    'title' => $title,
                    'summary' => $summary,
                    'content' => '<p>' . $summary . '</p>',
                    'payload' => $payload,
                    'published_at' => $status === StructuredContentStatus::Published ? now()->subDays($sortOrder + 2) : null,
                    'sort_order' => $sortOrder + 1,
                ],
            );

            if ($item->trashed()) {
                $item->restore();
            }
        }
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
