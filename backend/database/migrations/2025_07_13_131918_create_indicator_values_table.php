<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('indicator_values', function (Blueprint $table) {
            $table->id();
            $table->foreignId('indicator_id')->constrained()->onDelete('cascade');
            $table->foreignId('region_id')->constrained()->onDelete('cascade');
            $table->year('year');
            $table->decimal('value', 15, 2);
            $table->timestamps();

            $table->unique(['indicator_id', 'region_id', 'year']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_values');
    }
};
