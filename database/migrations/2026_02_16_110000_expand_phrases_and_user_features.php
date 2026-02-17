<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->unsignedInteger('jewish')->default(0)->index();
            $table->unsignedInteger('chaldean')->default(0)->index();
            $table->unsignedInteger('primes')->default(0)->index();
            $table->unsignedInteger('trigonal')->default(0)->index();
            $table->unsignedInteger('squares')->default(0)->index();
            $table->boolean('approved')->default(true)->index();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->boolean('is_admin')->default(false)->index();
            $table->json('preferred_ciphers')->nullable();
            $table->string('theme', 20)->default('dark');
            $table->string('locale_preference', 5)->default('en');
        });

        Schema::create('favorites', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phrase');
            $table->json('scores')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'phrase']);
        });

        Schema::create('search_histories', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('query');
            $table->string('locale', 5)->default('en');
            $table->json('ciphers');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('user_phrases', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('phrase');
            $table->text('note')->nullable();
            $table->json('scores');
            $table->timestamps();

            $table->index(['user_id', 'created_at']);
        });

        Schema::create('app_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('app_settings');
        Schema::dropIfExists('user_phrases');
        Schema::dropIfExists('search_histories');
        Schema::dropIfExists('favorites');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['is_admin', 'preferred_ciphers', 'theme', 'locale_preference']);
        });

        Schema::table('phrases', function (Blueprint $table): void {
            $table->dropColumn(['jewish', 'chaldean', 'primes', 'trigonal', 'squares', 'approved']);
        });
    }
};
