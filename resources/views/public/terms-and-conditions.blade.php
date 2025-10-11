<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}" dir="{{ app()->getLocale() == 'ar' ? 'rtl' : 'ltr' }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ app()->getLocale() == 'ar' ? $terms->title_ar : $terms->title }} - مهرجان صحار Sohar Festival</title>

    <!-- Bootstrap CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css" rel="stylesheet">

    <!-- Google Fonts -->
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">

    <style>
        body {
            font-family: {{ app()->getLocale() == 'ar' ? "'Cairo', sans-serif" : "system-ui, -apple-system, sans-serif" }};
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px 0;
        }

        .content-wrapper {
            max-width: 900px;
            margin: 0 auto;
        }

        .header-card {
            background: white;
            border-radius: 15px;
            padding: 30px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            text-align: center;
        }

        .logo {
            max-width: 150px;
            margin-bottom: 15px;
        }

        .app-title {
            font-size: 1.8rem;
            font-weight: 700;
            color: #333;
            margin-bottom: 5px;
        }

        .app-subtitle {
            color: #666;
            font-size: 1rem;
            margin-bottom: 0;
        }

        .content-card {
            background: white;
            border-radius: 15px;
            padding: 40px;
            box-shadow: 0 10px 40px rgba(0,0,0,0.1);
        }

        .content-card h2 {
            color: #667eea;
            font-weight: 700;
            margin-bottom: 20px;
        }

        .content-card h3 {
            color: #764ba2;
            font-weight: 600;
            margin-top: 25px;
            margin-bottom: 15px;
        }

        .content-card h4 {
            color: #555;
            font-weight: 600;
            margin-top: 20px;
            margin-bottom: 10px;
        }

        .content-card p {
            color: #333;
            line-height: 1.8;
            margin-bottom: 15px;
        }

        .content-card ul {
            line-height: 1.8;
            color: #333;
        }

        .footer-links {
            text-align: center;
            margin-top: 20px;
        }

        .footer-links a {
            color: white;
            text-decoration: none;
            margin: 0 15px;
            font-weight: 600;
            transition: opacity 0.3s;
        }

        .footer-links a:hover {
            opacity: 0.8;
        }

        .language-switcher {
            position: fixed;
            top: 20px;
            {{ app()->getLocale() == 'ar' ? 'left' : 'right' }}: 20px;
            z-index: 1000;
        }

        .language-switcher a {
            background: white;
            color: #667eea;
            padding: 8px 16px;
            border-radius: 25px;
            text-decoration: none;
            font-weight: 600;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
            transition: all 0.3s;
        }

        .language-switcher a:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0,0,0,0.15);
        }
    </style>
</head>
<body>
    <div class="language-switcher">
        <a href="?lang={{ app()->getLocale() == 'ar' ? 'en' : 'ar' }}">
            <i class="bi bi-translate"></i>
            {{ app()->getLocale() == 'ar' ? 'English' : 'العربية' }}
        </a>
    </div>

    <div class="content-wrapper">
        <div class="header-card">
            <img src="{{ asset('images/logo.png') }}" alt="Sohar Festival Logo" class="logo" onerror="this.style.display='none'">
            <h1 class="app-title">
                {{ app()->getLocale() == 'ar' ? 'مهرجان صحار' : 'Sohar Festival' }}
            </h1>
            <p class="app-subtitle">
                {{ app()->getLocale() == 'ar' ? 'تحت إدارة مكتب محافظ شمال الباطنة' : 'Managed by North Batinah Governor Office' }}
            </p>
        </div>

        <div class="content-card">
            {!! app()->getLocale() == 'ar' ? $terms->content_ar : $terms->content !!}
        </div>

        <div class="footer-links">
            <a href="{{ route('public.privacy-policy', ['lang' => app()->getLocale()]) }}">
                <i class="bi bi-shield-check"></i>
                {{ app()->getLocale() == 'ar' ? 'سياسة الخصوصية' : 'Privacy Policy' }}
            </a>
        </div>
    </div>

    <!-- Bootstrap JS -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

    <script>
        // Handle language switching
        const urlParams = new URLSearchParams(window.location.search);
        const lang = urlParams.get('lang');
        if (lang) {
            document.documentElement.lang = lang;
            document.documentElement.dir = lang === 'ar' ? 'rtl' : 'ltr';
        }
    </script>
</body>
</html>
