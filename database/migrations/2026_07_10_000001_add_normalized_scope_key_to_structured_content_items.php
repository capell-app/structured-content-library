<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('structured_content_items', function (Blueprint $table): void {
            $table->string('site_scope_key', 20)->default('global')->after('site_id');
        });

        DB::table('structured_content_items')
            ->select(['id', 'site_id'])
            ->whereNotNull('site_id')
            ->orderBy('id')
            ->chunkById(500, function (Collection $items): void {
                $items->each(function (stdClass $item): void {
                    DB::table('structured_content_items')
                        ->where('id', (int) $item->id)
                        ->update(['site_scope_key' => (string) $item->site_id]);
                });
            });

        $this->deduplicateScopedSlugs();

        Schema::table('structured_content_items', function (Blueprint $table): void {
            $table->unique(
                ['type', 'site_scope_key', 'slug'],
                'structured_content_type_scope_slug_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('structured_content_items', function (Blueprint $table): void {
            $table->dropUnique('structured_content_type_scope_slug_unique');
            $table->dropColumn('site_scope_key');
        });
    }

    private function deduplicateScopedSlugs(): void
    {
        /** @var array<string, true> $seenSlugs */
        $seenSlugs = [];

        $items = DB::table('structured_content_items')
            ->select(['id', 'type', 'site_scope_key', 'slug'])
            ->whereNotNull('slug')
            ->orderBy('id')
            ->lazyById(500);

        foreach ($items as $item) {
            $slug = (string) $item->slug;
            $scopeKey = (string) $item->site_scope_key;
            $scopedSlugKey = $item->type . '|' . $scopeKey . '|' . $slug;

            if (! isset($seenSlugs[$scopedSlugKey])) {
                $seenSlugs[$scopedSlugKey] = true;

                continue;
            }

            $deduplicatedSlug = $this->deduplicatedSlug((string) $item->type, $scopeKey, $slug);

            DB::table('structured_content_items')
                ->where('id', (int) $item->id)
                ->update(['slug' => $deduplicatedSlug]);

            $seenSlugs[$item->type . '|' . $scopeKey . '|' . $deduplicatedSlug] = true;
        }
    }

    private function deduplicatedSlug(string $type, string $scopeKey, string $baseSlug): string
    {
        $candidateBaseSlug = trim($baseSlug) !== '' ? $baseSlug : 'structured-content-item';
        $suffix = 2;

        do {
            $suffixText = '-' . $suffix;
            $candidateSlug = Str::limit($candidateBaseSlug, 255 - strlen($suffixText), '') . $suffixText;
            $suffix++;
        } while (DB::table('structured_content_items')
            ->where('type', $type)
            ->where('site_scope_key', $scopeKey)
            ->where('slug', $candidateSlug)
            ->exists());

        return $candidateSlug;
    }
};
