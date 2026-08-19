<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Check D3 runs `MAX(report_date)` across the whole table. The existing
     * UNIQUE(campaign_id, report_date) cannot serve it — report_date is not an
     * index prefix, so MySQL cannot apply the MIN/MAX optimisation and falls
     * back to scanning the full index (verified on production: type=index,
     * Extra="Using index"). Harmless at today's ~1.8K rows, but the table is
     * append-only and the query runs every minute, sweeping the buffer pool.
     *
     * With this index the plan becomes "Select tables optimized away".
     */
    public function up(): void
    {
        Schema::table('campaign_data', function (Blueprint $table) {
            $table->index('report_date', 'campaign_data_report_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('campaign_data', function (Blueprint $table) {
            $table->dropIndex('campaign_data_report_date_index');
        });
    }
};
