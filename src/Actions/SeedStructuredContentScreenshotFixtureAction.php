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

        (new StructuredContentItem)->getConnection()->transaction(static function (): void {
            self::assertFixtureOwnership(true);
            StructuredContentItem::query()->whereIn('slug', array_keys(self::fixtureRows()))->delete();
        });
    }

    public static function run(): StructuredContentItem
    {
        self::assertDisposableScreenshotEnvironment();

        return (new StructuredContentItem)->getConnection()->transaction(static function (): StructuredContentItem {
            self::assertFixtureOwnership(false);

            $testimonial = null;
            foreach (self::fixtureRows() as $slug => [$type, $status, $title, $summary, $content, $payload, $sortOrder]) {
                $item = StructuredContentItem::withTrashed()->updateOrCreate(
                    ['type' => $type, 'site_scope_key' => StructuredContentItem::scopeKeyForSiteId(null), 'slug' => $slug],
                    [
                        'site_id' => null,
                        'status' => $status,
                        'title' => $title,
                        'summary' => $summary,
                        'content' => $content,
                        'payload' => $payload,
                        'published_at' => $status === StructuredContentStatus::Published ? now()->subDays($sortOrder + 1) : null,
                        'sort_order' => $sortOrder,
                    ],
                );

                if ($item->trashed()) {
                    $item->restore();
                }

                if ($slug === self::Slug) {
                    $testimonial = $item;
                }
            }

            if (! $testimonial instanceof StructuredContentItem) {
                throw new RuntimeException('The screenshot testimonial was not seeded.');
            }

            return $testimonial;
        });
    }

    /**
     * @return array<string, array{StructuredContentType, StructuredContentStatus, string, string, string, StructuredContentPayloadData, int}>
     */
    private static function fixtureRows(): array
    {
        return [
            self::Slug => [StructuredContentType::Testimonial, StructuredContentStatus::Published, 'A clearer editorial workflow', 'A deterministic published testimonial for the authenticated screenshot queue.', '<p>Our editors can review, refine, and publish reusable content with confidence.</p>', new StructuredContentPayloadData(quote: 'The content workflow gives every team a clear publishing path.', attribution: 'Morgan Lee', role: 'Content Operations Lead', company: 'Northstar Studio'), 0],
            'capell-screenshot-team-member' => [StructuredContentType::TeamMember, StructuredContentStatus::Published, 'Priya Raman', 'Head of editorial operations.', '<p>Head of editorial operations.</p>', new StructuredContentPayloadData(role: 'Head of Editorial Operations', company: 'Northstar Studio'), 1],
            'capell-screenshot-faq' => [StructuredContentType::Faq, StructuredContentStatus::Published, 'Can editors schedule updates?', 'Scheduling answer for the help section.', '<p>Scheduling answer for the help section.</p>', new StructuredContentPayloadData(question: 'Can editors schedule updates?', answer: 'Yes. Every item can be scheduled and reviewed before it goes live.'), 2],
            'capell-screenshot-service' => [StructuredContentType::Service, StructuredContentStatus::Draft, 'Content migration support', 'Guided migration from a legacy CMS.', '<p>Guided migration from a legacy CMS.</p>', new StructuredContentPayloadData(subtitle: 'Guided migration from a legacy CMS'), 3],
            'capell-screenshot-location' => [StructuredContentType::Location, StructuredContentStatus::Published, 'Manchester studio', 'Our northern office.', '<p>Our northern office.</p>', new StructuredContentPayloadData(streetAddress: '12 Tariff Street', locality: 'Manchester', postalCode: 'M1 2FF', countryCode: 'GB'), 4],
            'capell-screenshot-partner' => [StructuredContentType::Partner, StructuredContentStatus::Archived, 'Brightline Agency', 'Former implementation partner.', '<p>Former implementation partner.</p>', new StructuredContentPayloadData(url: 'https://brightline.example.test'), 5],
        ];
    }

    private static function assertFixtureOwnership(bool $requireOnlyFixtures): void
    {
        $rows = self::fixtureRows();
        $existing = StructuredContentItem::withTrashed()
            ->unless($requireOnlyFixtures, static fn ($query) => $query->whereIn('slug', array_keys($rows)))
            ->get();

        foreach ($existing as $item) {
            $expected = $rows[$item->slug ?? ''] ?? null;
            if ($expected === null) {
                throw new RuntimeException('Refusing to empty a screenshot database containing non-fixture content.');
            }

            [$type, , $title, $summary, $content, $payload] = $expected;
            throw_if(
                $item->type !== $type
                    || $item->site_id !== null
                    || $item->site_scope_key !== StructuredContentItem::scopeKeyForSiteId(null)
                    || $item->title !== $title
                    || $item->summary !== $summary
                    || $item->content !== $content
                    || $item->payload?->toArray() !== $payload->toArray(),
                RuntimeException::class,
                'Structured content screenshot slug belongs to another record.',
            );
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

        $captureEmail = getenv('CAPELL_SCREENSHOT_ADMIN_EMAIL') ?: (getenv('CAPELL_ADMIN_EMAIL') ?: 'admin@example.com');
        $authenticated = auth()->user();
        throw_if(
            $authenticated !== null && data_get($authenticated, 'email') !== $captureEmail,
            RuntimeException::class,
            'Screenshot fixtures require the selected capture actor.',
        );
    }

    private static function isLocalOrTestingEnvironment(): bool
    {
        $environment = app()->bound('config') ? config('app.env') : getenv('APP_ENV');

        return in_array($environment, ['local', 'testing'], true);
    }
}
