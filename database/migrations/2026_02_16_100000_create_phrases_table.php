<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('phrases', function (Blueprint $table): void {
            $table->id();
            $table->string('phrase')->unique();
            $table->unsignedInteger('english_ordinal')->index();
            $table->unsignedInteger('reverse_ordinal')->index();
            $table->unsignedInteger('full_reduction')->index();
            $table->unsignedInteger('reverse_reduction')->index();
            $table->unsignedInteger('satanic')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('phrases');
    }
};
