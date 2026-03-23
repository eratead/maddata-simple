<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('agency_user', function (Blueprint $table) {
            $table->dropColumn('role');
            $table->boolean('access_all_clients')->default(true)->after('user_id');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::table('agency_user', function (Blueprint $table) {
            $table->dropColumn('access_all_clients');
            $table->dropTimestamps();
            $table->string('role')->default('viewer');
        });
    }
};
