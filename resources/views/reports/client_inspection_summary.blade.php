<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Client Inspection Summary Report</title>
    <style>
        @page {
            margin: 25px 25px 35px 25px;
        }
        body {
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            font-size: 11px;
            color: #1e293b;
            line-height: 1.4;
            margin: 0;
            padding: 0;
        }
        .header-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
            border-bottom: 2px solid #0284c7;
            padding-bottom: 12px;
        }
        .header-table td {
            vertical-align: middle;
        }
        .company-title {
            font-size: 20px;
            font-weight: bold;
            color: #0369a1;
            letter-spacing: -0.5px;
        }
        .report-title {
            font-size: 16px;
            font-weight: 600;
            color: #0f172a;
            text-align: right;
        }
        .report-subtitle {
            font-size: 11px;
            color: #64748b;
            text-align: right;
            margin-top: 3px;
        }

        /* Client Info Box */
        .info-table {
            width: 100%;
            margin-bottom: 18px;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 10px;
        }
        .info-table td {
            font-size: 11px;
            vertical-align: top;
        }
        .info-label {
            font-weight: bold;
            color: #475569;
            width: 110px;
        }

        /* Summary Cards Table */
        .cards-table {
            width: 100%;
            border-collapse: separate;
            border-spacing: 8px 0;
            margin-bottom: 20px;
        }
        .cards-table td {
            background-color: #f1f5f9;
            border: 1px solid #cbd5e1;
            border-radius: 6px;
            padding: 10px 12px;
            text-align: center;
            width: 20%;
        }
        .card-num {
            font-size: 18px;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }
        .card-label {
            font-size: 9px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }

        /* Inspections Table */
        .data-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 10px;
        }
        .data-table th {
            background-color: #0f172a;
            color: #ffffff;
            font-size: 10px;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            padding: 8px 6px;
            text-align: left;
            border: 1px solid #0f172a;
        }
        .data-table td {
            padding: 7px 6px;
            font-size: 10px;
            border-bottom: 1px solid #e2e8f0;
            border-left: 1px solid #f1f5f9;
            border-right: 1px solid #f1f5f9;
            vertical-align: top;
        }
        .data-table tr:nth-child(even) td {
            background-color: #f8fafc;
        }

        /* Badges */
        .badge {
            display: inline-block;
            padding: 3px 6px;
            border-radius: 4px;
            font-size: 9px;
            font-weight: 600;
            text-transform: uppercase;
        }
        .badge-completed {
            background-color: #dcfce7;
            color: #166534;
        }
        .badge-pending {
            background-color: #fef3c7;
            color: #92400e;
        }
        .badge-started, .badge-assigned, .badge-reports {
            background-color: #e0f2fe;
            color: #075985;
        }
        .badge-cancelled {
            background-color: #fee2e2;
            color: #991b1b;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0px;
            left: 0px;
            right: 0px;
            height: 20px;
            font-size: 9px;
            color: #94a3b8;
            border-top: 1px solid #e2e8f0;
            padding-top: 5px;
            text-align: center;
        }
    </style>
