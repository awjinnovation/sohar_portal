<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Payment Error - Sohar Festival</title>
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
        .error-card {
            background: white;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            max-width: 600px;
            width: 100%;
            overflow: hidden;
        }
        .error-header {
            background: linear-gradient(135deg, #e74c3c 0%, #c0392b 100%);
            color: white;
            padding: 40px 20px;
            text-align: center;
        }
        .error-icon {
            font-size: 80px;
            margin-bottom: 20px;
            animation: shake 0.5s ease-out;
        }
        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            25% { transform: translateX(-10px); }
            75% { transform: translateX(10px); }
        }
        .error-body {
            padding: 40px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="error-card">
        <div class="error-header">
            <i class="bi bi-exclamation-triangle-fill error-icon"></i>
            <h1>Payment Error</h1>
            <p class="mb-0">An error occurred while processing your payment</p>
        </div>

        <div class="error-body">
            <div class="alert alert-danger">
                <strong>{{ $message }}</strong>
            </div>

            @if(isset($error) && config('app.debug'))
            <div class="alert alert-secondary">
                <small><strong>Debug Info:</strong> {{ $error }}</small>
            </div>
            @endif

            <p class="text-muted mt-4">Please try again or contact our support team if the problem persists.</p>

            <div class="mt-4">
                <p class="text-muted">Return to the app to try again</p>
            </div>
        </div>
    </div>
</body>
</html>
