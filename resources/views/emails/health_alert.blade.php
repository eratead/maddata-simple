@php
    use App\Enums\HealthStatus;
    use App\Services\Health\HealthFormat;

    $palette = [
        'ok' => ['bg' => '#f0fdf4', 'border' => '#16a34a', 'text' => '#166534'],
        'warn' => ['bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e'],
        'crit' => ['bg' => '#fef2f2', 'border' => '#dc2626', 'text' => '#991b1b'],
        'stale' => ['bg' => '#f9fafb', 'border' => '#9ca3af', 'text' => '#374151'],
    ];
    $tone = $palette[$snapshot->overall->value] ?? $palette['stale'];
@endphp
<!DOCTYPE html>
<html>
<head>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
        .banner { padding: 14px 18px; border-left: 5px solid; border-radius: 4px; margin-bottom: 20px; }
        .banner h2 { margin: 0 0 4px; font-size: 20px; }
        .banner .reason { font-size: 14px; opacity: .85; }
        table { border-collapse: collapse; width: 100%; font-size: 14px; margin-bottom: 20px; }
        th { text-align: left; background: #f3f4f6; padding: 8px; border-bottom: 2px solid #e5e7eb; }
        td { padding: 8px; border-bottom: 1px solid #eee; vertical-align: top; }
        .key { font-family: monospace; color: #6b7280; white-space: nowrap; }
        .status { font-weight: bold; text-transform: uppercase; font-size: 12px; white-space: nowrap; }
        .status-crit { color: #dc2626; }
        .status-warn { color: #d97706; }
        .status-stale { color: #6b7280; }
        .meta { font-size: 13px; color: #6b7280; border-top: 1px solid #eee; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="banner" style="background: {{ $tone['bg'] }}; border-color: {{ $tone['border'] }}; color: {{ $tone['text'] }};">
        <h2>{{ $isRecovery ? 'All systems go' : strtoupper($snapshot->overall->label()) }}</h2>
        <div class="reason">{{ $reason }}</div>
    </div>

    @if ($isRecovery)
        <p>
            Everything that was failing has recovered.
            @if ($episodeStartedAt)
                The problem lasted {{ HealthFormat::age(max(0, now()->getTimestamp() - $episodeStartedAt)) }}.
            @endif
        </p>
    @else
        <table>
            <thead>
                <tr>
                    <th>Status</th>
                    <th>Check</th>
                    <th>Node</th>
                    <th>Value</th>
                    <th>Threshold</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($failing as $check)
                    <tr>
                        <td class="status status-{{ $check->status->value }}">{{ $check->status->value }}</td>
                        <td>
                            <span class="key">{{ $check->key }}</span><br>
                            {{ $check->label }}
                        </td>
                        <td>{{ $check->node }}</td>
                        <td>{{ $check->value }}</td>
                        <td>{{ $check->threshold }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="font-size: 14px;">
            What each failure means, and the first command to run for it, is in
            <strong>docs/runbooks/health-monitor.md</strong>. From the droplet:
            <code>php artisan health:check</code>
        </p>
    @endif

    <div class="meta">
        Snapshot taken {{ $snapshot->generatedAt->toDayDateTimeString() }} ·
        {{ count($snapshot->checks) }} checks run ·
        {{ count($snapshot->failing()) }} failing
    </div>
</body>
</html>
