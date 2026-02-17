<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('phrase_click_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('phrase_id')->constrained('phrases')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('source', 30)->default('direct')->index();
            $table->string('search_query')->nullable();
            $table->string('search_query_norm')->nullable()->index();
            $table->timestamps();

            $table->index(['phrase_id', 'created_at']);
            $table->index(['source', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrase_click_events');
    }
};
