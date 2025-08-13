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
        Schema::table('publications', function (Blueprint $table) {
            // Kolom ini dapat NULL karena publikasi utama tidak punya induk 
            $table->foreignId('parent_publication_id')->nullable()->after('id')->constrained('publications')->onDelete('cascade');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('publications', function (Blueprint $table) {
            $table->dropForeign(['parent_publication_id']);
            $table->dropColumn('parent_publication_id');
        });
    }
};
