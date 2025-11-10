<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Redirecting to Payment Gateway...</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        .loader-container {
            text-align: center;
            background: white;
            padding: 3rem;
            border-radius: 1rem;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
        }
        .loader {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 1.5rem;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        h2 {
            color: #333;
            margin: 0 0 0.5rem;
            font-size: 1.5rem;
        }
        p {
            color: #666;
            margin: 0;
            font-size: 0.95rem;
        }
    </style>
</head>
<body>
    <div class="loader-container">
        <div class="loader"></div>
        <h2>Redirecting to Payment Gateway</h2>
        <p>Please wait while we redirect you to complete your payment...</p>
    </div>

    <form method="post" name="redirect" action="{{ $gatewayUrl }}" id="payment-form">
        <input type="hidden" name="encRequest" value="{{ $encRequest }}">
        <input type="hidden" name="access_code" value="{{ $accessCode }}">
    </form>

    <script>
        // Auto-submit form after 1 second
        setTimeout(function() {
            document.getElementById('payment-form').submit();
        }, 1000);
    </script>
</body>
</html>
