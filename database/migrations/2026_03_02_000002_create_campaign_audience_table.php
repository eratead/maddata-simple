<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('campaign_audience', function (Blueprint $table) {
            $table->foreignId('campaign_id')->constrained()->cascadeOnDelete();
            $table->foreignId('audience_id')->constrained()->cascadeOnDelete();
            $table->primary(['campaign_id', 'audience_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('campaign_audience');
    }
};
