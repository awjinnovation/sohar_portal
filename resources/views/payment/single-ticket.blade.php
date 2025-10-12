<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket #{{ $ticket->id }} - Sohar Festival</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2canvas/1.4.1/html2canvas.min.js"></script>
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 40px 20px;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .ticket-container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            overflow: hidden;
        }
        .ticket-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
            position: relative;
        }
        .logo-container {
            margin-bottom: 20px;
        }
        .logo-container img {
            max-width: 150px;
            height: auto;
            filter: brightness(0) invert(1);
        }
        .ticket-header::after {
            content: '';
            position: absolute;
            bottom: -20px;
            left: 0;
            right: 0;
            height: 40px;
            background: white;
            border-radius: 50% 50% 0 0 / 100% 100% 0 0;
        }
        .ticket-body {
            padding: 60px 40px 40px;
        }
        .qr-section {
            text-align: center;
            margin: 40px 0;
            padding: 30px;
            background: #f8f9fa;
            border-radius: 15px;
        }
        .qr-code {
            background: white;
            border: 5px solid #667eea;
            border-radius: 15px;
            display: inline-block;
            padding: 15px;
            margin: 0 auto 20px;
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .qr-code img {
            display: block;
        }
        .ticket-details {
            margin-top: 30px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 2px dashed #e0e0e0;
        }
        .detail-row:last-child {
            border-bottom: none;
        }
        .detail-label {
            color: #666;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 8px;
        }
        .detail-value {
            color: #333;
            font-weight: 700;
            text-align: right;
        }
        .btn-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            width: 100%;
            margin-top: 20px;
            cursor: pointer;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        .actions-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-top: 20px;
        }
        .btn-print {
            background: linear-gradient(135deg, #3498db 0%, #2980b9 100%);
        }
        @media print {
            body {
                background: white;
                padding: 0;
            }
            .actions-row, .alert {
                display: none !important;
            }
            .ticket-container {
                box-shadow: none;
            }
        }
        .ticket-status {
            display: inline-block;
            padding: 8px 20px;
            border-radius: 20px;
            font-weight: 600;
            font-size: 14px;
            background: #2ecc71;
            color: white;
        }
        .watermark {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 80px;
            font-weight: bold;
            color: rgba(0,0,0,0.03);
            pointer-events: none;
            z-index: 1;
        }
    </style>
</head>
<body>
    <div class="ticket-container" id="ticketContainer">
        <div class="watermark">SOHAR FESTIVAL</div>
        <div class="ticket-header">
            <div class="logo-container">
                <img src="{{ asset('sohar_fastival_logo_no_bg.png') }}" alt="Sohar Festival Logo">
            </div>
            <h1 class="mt-2 mb-2">Sohar Festival 2025</h1>
            <div class="ticket-status">VALID TICKET</div>
        </div>

        <div class="ticket-body">
            <div class="qr-section">
                <div class="qr-code">
                    {!! QrCode::size(200)->generate($ticket->qr_code) !!}
                </div>
                <h5>Scan this code at the entrance</h5>
                <p class="text-muted mb-0 font-monospace small">{{ $ticket->qr_code }}</p>
            </div>

            <div class="ticket-details">
                @if($ticket->event)
                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-calendar-event text-primary"></i>
                        Event
                    </span>
                    <span class="detail-value">{{ $ticket->event->title }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-clock text-primary"></i>
                        Date & Time
                    </span>
                    <span class="detail-value">{{ $ticket->event->start_time->format('d M Y, h:i A') }}</span>
                </div>

                @if($ticket->event->mapLocation)
                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-geo-alt text-primary"></i>
                        Location
                    </span>
                    <span class="detail-value">{{ $ticket->event->mapLocation->name }}</span>
                </div>
                @endif
                @endif

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-person text-primary"></i>
                        Ticket Holder
                    </span>
                    <span class="detail-value">{{ $ticket->user->name }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-tag text-primary"></i>
                        Ticket Type
                    </span>
                    <span class="detail-value text-uppercase">{{ $ticket->ticket_type }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-hash text-primary"></i>
                        Ticket ID
                    </span>
                    <span class="detail-value">#{{ $ticket->id }}</span>
                </div>

                @if($ticket->payment)
                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-receipt text-primary"></i>
                        Transaction ID
                    </span>
                    <span class="detail-value">{{ $ticket->payment->transaction_id }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-currency-exchange text-primary"></i>
                        Price Paid
                    </span>
                    <span class="detail-value">{{ $ticket->price }} {{ $ticket->currency }}</span>
                </div>
                @endif

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-calendar-check text-primary"></i>
                        Purchase Date
                    </span>
                    <span class="detail-value">{{ $ticket->purchase_date->format('d M Y, h:i A') }}</span>
                </div>

                <div class="detail-row">
                    <span class="detail-label">
                        <i class="bi bi-check-circle text-primary"></i>
                        Status
                    </span>
                    <span class="detail-value">
                        <span class="badge bg-success">{{ strtoupper($ticket->status) }}</span>
                    </span>
                </div>
            </div>

            <div class="actions-row">
                <button class="btn-download btn-print" onclick="window.print()">
                    <i class="bi bi-printer"></i> Print
                </button>
                <button class="btn-download" onclick="downloadTicket()">
                    <i class="bi bi-download"></i> Download Image
                </button>
            </div>

            <div class="alert alert-info mt-4 mb-0">
                <i class="bi bi-info-circle"></i>
                <small><strong>Important:</strong> This ticket is valid for one-time entry only. Screenshot or save this ticket for entry at the festival.</small>
            </div>
        </div>
    </div>

    <script>
        function downloadTicket() {
            const ticketContainer = document.getElementById('ticketContainer');
            const downloadBtn = document.querySelector('.actions-row');
            const alert = document.querySelector('.alert');

            // Hide buttons and alert temporarily
            downloadBtn.style.display = 'none';
            alert.style.display = 'none';

            html2canvas(ticketContainer, {
                scale: 2,
                backgroundColor: '#ffffff',
                logging: false,
                useCORS: true
            }).then(canvas => {
                // Restore buttons and alert
                downloadBtn.style.display = 'grid';
                alert.style.display = 'block';

                // Convert to blob and download
                canvas.toBlob(function(blob) {
                    const url = URL.createObjectURL(blob);
                    const link = document.createElement('a');
                    link.href = url;
                    link.download = 'sohar_festival_ticket_{{ $ticket->id }}_{{ $ticket->qr_code }}.png';
                    document.body.appendChild(link);
                    link.click();
                    document.body.removeChild(link);
                    URL.revokeObjectURL(url);
                });
            });
        }

        // Track single ticket view
        if (typeof gtag !== 'undefined') {
            gtag('event', 'ticket_view', {
                ticket_id: '{{ $ticket->id }}',
                event_name: '{{ $ticket->event->title ?? "Unknown" }}'
            });
        }

        // Prevent right-click to add some security
        document.addEventListener('contextmenu', function(e) {
            if (e.target.closest('.qr-code') || e.target.closest('.ticket-container')) {
                e.preventDefault();
                return false;
            }
        });

        // Prevent screenshot shortcuts (basic deterrent)
        document.addEventListener('keyup', function(e) {
            if (e.key == 'PrintScreen') {
                navigator.clipboard.writeText('');
                alert('Screenshots are discouraged. Please use the Download button to save your ticket.');
            }
        });
    </script>
</body>
</html>
