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
        Schema::create('indicator_publication', function (Blueprint $table) {
            // Foreign Key ke tabel indicators
            $table->foreignId('indicator_id')->constrained()->onDelete('cascade');

            // Foreign key ke tabel publications
            $table->foreignId('publication_id')->constrained()->onDelete('cascade');

            // Menjadikan kombinasi keduanya sebagai primary key untuk mencegah duplikasi
            $table->primary(['indicator_id', 'publication_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('indicator_publication');
    }
};
