<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // activity_logs: ORDER BY created_at on every admin pageload
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->index('created_at', 'activity_logs_created_at_index');
        });

        // campaigns: UpdateCampaignStatuses cron uses WHERE status = ? AND end_date <= ?
        // campaigns.start_date: ordering/filtering on start_date
        // Note: created_at already indexed in 2026_03_10_114121; status already indexed in 2026_03_22_184627
        Schema::table('campaigns', function (Blueprint $table) {
            $table->index(['status', 'end_date'], 'campaigns_status_end_date_index');
            $table->index('start_date', 'campaigns_start_date_index');
        });

        // agency_user: composite PK is (agency_id, user_id) so WHERE user_id = ? has no index
        Schema::table('agency_user', function (Blueprint $table) {
            $table->index('user_id', 'agency_user_user_id_index');
        });

        // client_user: composite PK is (client_id, user_id) so WHERE user_id = ? has no index
        Schema::table('client_user', function (Blueprint $table) {
            $table->index('user_id', 'client_user_user_id_index');
        });

        // campaign_audience: composite PK is (campaign_id, audience_id) so WHERE audience_id = ? has no index
        Schema::table('campaign_audience', function (Blueprint $table) {
            $table->index('audience_id', 'campaign_audience_audience_id_index');
        });

        // audiences: covering index for the audience picker query
        // Note: is_active, main_category, sub_category each have individual indexes from create migration
        // and from 2026_03_22_184627. This composite covering index serves the combined filter + sort by name.
        Schema::table('audiences', function (Blueprint $table) {
            $table->index(
                ['is_active', 'main_category', 'sub_category', 'name'],
                'audiences_picker_covering_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('activity_logs', function (Blueprint $table) {
            $table->dropIndex('activity_logs_created_at_index');
        });

        Schema::table('campaigns', function (Blueprint $table) {
            $table->dropIndex('campaigns_status_end_date_index');
            $table->dropIndex('campaigns_start_date_index');
        });

        Schema::table('agency_user', function (Blueprint $table) {
            $table->dropIndex('agency_user_user_id_index');
        });

        Schema::table('client_user', function (Blueprint $table) {
            $table->dropIndex('client_user_user_id_index');
        });

        Schema::table('campaign_audience', function (Blueprint $table) {
            $table->dropIndex('campaign_audience_audience_id_index');
        });

        Schema::table('audiences', function (Blueprint $table) {
            $table->dropIndex('audiences_picker_covering_index');
        });
    }
};
