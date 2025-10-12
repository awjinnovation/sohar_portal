<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #{{ $ticket->id }} - Sohar Festival</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        @page {
            size: A4 landscape;
            margin: 0;
        }
        body {
            background: #f5f5f5;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            margin: 0;
            padding: 20px;
        }
        .ticket-wrapper {
            max-width: 1100px;
            margin: 0 auto;
        }
        .ticket-container {
            background: white;
            border-radius: 15px;
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
            overflow: hidden;
            display: flex;
            height: 400px;
        }
        .ticket-left {
            flex: 1;
            padding: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
        }
        .ticket-right {
            width: 400px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 40px;
            position: relative;
            overflow: hidden;
        }
        .ticket-right::before {
            content: '';
            position: absolute;
            width: 200%;
            height: 200%;
            background: url('{{ asset("sohar_fastival_logo_no_bg.png") }}') center/contain no-repeat;
            opacity: 0.1;
            transform: rotate(-15deg);
        }
        .logo {
            max-width: 120px;
            margin-bottom: 20px;
        }
        .ticket-title {
            font-size: 28px;
            font-weight: bold;
            color: #333;
            margin-bottom: 10px;
        }
        .qr-container {
            background: white;
            padding: 20px;
            border-radius: 15px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.2);
            text-align: center;
            position: relative;
            z-index: 1;
        }
        .qr-container h4 {
            color: #333;
            margin-top: 15px;
            font-size: 16px;
        }
        .ticket-info {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        .info-item {
            display: flex;
            flex-direction: column;
        }
        .info-label {
            font-size: 12px;
            color: #999;
            text-transform: uppercase;
            margin-bottom: 5px;
            font-weight: 600;
        }
        .info-value {
            font-size: 16px;
            color: #333;
            font-weight: 600;
        }
        .ticket-footer {
            border-top: 2px dashed #e0e0e0;
            padding-top: 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .ticket-id {
            font-size: 14px;
            color: #666;
        }
        .status-badge {
            background: #2ecc71;
            color: white;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
        }
        .actions {
            margin-top: 20px;
            display: flex;
            gap: 10px;
            justify-content: center;
        }
        .btn {
            padding: 12px 30px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            display: inline-flex;
            align-items: center;
            gap: 8px;
        }
        .btn-primary {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .actions {
                display: none !important;
            }
            .ticket-wrapper {
                max-width: 100%;
            }
            .ticket-container {
                box-shadow: none;
                page-break-inside: avoid;
            }
        }
        @media (max-width: 768px) {
            .ticket-container {
                flex-direction: column;
                height: auto;
            }
            .ticket-right {
                width: 100%;
                padding: 30px;
            }
            .ticket-info {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="ticket-wrapper">
        <div class="ticket-container">
            <div class="ticket-left">
                <div>
                    <img src="{{ asset('sohar_fastival_logo_no_bg.png') }}" alt="Logo" class="logo">
                    <div class="ticket-title">{{ $ticket->event->title ?? 'Event Ticket' }}</div>

                    <div class="ticket-info">
                        <div class="info-item">
                            <span class="info-label">Date & Time</span>
                            <span class="info-value">{{ $ticket->event->start_time->format('d M Y, h:i A') }}</span>
                        </div>
                        <div class="info-item">
                            <span class="info-label">Holder</span>
                            <span class="info-value">{{ $ticket->holder_name }}</span>
                        </div>
                        @if($ticket->event->mapLocation)
                        <div class="info-item">
                            <span class="info-label">Location</span>
                            <span class="info-value">{{ $ticket->event->mapLocation->name }}</span>
                        </div>
                        @endif
                        <div class="info-item">
                            <span class="info-label">Type</span>
                            <span class="info-value text-uppercase">{{ $ticket->ticket_type }}</span>
                        </div>
                    </div>
                </div>

                <div class="ticket-footer">
                    <span class="ticket-id">Ticket #{{ $ticket->id }} | {{ $ticket->transaction_id }}</span>
                    <span class="status-badge">{{ strtoupper($ticket->status) }}</span>
                </div>
            </div>

            <div class="ticket-right">
                <div class="qr-container">
                    {!! QrCode::size(250)->generate($ticket->qr_code) !!}
                    <h4>Scan to Enter</h4>
                </div>
            </div>
        </div>

        <div class="actions">
            <button class="btn btn-primary" onclick="window.print()">
                <i class="bi bi-printer"></i> Print Ticket
            </button>
            <a href="{{ route('payment.download', $ticket->transaction_id) }}" class="btn btn-secondary">
                <i class="bi bi-list"></i> View All Tickets
            </a>
        </div>
    </div>

    <script>
        // Track ticket view
        if (typeof gtag !== 'undefined') {
            gtag('event', 'ticket_view', {
                ticket_id: '{{ $ticket->id }}',
                event_name: '{{ $ticket->event->title ?? "Unknown" }}'
            });
        }
    </script>
</body>
</html>
