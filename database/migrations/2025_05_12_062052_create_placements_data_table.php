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
        Schema::create('placements_data', function (Blueprint $table) {
            $table->id();
            $table->string('name')->index();
            $table->foreignId('campaign_id')->constrained('campaigns')->cascadeOnDelete();
            $table->date('report_date');
            $table->unsignedInteger('impressions')->default(0);
            $table->unsignedInteger('clicks')->default(0);
            $table->unsignedInteger('visible_impressions')->default(0);
            $table->unsignedInteger('uniques')->default(0);
            $table->unsignedInteger('video_25')->nullable();
            $table->unsignedInteger('video_50')->nullable();
            $table->unsignedInteger('video_75')->nullable();
            $table->unsignedInteger('video_100')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('placements_data');
    }
};
