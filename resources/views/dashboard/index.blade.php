<x-app-layout>
        <x-title>Dashboard – {{ $campaign->name }}</x-title>
        <x-page-box>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
                        <!-- Campaign Summary -->
                        <div class="md:col-span-1 bg-white shadow rounded p-4">
                                <h2 class="text-lg font-semibold mb-2">Summary</h2>
                                <ul class="text-sm text-gray-700 space-y-1">
                                        <li><strong>Impressions:</strong>
                                                {{ number_format($summary['impressions'] ?? 0) }}</li>
                                        <li><strong>Clicks:</strong> {{ number_format($summary['clicks'] ?? 0) }}</li>
                                        <li><strong>CTR:</strong>
                                                @if (!empty($summary['impressions']))
                                                        {{ number_format(($summary['clicks'] / $summary['impressions']) * 100, 2) }}%
                                                @else
                                                        —
                                                @endif
                                        </li>
                                        <li><strong>Reach:</strong> {{ number_format($summary['uniques'] ?? 0) }}
                                        </li>
                                        <li><strong>Frequency:</strong>
                                                @if (!empty($summary['uniques']))
                                                        {{ number_format($summary['impressions'] / $summary['uniques'], 2) }}
                                                @else
                                                        —
                                                @endif
                                        </li>
                                        <li class="flex items-center space-x-2">
                                                <strong class="whitespace-nowrap">Pacing :</strong>
                                                @if (!empty($summary['expected_impressions']))
                                                        <div title="{{ number_format(($summary['impressions'] / $summary['expected_impressions']) * 100, 2) }}%"
                                                                class="relative w-24 h-3 bg-gray-200 rounded overflow-hidden">
                                                                <div class="absolute top-0 left-0 h-full bg-green-500"
                                                                        style="width: {{ min(100, ($summary['impressions'] / $summary['expected_impressions']) * 100) }}%">
                                                                </div>
                                                        </div>
                                                @else
                                                        <span>—</span>
                                                @endif
                                        </li>
                                        <li><strong>Viewability:</strong>
                                                @if (!empty($summary['impressions']))
                                                        {{ number_format(($summary['visible'] / $summary['impressions']) * 100, 2) }}%
                                                @else
                                                        —
                                                @endif
                                        </li>
                                </ul>

                                <div class="mt-4">
                                        <form action="{{ route('dashboard.export.excel', $campaign->id) }}"
                                                method="GET" class="flex flex-col space-y-2">
                                                <input type="hidden" name="start_date"
                                                        value="{{ request('start_date') }}">
                                                <input type="hidden" name="end_date"
                                                        value="{{ request('end_date') }}">
                                                <button type="submit"
                                                        class="px-4 py-2 bg-indigo-600 text-white text-sm rounded hover:bg-indigo-700 transition">
                                                        Download Excel
                                                </button>
                                        </form>
                                </div>
                        </div>

                        <!-- Tabs -->
                        <div class="bg-white shadow rounded p-4 md:col-span-3 overflow-x-scroll hide-scrollbar"
                                x-data="{ activeTab: 'date' }">
                                <x-dates-filter action="{{ route('dashboard.campaign', $campaign->id) }}"
                                        :first-report-date="$firstReportDate" />

                                <div class="border-b border-gray-200 mb-4">
                                        <nav class="-mb-px flex space-x-4">
                                                <a href="#" @click.prevent="activeTab = 'date'"
                                                        :class="activeTab === 'date' ?
                                                            'text-blue-600 border-blue-600 border-b-2' : 'text-gray-500'"
                                                        class="px-3 py-2 text-sm font-medium border-b-2">
                                                        By Date
                                                </a>
                                                <a href="#" @click.prevent="activeTab = 'placement'"
                                                        :class="activeTab === 'placement' ?
                                                            'text-blue-600 border-blue-600 border-b-2' : 'text-gray-500'"
                                                        class="px-3 py-2 text-sm font-medium border-b-2">
                                                        By Placement
                                                </a>
                                        </nav>
                                </div>

                                <div x-show="activeTab === 'date'" x-cloak>
                                        <x-scripts.datatables table-id="date-table" :order="[[1, 'desc']]" />

                                        <table id="date-table"
                                                class="min-w-full text-sm text-gray-700 border rounded overflow-hidden">
                                                <thead
                                                        class="bg-gray-100 text-xs font-semibold uppercase text-gray-500">
                                                        <tr>
                                                                <th class="px-4 py-2 text-left">Date</th>
                                                                <th class="px-4 py-2 text-left">Impressions</th>
                                                                <th class="px-4 py-2 text-left">Clicks</th>
                                                                <th class="px-4 py-2 text-left">CTR</th>
                                                        </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                        @foreach ($campaignData as $row)
                                                                <tr>
                                                                        <td class="px-4 py-2">
                                                                                {{ \Carbon\Carbon::parse($row->report_date)->format('Y-m-d') }}
                                                                        </td>
                                                                        <td class="px-4 py-2">
                                                                                {{ number_format($row->impressions) }}
                                                                        </td>
                                                                        <td class="px-4 py-2">
                                                                                {{ number_format($row->clicks) }}</td>
                                                                        <td class="px-4 py-2">
                                                                                @if ($row->impressions > 0)
                                                                                        {{ number_format(($row->clicks / $row->impressions) * 100, 2) }}%
                                                                                @else
                                                                                        —
                                                                                @endif
                                                                        </td>
                                                                </tr>
                                                        @endforeach
                                                </tbody>
                                        </table>
                                </div>

                                <div x-show="activeTab === 'placement'" x-cloak>
                                        <x-scripts.datatables table-id="placement-table" :order="[[1, 'desc']]" />

                                        <table id="placement-table"
                                                class="min-w-full text-sm text-gray-700 border rounded overflow-hidden">
                                                <thead
                                                        class="bg-gray-100 text-xs font-semibold uppercase text-gray-500">
                                                        <tr>
                                                                <th class="px-4 py-2 text-left">Placement</th>
                                                                <th class="px-4 py-2 text-left">Impressions</th>
                                                                <th class="px-4 py-2 text-left">Clicks</th>
                                                                <th class="px-4 py-2 text-left">CTR</th>

                                                        </tr>
                                                </thead>
                                                <tbody class="divide-y divide-gray-100">
                                                        @foreach ($placementData as $row)
                                                                <tr>
                                                                        <td class="px-4 py-2">{{ $row->name }}</td>
                                                                        <td class="px-4 py-2">
                                                                                {{ number_format($row->impressions) }}
                                                                        </td>
                                                                        <td class="px-4 py-2">
                                                                                {{ number_format($row->clicks) }}</td>
                                                                        <td class="px-4 py-2">
                                                                                @if ($row->impressions > 0)
                                                                                        {{ number_format(($row->clicks / $row->impressions) * 100, 2) }}%
                                                                                @else
                                                                                        —
                                                                                @endif
                                                                        </td>
                                                                </tr>
                                                        @endforeach
                                                </tbody>
                                        </table>
                                </div>
                        </div>
                </div>
        </x-page-box>

        <x-page-box>

                <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
                <canvas id="campaignChart" style="width: 100%; height: 400px;"></canvas>
                <script>
                        document.addEventListener('DOMContentLoaded', () => {
                                const ctx = document.getElementById('campaignChart');
                                if (!ctx) return;

                                new Chart(ctx, {
                                        type: 'line',
                                        data: {
                                                labels: {!! json_encode($chartLabels) !!},
                                                datasets: [{
                                                                label: 'Impressions',
                                                                data: {!! json_encode($chartImpressions) !!},
                                                                borderColor: 'rgba(59, 130, 246, 1)',
                                                                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                                                                fill: true,
                                                                tension: 0.4,
                                                                yAxisID: 'y',
                                                        },
                                                        {
                                                                label: 'CTR (%)',
                                                                data: {!! json_encode(array_map(fn($i, $c) => $i ? round(($c / $i) * 100, 2) : 0, $chartImpressions, $chartClicks)) !!},
                                                                borderColor: 'rgba(234, 179, 8, 1)',
                                                                backgroundColor: 'rgba(234, 179, 8, 0.1)',
                                                                fill: true,
                                                                tension: 0.4,
                                                                yAxisID: 'y1',
                                                        }
                                                ]
                                        },
                                        options: {
                                                responsive: true,
                                                maintainAspectRatio: true,
                                                plugins: {
                                                        legend: {
                                                                position: 'top',
                                                        },
                                                        title: {
                                                                display: true,
                                                                text: 'Test Chart'
                                                        }
                                                },
                                                scales: {
                                                        y: {
                                                                type: 'linear',
                                                                position: 'left',
                                                                title: {
                                                                        display: true,
                                                                        text: 'Impressions'
                                                                }
                                                        },
                                                        y1: {
                                                                type: 'linear',
                                                                position: 'right',
                                                                title: {
                                                                        display: true,
                                                                        text: 'CTR (%)'
                                                                },
                                                                grid: {
                                                                        drawOnChartArea: false
                                                                }
                                                        }
                                                }
                                        }
                                });
                        });
                </script>
        </x-page-box>
</x-app-layout>
