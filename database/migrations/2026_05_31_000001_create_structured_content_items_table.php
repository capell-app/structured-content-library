<?php

declare(strict_types=1);

use Capell\Core\Support\TableName;
use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (TableName::existsAndIsOwned('structured_content_items', basename(__FILE__, '.php'))) {
            return;
        }

        Schema::create(TableName::resolve('structured_content_items'), function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained(TableName::resolve('sites'))->nullOnDelete();
            $table->string('type')->index();
            $table->string('status')->default(StructuredContentStatus::Draft->value)->index();
            $table->string('title');
            $table->string('slug')->nullable()->index();
            $table->text('summary')->nullable();
            $table->mediumText('content')->nullable();
            $table->json('payload')->nullable();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('sort_order')->default(0)->index();
            $table->timestamps();
            $table->softDeletes();

            $table->index(['site_id', 'type', 'status', 'sort_order'], TableName::namedIndex('structured_content_items', 'structured_content_site_type_status_order_index'));
            $table->index(['type', 'slug'], TableName::namedIndex('structured_content_items', 'structured_content_type_slug_index'));
            $table->unique(['type', 'site_id', 'slug'], TableName::namedIndex('structured_content_items', 'structured_content_type_site_slug_unique'));
        });
    }

    public function down(): void
    {
        Schema::dropIfExists(TableName::resolve('structured_content_items'));
    }
};
