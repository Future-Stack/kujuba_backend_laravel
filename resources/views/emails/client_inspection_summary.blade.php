<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Your ConnectToInspect Inspection Summary Report</title>
    <style>
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; margin: 0; padding: 20px; }
        .container { max-width: 650px; margin: 0 auto; background: #ffffff; border-radius: 8px; border: 1px solid #e2e8f0; overflow: hidden; box-shadow: 0 4px 6px -1px rgba(0,0,0,0.05); }
        .header { background-color: #0284c7; color: #ffffff; padding: 24px; text-align: center; }
        .content { padding: 24px; }
        .stats-grid { display: flex; gap: 10px; margin: 20px 0; }
        .stat-box { flex: 1; background: #f1f5f9; padding: 12px; border-radius: 6px; text-align: center; border: 1px solid #e2e8f0; }
        .stat-val { font-size: 20px; font-weight: bold; color: #0f172a; }
        .stat-title { font-size: 11px; text-transform: uppercase; color: #64748b; margin-top: 4px; }
        .footer { background: #f8fafc; padding: 16px; text-align: center; font-size: 12px; color: #94a3b8; border-top: 1px solid #e2e8f0; }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1 style="margin: 0; font-size: 22px;">ConnectToInspect</h1>
            <p style="margin: 6px 0 0 0; font-size: 14px; opacity: 0.9;">{{ $report['period']['frequency'] ?? 'Weekly' }} Inspection Status Summary</p>
        </div>
        <div class="content">
            <p>Hello <strong>{{ $report['client']['name'] ?? $report['client']['company_name'] ?? 'Partner' }}</strong>,</p>
            <p>Here is your automated inspection status report for the period <strong>{{ $report['period']['start_date'] ?? '' }} to {{ $report['period']['end_date'] ?? '' }}</strong>.</p>
            
            <div style="background-color: #f8fafc; border-left: 4px solid #0284c7; padding: 12px 16px; margin: 18px 0; border-radius: 0 6px 6px 0;">
                <p style="margin: 0; font-weight: 600;">Executive Summary:</p>
                <ul style="margin: 8px 0 0 0; padding-left: 20px; font-size: 13px; color: #334155;">
                    <li><strong>Total Bookings:</strong> {{ $report['summary']['total_inspections'] ?? 0 }}</li>
                    <li><strong>Completed & Reports Ready:</strong> {{ $report['summary']['completed_inspections'] ?? 0 }}</li>
                    <li><strong>In Progress / Scheduled:</strong> {{ $report['summary']['in_progress_inspections'] ?? 0 }}</li>
                    <li><strong>Pending Scheduling:</strong> {{ $report['summary']['pending_inspections'] ?? 0 }}</li>
                </ul>
            </div>

            <p style="font-size: 13px; color: #475569;">
                A full detailed PDF breakdown of each homeowner's inspection status, property details, and assigned inspector is attached to this email for your records.
            </p>

            <p style="font-size: 13px; color: #475569; margin-top: 20px;">
                If you have any questions or need to schedule an urgent inspection, please reach out directly to us.
            </p>
        </div>
        <div class="footer">
            ConnectToInspect &bull; <a href="mailto:david@connecttoinspect.com" style="color: #0284c7; text-decoration: none;">david@connecttoinspect.com</a>
        </div>
    </div>
</body>
</html>
