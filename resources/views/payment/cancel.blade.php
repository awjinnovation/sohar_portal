<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Cancelled - Sohar Festival</title>
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
        .cancel-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .cancel-header {
            background: linear-gradient(135deg, #f39c12 0%, #e67e22 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .cancel-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 0.5s ease-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .cancel-body {
            padding: 40px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="cancel-card">
        <div class="cancel-header">
            <i class="bi bi-x-circle-fill cancel-icon"></i>
            <h1>Payment Cancelled</h1>
            <p class="mb-0">Your payment has been cancelled</p>
        </div>

        <div class="cancel-body">
            <p class="lead">No charges have been made to your account.</p>

            <div class="alert alert-warning mt-4">
                <i class="bi bi-info-circle"></i>
                <small>If you experienced any issues during the payment process, please try again or contact our support team.</small>
            </div>

            <div class="mt-4">
                <p class="text-muted">Return to the app to try again</p>
            </div>
        </div>
    </div>

    <script>
        // Track cancelled payment
        if (typeof gtag !== 'undefined') {
            gtag('event', 'checkout_cancelled', {
                session_id: '{{ $sessionId ?? "unknown" }}'
            });
        }
    </script>
</body>
</html>
