<table>
        <thead>
                <tr>
                        <th>Site</th>
                        <th>Impressions</th>
                        <th>Clicks</th>
                        <th>CTR (%)</th>
                </tr>
        </thead>
        <tbody>
                @foreach ($campaignDataByPlacement as $row)
                        <tr>
                                <td>{{ $row['site'] }}</td>
                                <td>{{ $row['impressions'] }}</td>
                                <td>{{ $row['clicks'] }}</td>
                                <td>{{ $row['impressions'] > 0 ? round(($row['clicks'] / $row['impressions']) * 100, 2) : 0 }}
                                </td>
                        </tr>
                @endforeach
        </tbody>
</table>
