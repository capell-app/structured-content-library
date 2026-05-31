<?php

declare(strict_types=1);

use Capell\StructuredContentLibrary\Enums\StructuredContentStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('structured_content_items')) {
            return;
        }

        Schema::create('structured_content_items', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('site_id')->nullable()->constrained('sites')->nullOnDelete();
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

            $table->index(['site_id', 'type', 'status', 'sort_order'], 'structured_content_site_type_status_order_index');
            $table->index(['type', 'slug'], 'structured_content_type_slug_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('structured_content_items');
    }
};
