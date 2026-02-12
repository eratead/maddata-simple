<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .client-section { margin-bottom: 20px; border-bottom: 1px solid #eee; padding-bottom: 15px; }
        .client-title { font-size: 18px; font-weight: bold; color: #2d3748; margin-bottom: 10px; }
        .campaign-section { margin-left: 15px; margin-bottom: 15px; }
        .campaign-title { font-size: 16px; font-weight: bold; color: #4a5568; margin-bottom: 5px; }
        .activity-list { list-style-type: none; padding-left: 0; }
        .activity-item { margin-bottom: 5px; font-size: 14px; }
        .action { font-weight: bold; text-transform: capitalize; }
        .action-created { color: #38a169; }
        .action-updated { color: #3182ce; }
        .action-deleted { color: #e53e3e; }
        a { color: #3182ce; text-decoration: none; }
        a:hover { text-decoration: underline; }
    </style>
</head>
<body>
    <h2>New Activity on MadData</h2>
    <p>Here is a summary of the latest activities:</p>

    @foreach($groupedLogs as $clientName => $campaigns)
        <div class="client-section">
            <div class="client-title">Client: {{ $clientName }}</div>
            
            @foreach($campaigns as $campaignName => $logs)
                @php $campaignId = $logs->first()->campaign_id; @endphp
                <div class="campaign-section">
                    <div class="campaign-title">
                        Campaign: <a href="{{ route('campaigns.edit', $campaignId) }}">{{ $campaignName }}</a>
                    </div>
                    <ul class="activity-list">
                        @foreach($logs as $log)
                            <li class="activity-item">
                                <span class="action action-{{ $log->action }}">{{ $log->action }}</span>: 
                                
                                @if($log->subject_type === 'App\Models\Creative' && $log->subject)
                                    Creative <a href="{{ route('creatives.edit', $log->subject_id) }}">#{{ $log->subject_id }}</a>
                                @elseif($log->subject_type === 'App\Models\CreativeFile' && $log->subject && $log->subject->creative)
                                    File in Creative <a href="{{ route('creatives.edit', $log->subject->creative_id) }}">#{{ $log->subject->creative_id }}</a>
                                @else
                                    {{ class_basename($log->subject_type) }} #{{ $log->subject_id }}
                                @endif
                                
                                - {{ $log->description }}
                                <small style="color: #718096;">(by {{ $log->user->name ?? 'System' }} at {{ $log->created_at->format('H:i') }})</small>
                            </li>
                        @endforeach
                    </ul>
                </div>
            @endforeach
        </div>
    @endforeach
    
    <p><a href="{{ route('admin.activity-logs.index') }}">View all activity logs</a></p>
</body>
</html>
