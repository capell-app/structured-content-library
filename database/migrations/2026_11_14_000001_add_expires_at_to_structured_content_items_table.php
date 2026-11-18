<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $table = TableName::resolve('structured_content_items');

        if (! Schema::hasTable($table) || Schema::hasColumn($table, 'expires_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->timestamp('expires_at')->nullable()->after('published_at')->index(TableName::namedIndex('structured_content_items', 'structured_content_items_expires_at_index'));
        });
    }

    public function down(): void
    {
        $table = TableName::resolve('structured_content_items');

        if (! Schema::hasTable($table) || ! Schema::hasColumn($table, 'expires_at')) {
            return;
        }

        Schema::table($table, function (Blueprint $table): void {
            $table->dropIndex(TableName::namedIndex('structured_content_items', 'structured_content_items_expires_at_index'));
            $table->dropColumn('expires_at');
        });
    }
};
