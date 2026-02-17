<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('news_sources', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('base_url')->nullable();
            $table->string('rss_url')->nullable();
            $table->string('locale', 10)->default('en');
            $table->boolean('enabled')->default(true)->index();
            $table->unsignedInteger('weight')->default(100);
            $table->timestamp('last_checked_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['name', 'rss_url']);
        });

        Schema::create('crawler_runs', function (Blueprint $table): void {
            $table->id();
            $table->string('crawler_name')->index();
            $table->string('status', 20)->default('running')->index();
            $table->timestamp('started_at')->nullable()->index();
            $table->timestamp('finished_at')->nullable()->index();
            $table->string('current_item')->nullable();
            $table->unsignedInteger('processed_count')->default(0);
            $table->unsignedInteger('inserted_count')->default(0);
            $table->text('error_message')->nullable();
            $table->json('meta')->nullable();
            $table->timestamps();
        });

        Schema::create('news_headlines', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('news_source_id')->nullable()->constrained('news_sources')->nullOnDelete();
            $table->string('headline', 500);
            $table->string('headline_normalized', 500)->index();
            $table->string('url', 700)->nullable();
            $table->string('url_hash', 64)->unique();
            $table->timestamp('published_at')->nullable()->index();
            $table->date('headline_date')->index();
            $table->string('locale', 10)->default('en')->index();
            $table->unsignedInteger('english_gematria')->default(0)->index();
            $table->json('scores')->nullable();
            $table->timestamps();

            $table->index(['news_source_id', 'headline_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_headlines');
        Schema::dropIfExists('crawler_runs');
        Schema::dropIfExists('news_sources');
    }
};
