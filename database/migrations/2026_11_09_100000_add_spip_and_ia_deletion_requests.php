<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Third irrigation system next to cis_name and nis.
        if (! Schema::hasColumn('ia_profiles', 'spip_name')) {
            Schema::table('ia_profiles', function (Blueprint $table) {
                $table->string('spip_name', 150)->nullable()->after('nis');
            });
        }

        // Delete requests that Super Admin / Regional Staff approve or reject.
        // Integer columns are UNSIGNED INT to match ia_profiles.id and users.id.
        Schema::create('ia_deletion_requests', function (Blueprint $table) {
            $table->increments('id');
            $table->unsignedInteger('ia_profile_id');
            $table->unsignedInteger('office_id')->nullable();
            $table->unsignedInteger('requested_by')->nullable();
            $table->string('reason', 500);
            $table->enum('status', ['pending', 'approved', 'rejected', 'withdrawn'])->default('pending');
            $table->unsignedInteger('decided_by')->nullable();
            $table->string('decision_note', 500)->nullable();
            $table->timestamp('decided_at')->nullable();
            $table->timestamp('requester_seen_at')->nullable(); // requester has seen the decision (clears their badge)
            $table->timestamps();

            $table->index(['status', 'created_at'], 'ix_iadr_status');
            $table->index(['ia_profile_id', 'status'], 'ix_iadr_profile');
            $table->foreign('ia_profile_id', 'fk_iadr_profile')->references('id')->on('ia_profiles')->cascadeOnDelete();
            $table->foreign('requested_by', 'fk_iadr_requester')->references('id')->on('users')->nullOnDelete();
            $table->foreign('decided_by', 'fk_iadr_decider')->references('id')->on('users')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_deletion_requests');

        if (Schema::hasColumn('ia_profiles', 'spip_name')) {
            Schema::table('ia_profiles', fn (Blueprint $table) => $table->dropColumn('spip_name'));
        }
    }
};
