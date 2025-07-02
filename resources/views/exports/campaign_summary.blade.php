<table>
        <thead>
                <tr>
                        <th colspan="2">Campaign Summary</th>
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
                        <td>Visibility Rate (%)</td>
                        <td>{{ $summary['visibility'] }}</td>
                </tr>
                <tr>
                        <td>Frequency</td>
                        <td>{{ $summary['unique_users'] > 0 ? round($summary['impressions'] / $summary['unique_users'], 2) : 0 }}
                        </td>
                </tr>
                @if ($user->can_view_budget)
                        <tr>
                                <td>Budget</td>
                                <td>{{ $summary['budget'] }}</td>
                        </tr>
                        <tr>
                                <td>Spent</td>
                                <td>{{ round($summary['spent'], 2) }}</td>
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
        </tbody>
</table>
