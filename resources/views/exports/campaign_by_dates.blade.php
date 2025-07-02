<table>
        <thead>
                <tr>
                        <th>Date</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR (%)</th>
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
                        </tr>
                @endforeach
        </tbody>
</table>
