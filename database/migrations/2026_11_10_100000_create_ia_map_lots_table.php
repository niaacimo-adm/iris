<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Lots (polygons) of each IA's service area. Filled from database/ia_map_lots.sql. */
    public function up(): void
    {
        if (Schema::hasTable('ia_map_lots')) {
            return;
        }

        Schema::create('ia_map_lots', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ia_profile_id');
            $table->string('lot_name', 100)->nullable();
            $table->decimal('area_ha', 10, 4)->default(0);
            $table->string('lot_status', 20)->default('op'); // op | non_op | converted | da_area | pnra
            $table->string('remarks', 100)->nullable();
            $table->string('municity', 100)->nullable();
            $table->longText('geometry');                     // GeoJSON geometry, WGS84
            $table->string('source', 120)->nullable();
            $table->timestamps();

            $table->index('ia_profile_id', 'ix_iaml_profile');
            $table->index('lot_status', 'ix_iaml_status');
            $table->foreign('ia_profile_id', 'fk_iaml_profile')->references('id')->on('ia_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_map_lots');
    }
};
