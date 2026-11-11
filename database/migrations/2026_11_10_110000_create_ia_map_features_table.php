<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** Canal network of each IA (lines and points) for the map. Filled from the upload on the IA profile page. */
    public function up(): void
    {
        if (Schema::hasTable('ia_map_features')) {
            return;
        }

        Schema::create('ia_map_features', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ia_profile_id');
            $table->string('kind', 20);                      // canal | structure | dam | damage
            $table->string('name', 150)->nullable();
            $table->string('group_name', 100)->nullable();   // e.g. "NIA / MC", "DA / LAT A1"
            $table->decimal('length_m', 10, 1)->default(0);  // lines only
            $table->longText('geometry');                    // GeoJSON LineString / MultiLineString / Point, WGS84
            $table->string('source', 120)->nullable();
            $table->timestamps();

            $table->index('ia_profile_id', 'ix_iamf_profile');
            $table->index('kind', 'ix_iamf_kind');
            $table->foreign('ia_profile_id', 'fk_iamf_profile')->references('id')->on('ia_profiles')->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_map_features');
    }
};
