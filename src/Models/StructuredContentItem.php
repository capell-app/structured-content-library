<?php

declare(strict_types=1);

namespace Capell\StructuredContentLibrary\Models;

use Capell\Core\Concerns\HasCapellMedia;
use Capell\Core\Concerns\HasCapellTable;
use Capell\Core\Enums\MediaCollectionEnum;
use Capell\Core\Models\Site;
use Capell\Core\Models\Translation;
use Capell\StructuredContentLibrary\Data\StructuredContentPayloadData;
use Capell\StructuredContentLibrary\Database\Factories\StructuredContentItemFactory;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Capell\StructuredContentLibrary\Enums\StructuredContentType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Override;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\MediaCollections\Models\Media;

/**
 * @property int $id
 * @property int|null $site_id
 * @property string $site_scope_key
 * @property StructuredContentType $type
 * @property StructuredContentStatus $status
 * @property string $title
 * @property string|null $slug
 * @property string|null $summary
 * @property string|null $content
 * @property StructuredContentPayloadData|null $payload
 * @property CarbonImmutable|null $published_at
 * @property CarbonImmutable|null $expires_at
 * @property int $sort_order
 * @property CarbonImmutable|null $created_at
 * @property CarbonImmutable|null $updated_at
 * @property CarbonImmutable|null $deleted_at
 * @property-read Site|null $site
 * @property-read EloquentCollection<int, Media> $media
 * @property-read EloquentCollection<int, Translation> $translations
 *
 * @method static StructuredContentItemFactory factory($count = null, $state = [])
 * @method static Builder<static>|StructuredContentItem archived()
 * @method static Builder<static>|StructuredContentItem draft()
 * @method static Builder<static>|StructuredContentItem forType(StructuredContentType $type)
 * @method static Builder<static>|StructuredContentItem ordered()
 * @method static Builder<static>|StructuredContentItem published()
 * @method static Builder<static>|StructuredContentItem visibleToSite(int|null $siteId)
 *
 * @mixin Model
 */
class StructuredContentItem extends Model implements HasMedia
{
    use HasCapellMedia;
    use HasCapellTable;

    /** @use HasFactory<StructuredContentItemFactory> */
    use HasFactory;

    use SoftDeletes;

    /** Image formats an editor may attach as a record's photo or logo. */
    public const array MEDIA_MIME_TYPES = ['image/jpeg', 'image/png', 'image/webp', 'image/gif', 'image/avif', 'image/svg+xml'];

    /** @var list<string> */
    protected $fillable = [
        'site_id',
        'site_scope_key',
        'type',
        'status',
        'title',
        'slug',
        'summary',
        'content',
        'payload',
        'published_at',
        'expires_at',
        'sort_order',
    ];

    protected static string $factory = StructuredContentItemFactory::class;

    public static function scopeKeyForSiteId(?int $siteId): string
    {
        return $siteId === null ? 'global' : (string) $siteId;
    }

    /**
     * Narrows a query to records whose publication or expiry time falls in the window, so a
     * sweep can retire the public output that changes at that moment.
     *
     * @param  Builder<self>  $query
     */
    public static function constrainToBoundariesBetween(Builder $query, CarbonImmutable $from, CarbonImmutable $until): void
    {
        $query->where(static function (Builder $boundary) use ($from, $until): void {
            $boundary
                ->whereBetween('published_at', [$from, $until])
                ->orWhereBetween('expires_at', [$from, $until]);
        });
    }

    /**
     * @return BelongsTo<Site, $this>
     */
    public function site(): BelongsTo
    {
        return $this->belongsTo(Site::class);
    }

    /**
     * The record's wording in other languages, stored as Core translations: the title, the
     * content, and the summary and payload in the translation's meta.
     *
     * @return MorphMany<Translation, $this>
     */
    public function translations(): MorphMany
    {
        return $this->morphMany(Translation::class, 'translatable');
    }

    /** Whether the record is publicly visible at the given moment. */
    public function isPublicAt(CarbonImmutable $moment): bool
    {
        return $this->status === StructuredContentStatus::Published
            && ($this->published_at === null || $this->published_at->lessThanOrEqualTo($moment))
            && ($this->expires_at === null || $this->expires_at->greaterThan($moment));
    }

    #[Override]
    public function registerMediaCollections(): void
    {
        $this->addMediaCollection(MediaCollectionEnum::Image->value)
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);
        $this->addMediaCollection(MediaCollectionEnum::Logo->value)
            ->singleFile()
            ->acceptsMimeTypes(self::MEDIA_MIME_TYPES);
    }

    #[Override]
    protected static function booted(): void
    {
        static::creating(function (self $item): void {
            $item->site_scope_key = self::scopeKeyForSiteId($item->site_id);
        });

        static::updating(function (self $item): void {
            if ($item->isDirty('site_id')) {
                $item->site_scope_key = self::scopeKeyForSiteId($item->site_id);
            }
        });

        static::forceDeleting(function (self $item): void {
            $item->translations()->withTrashed()->forceDelete();
        });
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
            })
            ->where(function (Builder $expiryQuery): void {
                $expiryQuery
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now());
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
            'expires_at' => 'immutable_datetime',
            'sort_order' => 'integer',
        ];
    }
}
