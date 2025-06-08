<!DOCTYPE html>
<html>

<head>
        <meta charset="UTF-8">
        <style>
                table {
                        border-collapse: collapse;
                        width: 100%;
                        margin-top: 20px;
                }

                th,
                td {
                        border: 1px solid #cccccc;
                        padding: 8px;
                        text-align: left;
                }

                h1,
                h2 {
                        margin-bottom: 10px;
                }
        </style>
</head>

<body>
        <table style="margin: auto; text-align: center; margin-bottom: 20px;">
                <tr>
                        <td><img src="{{ public_path('images/logo.png') }}" alt="Logo" height="80"></td>
                </tr>
                <tr>
                        <td>
                                <h1>Campaign {{ $campaign->name }} data from {{ $startDate }} to {{ $endDate }}
                                </h1>
                        </td>
                </tr>
        </table>

        <h2>Summary</h2>
        <table>
                <tr>
                        <th>Total Impressions</th>
                        <th>Total Clicks</th>
                        <th>CTR (%)</th>
                        <th>Unique Users</th>
                        <th>Visibility Rate (%)</th>
                </tr>
                <tr>
                        <td>{{ $summary['impressions'] }}</td>
                        <td>{{ $summary['clicks'] }}</td>
                        <td>{{ $summary['ctr'] }}</td>
                        <td>{{ $summary['unique_users'] }}</td>
                        <td>{{ $summary['visibility'] }}</td>
                </tr>
        </table>

        <h2>Data by Date</h2>
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
                                        <td>{{ $row->report_date }}</td>
                                        <td>{{ $row->impressions }}</td>
                                        <td>{{ $row->clicks }}</td>
                                        <td>{{ $row->impressions ? round(($row->clicks / $row->impressions) * 100, 2) : 0 }}
                                        </td>

                                </tr>
                        @endforeach
                </tbody>
        </table>
</body>

</html>
