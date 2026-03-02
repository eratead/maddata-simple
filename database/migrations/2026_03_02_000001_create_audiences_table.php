<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audiences', function (Blueprint $table) {
            $table->id();
            $table->string('provider')->nullable();
            $table->string('main_category')->index();
            $table->string('sub_category')->index();
            $table->string('name');
            $table->string('full_path')->nullable();
            $table->bigInteger('estimated_users')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audiences');
    }
};
