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
        // Update table indicators 
        Schema::table('indicators', function (Blueprint $table) {
            $table->integer('bps_var_id')->nullable()->after('category_id');
            $table->integer('bps_vervar_id')->nullable()->after('bps_var_id');
            $table->integer('bps_turvar_id')->nullable()->after('bps_vervar_id');
            $table->string('name', 255)->change();
            $table->text('description')->nullable()->change();
        });


        // Change table 'indicator_values'
        Schema::table('indicator_values', function (Blueprint $table) {
            $table->timestamp('last_synced_at')->nullable()->after('value');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('indicators', function (Blueprint $table) {
            $table->dropColumn(['bps_var_id', 'bps_vervar_id', 'bps_turvar_id']);
        });

        Schema::table('indicator_values', function (Blueprint $table) {
            $table->dropColumn('last_synced_at');
        });
    }
};
