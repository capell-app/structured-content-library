<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('structured_content_items')) {
            return;
        }

        if (Schema::hasIndex('structured_content_items', 'structured_content_type_site_slug_unique')) {
            return;
        }

        $this->deduplicateScopedSlugs();

        Schema::table('structured_content_items', function (Blueprint $table): void {
            $table->unique(['type', 'site_id', 'slug'], 'structured_content_type_site_slug_unique');
        });
    }

    public function down(): void
    {
        if (! Schema::hasTable('structured_content_items')) {
            return;
        }

        if (! Schema::hasIndex('structured_content_items', 'structured_content_type_site_slug_unique')) {
            return;
        }

        Schema::table('structured_content_items', function (Blueprint $table): void {
            $table->dropUnique('structured_content_type_site_slug_unique');
        });
    }

    private function deduplicateScopedSlugs(): void
    {
        $reservedSlugsByScope = [];
        $seenScopedSlugs = [];

        $items = DB::table('structured_content_items')
            ->select(['id', 'type', 'site_id', 'slug'])
            ->whereNotNull('slug')
            ->orderBy('type')
            ->orderBy('site_id')
            ->orderBy('slug')
            ->orderBy('id')
            ->get();

        foreach ($items as $item) {
            $scopeKey = $this->scopeKey($item);
            $slug = (string) $item->slug;

            $reservedSlugsByScope[$scopeKey][$slug] = true;
        }

        foreach ($items as $item) {
            $scopeKey = $this->scopeKey($item);
            $slug = (string) $item->slug;
            $scopedSlugKey = $scopeKey . '|' . $slug;

            if (! isset($seenScopedSlugs[$scopedSlugKey])) {
                $seenScopedSlugs[$scopedSlugKey] = true;

                continue;
            }

            $deduplicatedSlug = $this->deduplicatedSlug(
                baseSlug: $slug,
                reservedSlugs: $reservedSlugsByScope[$scopeKey],
            );

            DB::table('structured_content_items')
                ->where('id', (int) $item->id)
                ->update(['slug' => $deduplicatedSlug]);

            $reservedSlugsByScope[$scopeKey][$deduplicatedSlug] = true;
            $seenScopedSlugs[$scopeKey . '|' . $deduplicatedSlug] = true;
        }
    }

    private function scopeKey(stdClass $item): string
    {
        $siteId = $item->site_id === null ? 'global' : (string) $item->site_id;

        return $item->type . '|' . $siteId;
    }

    /**
     * @param  array<string, true>  $reservedSlugs
     */
    private function deduplicatedSlug(string $baseSlug, array $reservedSlugs): string
    {
        $candidateBaseSlug = trim($baseSlug) !== ''
            ? $baseSlug
            : 'structured-content-item';
        $suffix = 2;

        do {
            $candidateSlug = $this->slugWithSuffix($candidateBaseSlug, $suffix);
            $suffix++;
        } while (isset($reservedSlugs[$candidateSlug]));

        return $candidateSlug;
    }

    private function slugWithSuffix(string $baseSlug, int $suffix): string
    {
        $slugSuffix = '-' . $suffix;
        $baseLength = 255 - strlen($slugSuffix);

        return Str::limit($baseSlug, $baseLength, '') . $slugSuffix;
    }
};
