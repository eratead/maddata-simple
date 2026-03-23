<?php

namespace App\Observers;

use App\Models\Campaign;
use App\Services\ActivityLogger;
use Illuminate\Contracts\Events\ShouldHandleEventsAfterCommit;

class CampaignObserver implements ShouldHandleEventsAfterCommit
{
    protected $logger;

    public function __construct(ActivityLogger $logger)
    {
        $this->logger = $logger;
    }

    public function created(Campaign $campaign): void
    {
        $this->logger->log('created', $campaign, 'Created campaign "'.$campaign->name.'"');
    }

    public function updated(Campaign $campaign): void
    {
        if ($campaign->isDirty('creative_optimization')) {
            $newValue = $campaign->creative_optimization;
            $message = $newValue
                ? 'Creative optimisation changed to CTR'
                : 'Creative optimisation changed to equal weights';

            $this->logger->log('updated', $campaign, $message, [
                'creative_optimization' => $newValue,
            ]);
        }

        if ($campaign->isDirty('targeting_rules')) {
            $raw = $campaign->getOriginal('targeting_rules');
            $oldRules = is_array($raw) ? $raw : (json_decode($raw ?? '{}', true) ?? []);
            $newRules = $campaign->targeting_rules ?? [];

            $fieldLabels = [
                'genders' => 'Gender',
                'ages' => 'Age',
                'incomes' => 'Income',
                'device_types' => 'Device Types',
                'os' => 'OS',
                'connection_types' => 'Connection',
                'environments' => 'Environment',
                'days' => 'Days',
                'time_start' => 'Time Start',
                'time_end' => 'Time End',
                'countries' => 'Countries',
                'regions' => 'Regions',
                'cities' => 'Cities',
            ];

            $diffs = [];
            foreach ($fieldLabels as $key => $label) {
                $old = $oldRules[$key] ?? [];
                $new = $newRules[$key] ?? [];

                // Decode cities JSON string if needed
                if ($key === 'cities') {
                    if (is_string($old)) {
                        $old = json_decode($old, true) ?? [];
                    }
                    if (is_string($new)) {
                        $new = json_decode($new, true) ?? [];
                    }
                }

                $oldNorm = is_array($old) ? $old : [$old];
                $newNorm = is_array($new) ? $new : [$new];
                sort($oldNorm);
                sort($newNorm);

                if ($oldNorm === $newNorm) {
                    continue;
                }

                $fmt = fn ($v) => empty($v) ? 'All' : implode(', ', $v);
                $diffs[$key] = [
                    'label' => $label,
                    'old' => $fmt($oldNorm),
                    'new' => $fmt($newNorm),
                ];
            }

            if (! empty($diffs)) {
                $parts = array_map(
                    fn ($d) => "{$d['label']}: {$d['old']} → {$d['new']}",
                    $diffs
                );

                $this->logger->log(
                    'updated',
                    $campaign,
                    'Targeting updated: '.implode('; ', $parts),
                    [
                        'targeting_rules' => [
                            'old' => $oldRules,
                            'new' => $newRules,
                        ],
                    ]
                );
            }
        }
    }
}
