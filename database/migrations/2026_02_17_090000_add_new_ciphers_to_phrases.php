<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->unsignedInteger('francis_bacon_gematria')->default(0)->index();
            $table->unsignedInteger('septenary_gematria')->default(0)->index();
            $table->unsignedInteger('glyph_geometry_gematria')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->dropColumn([
                'francis_bacon_gematria',
                'septenary_gematria',
                'glyph_geometry_gematria',
            ]);
        });
    }
};
