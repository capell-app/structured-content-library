<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

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
};