</head>
<body>

    <!-- Header -->
    <table class="header-table">
        <tr>
            <td>
                <div class="company-title">ConnectToInspect</div>
                <div style="font-size: 10px; color: #64748b; margin-top: 2px;">Home Inspection Management & Reporting Platform</div>
            </td>
            <td>
                <div class="report-title">Client Inspection Summary Report</div>
                <div class="report-subtitle">Generated: {{ $report['period']['generated_at'] ?? now()->format('Y-m-d H:i') }} (EST)</div>
            </td>
        </tr>
    </table>

    <!-- Client Info -->
    <table class="info-table">
        <tr>
            <td class="info-label">Client / Company:</td>
            <td><strong>{{ $report['client']['company_name'] ?? 'N/A' }}</strong> ({{ $report['client']['name'] ?? '' }})</td>
            <td class="info-label">Report Period:</td>
            <td><strong>{{ $report['period']['frequency'] ?? 'Weekly' }}</strong> ({{ $report['period']['start_date'] ?? '' }} to {{ $report['period']['end_date'] ?? '' }})</td>
        </tr>
        <tr>
            <td class="info-label">Client Type:</td>
            <td>{{ ucfirst(str_replace('_', ' ', $report['client']['client_type'] ?? 'Insurance Company')) }}</td>
            <td class="info-label">Client Email:</td>
            <td>{{ $report['client']['email'] ?? 'N/A' }} | {{ $report['client']['phone'] ?? 'N/A' }}</td>
        </tr>
    </table>

    <!-- Summary Metrics Cards -->
    <table class="cards-table">
        <tr>
            <td>
                <div class="card-num">{{ $report['summary']['total_inspections'] ?? 0 }}</div>
                <div class="card-label">Total Bookings</div>
            </td>
            <td>
                <div class="card-num" style="color: #16a34a;">{{ $report['summary']['completed_inspections'] ?? 0 }}</div>
                <div class="card-label">Completed & Ready</div>
            </td>
            <td>
                <div class="card-num" style="color: #0284c7;">{{ $report['summary']['in_progress_inspections'] ?? 0 }}</div>
                <div class="card-label">In Progress</div>
            </td>
            <td>
                <div class="card-num" style="color: #d97706;">{{ $report['summary']['pending_inspections'] ?? 0 }}</div>
                <div class="card-label">Pending Schedule</div>
            </td>
            <td>
                <div class="card-num" style="color: #4f46e5;">{{ $report['summary']['completion_rate'] ?? '0%' }}</div>
                <div class="card-label">Completion Rate</div>
            </td>
        </tr>
    </table>

    <!-- Inspections Table -->
    <div style="font-size: 12px; font-weight: bold; margin-bottom: 6px; color: #1e293b;">
        Homeowner Inspection Status & Reports ({{ count($report['inspections'] ?? []) }} Records)
    </div>

    <table class="data-table">
        <thead>
            <tr>
                <th style="width: 10%;">Ref #</th>
                <th style="width: 18%;">Homeowner</th>
                <th style="width: 24%;">Property Address</th>
                <th style="width: 16%;">Inspection Type</th>
                <th style="width: 14%;">Inspector</th>
                <th style="width: 10%;">Date</th>
                <th style="width: 8%;">Status</th>
            </tr>
        </thead>
        <tbody>
            @forelse($report['inspections'] ?? [] as $item)
                <tr>
                    <td><strong>{{ $item['reference_no'] }}</strong></td>
                    <td>
                        <strong>{{ $item['homeowner_name'] }}</strong><br>
                        <span style="font-size: 9px; color: #64748b;">{{ $item['homeowner_phone'] }}</span>
                    </td>
                    <td>
                        {{ $item['property_address'] }}<br>
                        <span style="font-size: 9px; color: #64748b;">Type: {{ ucfirst($item['property_type']) }}</span>
                    </td>
                    <td>{{ $item['inspection_types'] }}</td>
                    <td>{{ $item['inspector_name'] }}</td>
                    <td>{{ $item['scheduled_date'] }}</td>
                    <td>
                        @php
                            $st = strtolower($item['status']);
                            $badgeClass = 'badge-pending';
                            if ($st === 'completed') $badgeClass = 'badge-completed';
                            elseif (in_array($st, ['started', 'assigned', 'reports'])) $badgeClass = 'badge-started';
                            elseif ($st === 'cancelled') $badgeClass = 'badge-cancelled';
                        @endphp
                        <span class="badge {{ $badgeClass }}">{{ $item['status'] }}</span>
                    </td>
                </tr>
            @empty
                <tr>
                    <td colspan="7" style="text-align: center; padding: 18px; color: #64748b;">
                        No inspection bookings recorded for this client in the selected date range.
                    </td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <!-- Footer -->
    <div class="footer">
        ConnectToInspect &bull; automated reporting &bull; For inquiries contact: david@connecttoinspect.com
    </div>

</body>
</html>
