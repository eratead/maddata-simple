<?php

namespace App\Console\Commands;

use App\Models\Agency;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class MigrateAgenciesData extends Command
{
    protected $signature = 'migrate:agencies-data';

    protected $description = 'Migrate agency strings from clients table into the new agencies table and set agency_id';

    public function handle(): int
    {
        $this->info('Starting agency data migration...');

        $agencyNames = DB::table('clients')
            ->whereNotNull('agency')
            ->where('agency', '!=', '')
            ->distinct()
            ->pluck('agency');

        if ($agencyNames->isEmpty()) {
            $this->warn('No agency strings found in clients table. Nothing to migrate.');

            return self::SUCCESS;
        }

        $agenciesCreated = 0;
        $clientsUpdated = 0;

        DB::transaction(function () use ($agencyNames, &$agenciesCreated, &$clientsUpdated) {
            foreach ($agencyNames as $name) {
                $agency = Agency::firstOrCreate(['name' => trim($name)]);

                if ($agency->wasRecentlyCreated) {
                    $agenciesCreated++;
                }

                $updated = DB::table('clients')
                    ->where('agency', $name)
                    ->whereNull('agency_id')
                    ->update(['agency_id' => $agency->id]);

                $clientsUpdated += $updated;
            }
        });

        $this->info("Done! Agencies created: {$agenciesCreated}, Clients updated: {$clientsUpdated}");

        return self::SUCCESS;
    }
}
