<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Client;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use App\Models\Campaign;
use App\Models\CampaignData;
use App\Models\PlacementData;
use Illuminate\Support\Carbon;

class InitialSeed extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // Create Clients
        $client1 = Client::create(['name' => 'client 1']);
        $client2 = Client::create(['name' => 'client 2']);

        // Create Users
        $user1 = User::create([
            'name' => 'User 1',
            'email' => 'user1@example.com',
            'password' => Hash::make('password1'),
        ]);

        $user2 = User::create([
            'name' => 'User 2',
            'email' => 'user2@example.com',
            'password' => Hash::make('password2'),
        ]);

        $user3 = User::create([
            'name' => 'User 3',
            'email' => 'user3@example.com',
            'password' => Hash::make('password3'),
        ]);

        // Assign client access
        $user1->clients()->attach($client1->id);
        $user2->clients()->attach($client2->id);
        $user3->clients()->attach([$client1->id, $client2->id]);

        $clients = [$client1, $client2];

        foreach ($clients as $client) {
            for ($i = 1; $i <= 2; $i++) {
                $campaign = Campaign::create([
                    'client_id' => $client->id,
                    'name' => "Campaign {$client->id}-{$i}"
                ]);

                for ($d = 0; $d < 5; $d++) {
                    $date = Carbon::today()->subDays($d);

                    $impressions = rand(1000, 10000);
                    $clicks = (int) round($impressions * rand(5, 10) / 100);
                    $visible = (int) round($impressions * rand(60, 80) / 100);
                    $uniques = (int) round($impressions * rand(30, 60) / 100);

                    // Create campaign_data row
                    CampaignData::create([
                        'campaign_id' => $campaign->id,
                        'report_date' => $date,
                        'impressions' => $impressions,
                        'clicks' => $clicks,
                        'visible_impressions' => $visible,
                        'uniques' => $uniques,
                    ]);

                    // Divide into 4 placements
                    $placementSums = [
                        'impressions' => 0,
                        'clicks' => 0,
                        'visible_impressions' => 0,
                        'uniques' => 0,
                    ];

                    for ($p = 1; $p <= 4; $p++) {
                        // Even split for first 3, remainder goes to 4th
                        $imp = ($p < 4) ? intdiv($impressions, 4) : $impressions - $placementSums['impressions'];
                        $clk = ($p < 4) ? intdiv($clicks, 4) : $clicks - $placementSums['clicks'];
                        $vis = ($p < 4) ? intdiv($visible, 4) : $visible - $placementSums['visible_impressions'];
                        $uni = ($p < 4) ? intdiv($uniques, 4) : $uniques - $placementSums['uniques'];

                        $placementSums['impressions'] += $imp;
                        $placementSums['clicks'] += $clk;
                        $placementSums['visible_impressions'] += $vis;
                        $placementSums['uniques'] += $uni;

                        PlacementData::create([
                            'campaign_id' => $campaign->id,
                            'report_date' => $date,
                            'name' => "Placement {$p}",
                            'impressions' => $imp,
                            'clicks' => $clk,
                            'visible_impressions' => $vis,
                            'uniques' => $uni,
                        ]);
                    }
                }
            }
        }
    }
}
