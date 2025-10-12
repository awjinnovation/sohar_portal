<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Download Tickets - Sohar Festival</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .container {
            max-width: 900px;
        }
        .header-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            padding: 40px;
            margin-bottom: 30px;
            text-align: center;
        }
        .ticket-card {
            background: white;
            border-radius: 15px;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
            padding: 30px;
            margin-bottom: 20px;
            position: relative;
            overflow: hidden;
        }
        .ticket-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, #667eea 0%, #764ba2 100%);
        }
        .qr-code {
            width: 150px;
            height: 150px;
            background: #f8f9fa;
            border: 3px solid #e0e0e0;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 20px;
        }
        .ticket-info {
            border-top: 2px dashed #ddd;
            padding-top: 20px;
            margin-top: 20px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #f0f0f0;
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .btn-download-all {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            transition: transform 0.2s;
        }
        .btn-download-all:hover {
            transform: translateY(-2px);
        }
        .btn-download-ticket {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
            border: none;
            padding: 10px 20px;
            border-radius: 8px;
            font-weight: 600;
            font-size: 14px;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
            margin-top: 10px;
        }
        .btn-download-ticket:hover {
            transform: translateY(-2px);
            color: white;
        }
        @media print {
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header-card">
            <i class="bi bi-ticket-perforated text-primary" style="font-size: 60px;"></i>
            <h1 class="mt-3">Your Tickets</h1>
            <p class="text-muted">Transaction ID: {{ $payment->transaction_id }}</p>
            <p class="text-muted">Amount Paid: {{ $payment->amount }} {{ $payment->currency }}</p>
            <button class="btn btn-download-all mt-3" onclick="window.print()">
                <i class="bi bi-printer"></i> Print All Tickets
            </button>
        </div>

        @foreach($tickets as $index => $ticket)
        <div class="ticket-card">
            <div class="row">
                <div class="col-md-4 text-center">
                    <div class="qr-code">
                        <i class="bi bi-qr-code" style="font-size: 80px; color: #333;"></i>
                    </div>
                    <small class="text-muted">Scan at entrance</small>
                </div>

                <div class="col-md-8">
                    <h4 class="mb-3">Ticket #{{ $index + 1 }}</h4>

                    <div class="ticket-info">
                        @if($ticket->event)
                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-calendar-event"></i> Event</span>
                            <strong>{{ $ticket->event->title }}</strong>
                        </div>
                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-clock"></i> Date</span>
                            <strong>{{ $ticket->event->start_time->format('d M Y, h:i A') }}</strong>
                        </div>
                        @if($ticket->event->mapLocation)
                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-geo-alt"></i> Location</span>
                            <strong>{{ $ticket->event->mapLocation->name }}</strong>
                        </div>
                        @endif
                        @endif

                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-person"></i> Holder</span>
                            <strong>{{ $payment->user->name }}</strong>
                        </div>

                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-hash"></i> QR Code</span>
                            <small class="font-monospace">{{ $ticket->qr_code }}</small>
                        </div>

                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-tag"></i> Ticket Type</span>
                            <strong class="text-uppercase">{{ $ticket->ticket_type }}</strong>
                        </div>

                        <div class="info-item">
                            <span class="text-muted"><i class="bi bi-check-circle"></i> Status</span>
                            <span class="badge bg-success">{{ strtoupper($ticket->status) }}</span>
                        </div>
                    </div>

                    <div class="text-center no-print">
                        <a href="{{ route('payment.ticket.single', $ticket->qr_code) }}" class="btn-download-ticket" target="_blank">
                            <i class="bi bi-file-pdf"></i> Download This Ticket
                        </a>
                    </div>
                </div>
            </div>
        </div>
        @endforeach

        <div class="alert alert-info text-center">
            <i class="bi bi-info-circle"></i>
            <strong>Important:</strong> Please present these tickets (printed or on your phone) at the festival entrance. Each ticket is valid for one-time entry only.
        </div>
    </div>

    <script>
        // Track ticket download
        if (typeof gtag !== 'undefined') {
            gtag('event', 'ticket_download', {
                transaction_id: '{{ $payment->transaction_id }}',
                ticket_count: {{ $tickets->count() }}
            });
        }
    </script>
</body>
</html>
