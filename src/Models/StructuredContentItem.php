<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Models;

use Capell\Core\Models\Site;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Database\Factories\StructuredContentItemFactory;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;

/**
 * @property int $id
 * @property int|null $site_id
 * @property StructuredContentType $type
 * @property StructuredContentStatus $status
 * @property string $title
 * @property string|null $slug
 * @property string|null $summary
 * @property string|null $content
 * @property StructuredContentPayloadData|null $payload
 * @property CarbonImmutable|null $published_at
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Site|null $site
 *
 * @method static StructuredContentItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|StructuredContentItem archived()
 * @method static Builder<static>|StructuredContentItem draft()
 * @method static Builder<static>|StructuredContentItem forType(StructuredContentType $type)
 * @method static Builder<static>|StructuredContentItem newModelQuery()
 * @method static Builder<static>|StructuredContentItem newQuery()
 * @method static Builder<static>|StructuredContentItem ordered()
 * @method static Builder<static>|StructuredContentItem published()
 * @method static Builder<static>|StructuredContentItem query()
 * @method static Builder<static>|StructuredContentItem visibleToSite(int|null $siteId)
 *
 * @mixin Model
 */
class StructuredContentItem extends Model
{
    /** @use HasFactory<StructuredContentItemFactory> */
    use HasFactory;

    use SoftDeletes;

    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'type',
        'status',
        'title',
        'slug',
        'summary',
        'content',
        'payload',
        'published_at',
        'sort_order',
    ];

    protected static string $factory = StructuredContentItemFactory::class;

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeForType(Builder $query, StructuredContentType $type): Builder
    {
        return $query->where('type', $type);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopePublished(Builder $query): Builder
    {
        return $query
            ->where('status', StructuredContentStatus::Published)
            ->where(function (Builder $visibilityQuery): void {
                $visibilityQuery
                    ->whereNull('published_at')
                    ->orWhere('published_at', '<=', now());
            });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeDraft(Builder $query): Builder
    {
        return $query->where('status', StructuredContentStatus::Draft);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeArchived(Builder $query): Builder
    {
        return $query->where('status', StructuredContentStatus::Archived);
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeVisibleToSite(Builder $query, ?int $siteId): Builder
    {
        if ($siteId === null) {
            return $query->whereNull('site_id');
        }

        return $query->where(function (Builder $siteQuery) use ($siteId): void {
            $siteQuery
                ->whereNull('site_id')
                ->orWhere('site_id', $siteId);
        });
    }

    /**
     * @param  Builder<Model>  $query
     * @return Builder<Model>
     */
    protected function scopeOrdered(Builder $query): Builder
    {
        return $query
            ->orderBy('sort_order')
            ->orderBy('title')
            ->orderBy('id');
    }

    /**
     * @return array<string, string>
     */
    #[Override]
    protected function casts(): array
    {
        return [
            'type' => StructuredContentType::class,
            'status' => StructuredContentStatus::class,
            'payload' => StructuredContentPayloadData::class,
            'published_at' => 'immutable_datetime',
            'sort_order' => 'integer',
        ];
    }
}
