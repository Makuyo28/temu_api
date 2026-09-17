<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket {{ $ticket->ticket_number }} — TEMU</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif;
            background: #F5F6F8;
            color: #1F2937;
            padding: 16px;
            line-height: 1.4;
        }
        .card {
            max-width: 560px;
            margin: 0 auto;
            background: #fff;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
        }
        .header {
            background: #16233F;
            color: #fff;
            padding: 24px 20px;
            text-align: center;
        }
        .header .eyebrow {
            font-size: 11px;
            letter-spacing: 2px;
            text-transform: uppercase;
            color: #F0B429;
            margin-bottom: 6px;
        }
        .header h1 { font-size: 20px; }
        .header .subtitle { font-size: 13px; color: #A9B3C9; margin-top: 4px; }
        .ticket-number {
            text-align: center;
            padding: 16px;
            background: #F5F6F8;
            font-family: monospace;
            font-size: 20px;
            font-weight: bold;
            color: #16233F;
            letter-spacing: 1px;
        }
        .status {
            display: inline-block;
            padding: 6px 14px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            margin: 12px auto 0;
            letter-spacing: 0.5px;
        }
        .status-issued { background: #FBF1DC; color: #92600A; }
        .status-paid { background: #E5F2EA; color: #1E8449; }
        .status-contested { background: #FBEAE2; color: #C2541F; }
        .status-dismissed { background: #FBE7E9; color: #C8202F; }
        .status-partial_paid { background: #E9ECF2; color: #16233F; }
        .center { text-align: center; padding: 0 20px 16px; }
        .section {
            padding: 16px 20px;
            border-bottom: 1px solid #F0F1F3;
        }
        .section:last-child { border-bottom: none; }
        .section-title {
            font-size: 12px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748B;
            margin-bottom: 10px;
        }
        .row {
            display: flex;
            justify-content: space-between;
            padding: 6px 0;
            gap: 12px;
        }
        .row .label { color: #64748B; font-size: 14px; flex-shrink: 0; }
        .row .value {
            font-weight: 500;
            font-size: 14px;
            text-align: right;
            word-break: break-word;
        }
        .violation {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px dashed #E9ECF2;
            gap: 12px;
        }
        .violation:last-of-type { border-bottom: none; }
        .total {
            display: flex;
            justify-content: space-between;
            padding: 14px 0 0;
            margin-top: 8px;
            border-top: 2px solid #16233F;
            font-size: 16px;
            font-weight: 700;
        }
        .total .value { color: #C8202F; }
        .footer {
            text-align: center;
            padding: 16px;
            font-size: 11px;
            color: #94A3B8;
            background: #FAFBFC;
        }
        .offline {
            text-align: center;
            background: #FBF1DC;
            color: #92600A;
            padding: 8px;
            font-size: 12px;
            font-weight: 600;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="header">
            <div class="eyebrow">Traffic Enforcement and Management Unit</div>
            <h1>🚦 TEMU Traffic Ticket</h1>
            <div class="subtitle">El Salvador City</div>
        </div>

        <div class="ticket-number">#{{ $ticket->ticket_number }}</div>

        <div class="center">
            <span class="status status-{{ $ticket->status }}">
                {{ strtoupper(str_replace('_', ' ', $ticket->status)) }}
            </span>
        </div>

        <div class="section">
            <div class="section-title">Violator</div>
            <div class="row">
                <span class="label">Name</span>
                <span class="value">
                    {{ $ticket->violator->firstname ?? '' }}
                    {{ $ticket->violator->lastname ?? '' }}
                </span>
            </div>
            <div class="row">
                <span class="label">License</span>
                <span class="value">{{ $ticket->violator->license ?? 'N/A' }}</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Vehicle</div>
            <div class="row">
                <span class="label">Plate Number</span>
                <span class="value">{{ $ticket->vehicle->platenumber ?? 'N/A' }}</span>
            </div>
            <div class="row">
                <span class="label">Owner</span>
                <span class="value">{{ $ticket->vehicle->owner ?? 'N/A' }}</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Violations</div>
            @forelse ($ticket->violations as $v)
                <div class="violation">
                    <span>{{ $v->violationType->violation_name ?? 'Unknown' }}</span>
                    <span>₱{{ number_format($v->fine_amount, 2) }}</span>
                </div>
            @empty
                <div class="row"><span class="label">No violations listed</span></div>
            @endforelse
            <div class="total">
                <span>TOTAL FINE</span>
                <span class="value">₱{{ number_format($ticket->total_fine, 2) }}</span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Location &amp; Time</div>
            <div class="row">
                <span class="label">Location</span>
                <span class="value">{{ $ticket->location }}</span>
            </div>
            <div class="row">
                <span class="label">Date &amp; Time</span>
                <span class="value">
                    {{ \Carbon\Carbon::parse($ticket->violation_datetime)->format('M d, Y h:i A') }}
                </span>
            </div>
        </div>

        <div class="section">
            <div class="section-title">Issued By</div>
            <div class="row">
                <span class="label">Enforcer</span>
                <span class="value">
                    {{ $ticket->enforcer->firstname ?? '' }}
                    {{ $ticket->enforcer->lastname ?? '' }}
                </span>
            </div>
        </div>

        <div class="footer">
            This is an official traffic violation ticket issued by TEMU.<br>
            For questions, please contact the TEMU office.
        </div>
    </div>
</body>
</html>