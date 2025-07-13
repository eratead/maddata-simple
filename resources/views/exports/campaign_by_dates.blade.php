<table>
        <thead>
                <tr>
                        <th>Date</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR (%)</th>
                        @if ($campaign->is_video)
                                <th>Video 25%</th>
                                <th>Video 50%</th>
                                <th>Video 75%</th>
                                <th>Video Completes</th>
                        @endif
                </tr>
        </thead>
        <tbody>
                @foreach ($campaignData as $row)
                        <tr>
                                <td>{{ $row['report_date'] }}</td>
                                <td>{{ $row['impressions'] }}</td>
                                <td>{{ $row['clicks'] }}</td>
                                <td>{{ $row['impressions'] > 0 ? round(($row['clicks'] / $row['impressions']) * 100, 2) : 0 }}
                                </td>
                                @if ($campaign->is_video)
                                        <td>{{ $row['video_25'] ?? 0 }}</td>
                                        <td>{{ $row['video_50'] ?? 0 }}</td>
                                        <td>{{ $row['video_75'] ?? 0 }}</td>
                                        <td>{{ $row['video_100'] ?? 0 }}</td>
                                @endif
                        </tr>
                @endforeach
        </tbody>
</table>
