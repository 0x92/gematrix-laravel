<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->unsignedInteger('simple_gematria')->default(0)->index();
            $table->unsignedInteger('unknown_gematria')->default(0)->index();
            $table->unsignedInteger('pythagoras_gematria')->default(0)->index();
            $table->unsignedInteger('jewish_gematria')->default(0)->index();
            $table->unsignedInteger('prime_gematria')->default(0)->index();
            $table->unsignedInteger('reverse_satanic_gematria')->default(0)->index();
            $table->unsignedInteger('clock_gematria')->default(0)->index();
            $table->unsignedInteger('reverse_clock_gematria')->default(0)->index();
            $table->unsignedInteger('system9_gematria')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->dropColumn([
                'simple_gematria',
                'unknown_gematria',
                'pythagoras_gematria',
                'jewish_gematria',
                'prime_gematria',
                'reverse_satanic_gematria',
                'clock_gematria',
                'reverse_clock_gematria',
                'system9_gematria',
            ]);
        });
    }
};
