<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        if (! Schema::hasTable('users')) {
            return;
        }

        Schema::table('users', function (Blueprint $table): void {
            if (! Schema::hasColumn('users', 'is_admin')) {
                $table->boolean('is_admin')->default(false)->index();
            }
            if (! Schema::hasColumn('users', 'preferred_ciphers')) {
                $table->json('preferred_ciphers')->nullable();
            }
            if (! Schema::hasColumn('users', 'theme')) {
                $table->string('theme', 20)->default('dark');
            }
            if (! Schema::hasColumn('users', 'locale_preference')) {
                $table->string('locale_preference', 5)->default('en');
            }
        });
    }

    public function down(): void
    {
        // no-op
    }
};
