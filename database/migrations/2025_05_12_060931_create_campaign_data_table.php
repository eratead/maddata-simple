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
        Schema::create('campaign_data', function (Blueprint $table) {
            $table->id();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedInteger('impressions');
            $table->unsignedInteger('clicks');
            $table->unsignedInteger('visible_impressions');
            $table->unsignedInteger('uniques');
            $table->unsignedInteger('video_25')->nullable();
            $table->unsignedInteger('video_50')->nullable();
            $table->unsignedInteger('video_75')->nullable();
            $table->unsignedInteger('video_100')->nullable();
            $table->timestamps();
            $table->unique(['campaign_id', 'report_date']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('campaign_data');
    }
};
