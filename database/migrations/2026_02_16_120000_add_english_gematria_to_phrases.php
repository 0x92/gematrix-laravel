<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->unsignedInteger('english_gematria')->default(0)->index();
        });
    }

    public function down(): void
    {
        Schema::table('phrases', function (Blueprint $table): void {
            $table->dropColumn('english_gematria');
        });
    }
};
