<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Migrate agency text data to agencies table BEFORE dropping the column
        if (Schema::hasColumn('clients', 'agency') && Schema::hasTable('agencies')) {
            $agencyNames = DB::table('clients')
                ->whereNotNull('agency')
                ->where('agency', '!=', '')
                ->distinct()
                ->pluck('agency');

            foreach ($agencyNames as $name) {
                $agency = DB::table('agencies')->where('name', trim($name))->first();
                if (! $agency) {
                    $agencyId = DB::table('agencies')->insertGetId([
                        'name' => trim($name),
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                } else {
                    $agencyId = $agency->id;
                }

                DB::table('clients')
                    ->where('agency', $name)
                    ->whereNull('agency_id')
                    ->update(['agency_id' => $agencyId]);
            }
        }

        if (Schema::hasColumn('clients', 'agency')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->dropColumn('agency');
            });
        }
    }

    public function down(): void
    {
        if (! Schema::hasColumn('clients', 'agency')) {
            Schema::table('clients', function (Blueprint $table) {
                $table->string('agency')->default('')->after('name');
            });
        }
    }
};
