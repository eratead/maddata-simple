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
        Schema::table('creative_files', function (Blueprint $table) {
            $table->string('path')->after('name');
            $table->string('mime_type')->nullable()->after('height');
            $table->unsignedBigInteger('size')->nullable()->after('mime_type');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('creative_files', function (Blueprint $table) {
            $table->dropColumn(['path', 'mime_type', 'size']);
        });
    }
};
