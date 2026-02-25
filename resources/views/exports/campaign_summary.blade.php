@for ($i = 0; $i < 6; $i++)
        <br>
@endfor


<table>
        <thead>
                <tr>
                        <th colspan="2">{{ $campaign->name }} Summary</th>
                </tr>
        </thead>
        <tbody>
                <tr>
                        <td>Impressions</td>
                        <td>{{ $summary['impressions'] }}</td>
                </tr>
                <tr>
                        <td>Clicks</td>
                        <td>{{ $summary['clicks'] }}</td>
                </tr>
                <tr>
                        <td>CTR (%)</td>
                        <td>{{ $summary['ctr'] }}</td>
                </tr>
                <tr>
                        <td>Reach</td>
                        <td>{{ $summary['unique_users'] }}</td>
                </tr>
                <tr>
                        <td>Frequency</td>
                        <td>{{ $summary['unique_users'] > 0 ? round($summary['impressions'] / $summary['unique_users'], 2) : 0 }}
                        </td>
                </tr>
                <tr>
                        <td>Pacing (%)</td>
                        <td>{{ $summary['impressions'] > 0 ? round(($summary['impressions'] / $summary['expected_impressions']) * 100, 2) : 0 }}
                        </td>
                </tr>
                <tr>
                        <td>Viewability (%)</td>
                        <td>{{ $summary['visibility'] }}</td>
                </tr>
                @if ($user->hasPermission('can_view_budget'))
                        <tr>
                                <td>Budget</td>
                                <td>{{ $summary['budget'] }}</td>
                        </tr>
                        <tr>
                                <td>Spent</td>
                                <td>{{ round($summary['spent'], 0) }}</td>
                        </tr>
                        <tr>
                                <td>CPM</td>
                                <td>{{ round($summary['cpm'], 2) }}</td>
                        </tr>
                        <tr>
                                <td>CPC</td>
                                <td>{{ round($summary['cpc'], 2) }}</td>
                        </tr>
                @endif

                @if ($campaign->is_video)
                        <tr>
                                <td>Video Complete</td>
                                <td>{{ $summary['video_complete'] ?? 0 }}</td>
                        </tr>
                        @if ($user->hasPermission('can_view_budget'))
                                <tr>
                                        <td>Avr. CPV</td>
                                        <td>{{ round($summary['cpv'], 2) }}
                                        </td>
                                </tr>
                        @endif
                        <tr>
                                <td>VCR (%)</td>
                                <td>{{ $summary['vcr'] }}</td>
                        </tr>
                @endif
        </tbody>
</table>
