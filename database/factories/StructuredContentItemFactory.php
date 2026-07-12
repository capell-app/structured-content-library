<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Database\Factories;

use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Capell\StructuredContentLibrary\Models\StructuredContentItem;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<StructuredContentItem>
 */
class StructuredContentItemFactory extends Factory
{
    protected $model = StructuredContentItem::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $title = 'Example structured content ' . $this->faker->unique()->numberBetween(1, 1_000_000);

        return [
            'site_id' => null,
            'type' => StructuredContentType::Service,
            'status' => StructuredContentStatus::Draft,
            'title' => $title,
            'slug' => Str::slug($title),
            'summary' => 'Portable structured content summary.',
            'content' => '<p>Portable structured content.</p>',
            'payload' => new StructuredContentPayloadData(
                subtitle: 'Portable subtitle',
            ),
            'published_at' => null,
            'sort_order' => 0,
        ];
    }

    public function published(): self
    {
        return $this->state(fn (): array => [
            'status' => StructuredContentStatus::Published,
            'published_at' => now()->subMinute(),
        ]);
    }

    public function type(StructuredContentType $type): self
    {
        return $this->state(fn (): array => [
            'type' => $type,
        ]);
    }
}
