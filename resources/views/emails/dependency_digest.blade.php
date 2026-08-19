@php
    $palette = [
        'ok' => ['bg' => '#f0fdf4', 'border' => '#16a34a', 'text' => '#166534'],
        'warn' => ['bg' => '#fffbeb', 'border' => '#f59e0b', 'text' => '#92400e'],
        'crit' => ['bg' => '#fef2f2', 'border' => '#dc2626', 'text' => '#991b1b'],
        'stale' => ['bg' => '#f9fafb', 'border' => '#9ca3af', 'text' => '#374151'],
    ];
    $worst = App\Enums\HealthStatus::worstOf(...array_map(fn ($c) => $c->status, $failing));
    $tone = $palette[count($failing) ? $worst->value : 'ok'];
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
        .status-ok { color: #16a34a; }
        .meta { font-size: 13px; color: #6b7280; border-top: 1px solid #eee; padding-top: 12px; }
    </style>
</head>
<body>
    <div class="banner" style="background: {{ $tone['bg'] }}; border-color: {{ $tone['border'] }}; color: {{ $tone['text'] }};">
        <h2>Weekly dependency digest</h2>
        <div class="reason">
            @if (count($failing))
                {{ count($failing) }} item{{ count($failing) === 1 ? '' : 's' }} need attention.
            @else
                Everything is current. Nothing to do.
            @endif
        </div>
    </div>

    @if (count($failing))
        <table>
            <thead>
                <tr><th>Check</th><th>Status</th><th>Value</th><th>Threshold</th></tr>
            </thead>
            <tbody>
                @foreach ($failing as $check)
                    <tr>
                        <td><span class="key">{{ $check->key }}</span> {{ $check->label }}</td>
                        <td class="status status-{{ $check->status->value }}">{{ $check->status->value }}</td>
                        <td>{{ $check->value }}</td>
                        <td style="color:#6b7280;">{{ $check->threshold }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>

        <p style="font-size: 14px;">
            After patching, record it so check d4 stops nagging:
            <code>php artisan deps:mark-patch-run --note="what you did"</code>
        </p>
    @endif

    @if (count($passing))
        <table>
            <thead>
                <tr><th colspan="2">Currently fine</th></tr>
            </thead>
            <tbody>
                @foreach ($passing as $check)
                    <tr>
                        <td><span class="key">{{ $check->key }}</span> {{ $check->label }}</td>
                        <td style="color:#6b7280;">{{ $check->value }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    @endif

    <div class="meta">
        {{-- This digest is sent every week even when there is nothing wrong. A
             report that only appears on bad news is indistinguishable from one
             that has stopped working. --}}
        Snapshot taken {{ $snapshot->generatedAt->toDayDateTimeString() }} ·
        sent weekly whether or not anything is wrong ·
        outages are alerted separately by health:alert.
    </div>
</body>
</html>
