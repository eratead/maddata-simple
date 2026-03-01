<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;

class UpdateCampaignStatuses extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'campaigns:generate-status';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Automatically updates campaign statuses based on their start and end dates.';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        // 1. Activate campaigns that start precisely today and are currently paused
        $activated = \App\Models\Campaign::whereDate('start_date', today())
            ->where('status', 'paused')
            ->update(['status' => 'active']);

        // 2. Pause campaigns where the end date has passed and they are still active
        $paused = \App\Models\Campaign::whereDate('end_date', '<', today())
            ->where('status', 'active')
            ->update(['status' => 'paused']);

        $this->info("Campaign Status Update Complete: {$activated} activated, {$paused} paused.");
    }
}
