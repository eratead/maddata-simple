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
        Schema::table('placements_data', function (Blueprint $table) {
            $table->index(['campaign_id', 'report_date'], 'placements_data_campaign_date_index');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index(['campaign_id', 'status'], 'activity_logs_campaign_status_index');
            $table->index('action', 'activity_logs_action_index');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->index('status', 'campaigns_status_index');
            $table->index(['client_id', 'status'], 'campaigns_client_status_index');
        });

        Schema::table('audiences', function (Blueprint $table) {
            $table->index('is_active', 'audiences_is_active_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('role_id', 'users_role_id_index');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('placements_data', function (Blueprint $table) {
            $table->dropIndex('placements_data_campaign_date_index');
        });

        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_campaign_status_index');
            $table->dropIndex('activity_logs_action_index');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('campaigns_status_index');
            $table->dropIndex('campaigns_client_status_index');
        });

        Schema::table('audiences', function (Blueprint $table) {
            $table->dropIndex('audiences_is_active_index');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex('users_role_id_index');
        });
    }
};
