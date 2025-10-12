<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Successful - Sohar Festival</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .success-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .success-header {
            background: linear-gradient(135deg, #2ecc71 0%, #27ae60 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .success-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: scaleIn 0.5s ease-out;
        }
        @keyframes scaleIn {
            from { transform: scale(0); }
            to { transform: scale(1); }
        }
        .success-body {
            padding: 40px;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid #eee;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .info-label {
            color: #666;
            font-weight: 500;
        }
        .info-value {
            color: #333;
            font-weight: 600;
        }
        .btn-download {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            padding: 15px 40px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 16px;
            color: white;
            text-decoration: none;
            display: inline-block;
            transition: transform 0.2s;
        }
        .btn-download:hover {
            transform: translateY(-2px);
            color: white;
        }
    </style>
</head>
<body>
    <div class="success-card">
        <div class="success-header">
            <i class="bi bi-check-circle-fill success-icon"></i>
            <h1>Payment Successful!</h1>
            <p class="mb-0">Your tickets have been purchased successfully</p>
        </div>

        <div class="success-body">
            <div class="info-row">
                <span class="info-label">Transaction ID</span>
                <span class="info-value">{{ $transactionId }}</span>
            </div>

            <div class="info-row">
                <span class="info-label">Amount Paid</span>
                <span class="info-value">{{ $payment->amount }} {{ $payment->currency }}</span>
            </div>

            <div class="info-row">
                <span class="info-label">Payment Status</span>
                <span class="info-value text-success">
                    <i class="bi bi-check-circle"></i> Completed
                </span>
            </div>

            <div class="info-row">
                <span class="info-label">Payment Date</span>
                <span class="info-value">{{ $payment->paid_at->format('d M Y, h:i A') }}</span>
            </div>

            @if($payment->payment_type === 'ticket')
            <div class="info-row">
                <span class="info-label">Tickets Quantity</span>
                <span class="info-value">{{ $payment->metadata['quantity'] ?? 1 }}</span>
            </div>
            @endif

            <div class="text-center mt-4">
                <a href="{{ route('payment.download', $transactionId) }}" class="btn-download">
                    <i class="bi bi-download"></i> Download Tickets
                </a>
            </div>

            <div class="alert alert-info mt-4">
                <i class="bi bi-info-circle"></i>
                <small>A confirmation has been sent to your registered phone number. You can download your tickets anytime using the button above.</small>
            </div>
        </div>
    </div>

    <script>
        // Track successful payment
        if (typeof gtag !== 'undefined') {
            gtag('event', 'purchase', {
                transaction_id: '{{ $transactionId }}',
                value: {{ $payment->amount }},
                currency: '{{ $payment->currency }}',
                items: [{
                    item_name: '{{ $payment->payment_type === "ticket" ? "Festival Ticket" : "Workshop Registration" }}',
                    quantity: {{ $payment->metadata['quantity'] ?? 1 }}
                }]
            });
        }
    </script>
</body>
</html>
