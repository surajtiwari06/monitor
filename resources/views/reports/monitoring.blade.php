<!DOCTYPE html>
<html>
<head>
    <title>Boondock All is Well Report</title>
    <style>
        body {
            font-family: 'DejaVu Sans', sans-serif;
            margin: 20px;
            color: #333;
        }
        h1 {
            text-align: center;
            color: #4CAF50;
        }
        .vendor-section {
            margin-bottom: 40px;
        }
        .card {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0, 0, 0, 0.1);
        }
        table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        table, th, td {
            border: 1px solid #ddd;
        }
        th, td {
            padding: 12px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
        }
        .chart-container {
            margin-top: 20px;
        }
        .no-data {
            text-align: center;
            color: red;
        }
        .report-date {
            text-align: right;
            font-style: italic;
            color: #666;
        }
    </style>
</head>
<body>
    <h1>Boondock All is Well Report</h1>
    <div class="report-date">
        Generated on: {{ \Carbon\Carbon::now()->toDayDateTimeString() }}
    </div>
    @foreach ($customerSites->groupBy('vendor_id') as $vendorId => $sites)
        <div class="vendor-section">
            <h2>{{ $sites->first()->vendor->name }}</h2>
            @foreach ($sites as $customerSite)
                <div class="card">
                    <h3>{{ $customerSite->name }}</h3>
                    <table>
                        <tbody>
                            {{-- <tr><td>{{ __('customer_site.name') }}</td><td>{{ $customerSite->name }}</td></tr> --}}
                            @if($customerSite->vendor->name != "Nodes")
                                <tr>
                                    <td>{{ __('customer_site.url') }}</td>
                                    <td><a target="_blank" href="{{ $customerSite->url }}">{{ $customerSite->url }}</a></td>
                                </tr>
                            @endif
                            {{-- <tr><td>{{ __('vendor.vendor') }}</td><td>{{ $customerSite->vendor->name }}</td></tr> --}}
                            @if($customerSite->vendor->name == "Nodes")
                                <tr>
                                    <td>{{ __('Topic Monitoring') }}</td>
                                    <td>{{ $customerSite->topic }}</td>
                                </tr>
                            @endif
                            <tr>
                                <td>{{ __('app.status') }}</td>
                                <td style="color: {{ $customerSite->is_online ? 'green' : 'red' }}">
                                    {{ $customerSite->is_online ? 'Online' : 'Offline' }}
                                </td>
                            </tr>
                            
                            <tr>
                                <td>{{ __('Average Response Time') }}</td>
                                <td>{{ $averageResponseTimes[$customerSite->id] ? round($averageResponseTimes[$customerSite->id] / 1000, 2) . ' s' : 'No data' }}</td>
                            </tr>
                            <tr>
                                <td>{{ __('customer_site.last_check_at') }}</td>
                                <td>{{ optional($customerSite->last_check_at)->diffForHumans() }}</td>
                            </tr>
                          </tbody>
                    </table>
                    {{-- <div class="chart-container">
                        @if ($charts[$customerSite->id])
                            <img src="{{ $charts[$customerSite->id] }}" alt="Chart for {{ $customerSite->name }}">
                        @else
                            <div class="no-data">No data available for this period.</div>
                        @endif
                    </div> --}}
                </div>
            @endforeach
        </div>
    @endforeach
</body>
</html>
