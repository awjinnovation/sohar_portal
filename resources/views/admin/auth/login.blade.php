<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تسجيل دخول الإدارة - مهرجان صحار</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@200;300;400;500;600;700;800;900&family=Tajawal:wght@200;300;400;500;700;800;900&display=swap" rel="stylesheet">
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.rtl.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4A90E2;
            --primary-light: #6BA5E8;
            --primary-dark: #3A7BC8;
            --secondary-color: #FFA726;
            --text-primary: #2C3E50;
            --text-secondary: #7F8C8D;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Cairo', 'Tajawal', system-ui, -apple-system, sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            position: relative;
            overflow: hidden;
            background: #F0F4F8;
        }

        body::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('{{ asset('images/file.jpg') }}') center/cover no-repeat;
            opacity: 0.15;
            z-index: 0;
        }

        body::after {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.05) 0%, rgba(255, 167, 38, 0.05) 100%);
            z-index: 0;
        }

        .login-wrapper {
            position: relative;
            z-index: 1;
            width: 100%;
            max-width: 1100px;
            padding: 20px;
        }

        .login-container {
            background: rgba(255, 255, 255, 0.98);
            border-radius: 24px;
            box-shadow: 0 20px 60px rgba(0, 0, 0, 0.15);
            overflow: hidden;
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.6);
            display: grid;
            grid-template-columns: 1fr 1fr;
            min-height: 600px;
        }

        .login-image {
            background: url('{{ asset('images/file.jpg') }}') center/cover no-repeat;
            position: relative;
            overflow: hidden;
        }

        .login-image::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(74, 144, 226, 0.7) 0%, rgba(58, 123, 200, 0.8) 100%);
        }

        .image-content {
            position: relative;
            z-index: 1;
            padding: 60px 50px;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            color: white;
        }

        .festival-logo {
            width: 180px;
            height: auto;
            margin-bottom: 30px;
            filter: brightness(0) invert(1);
        }

        .festival-title {
            font-size: 42px;
            font-weight: 800;
            margin-bottom: 16px;
            text-shadow: 0 2px 10px rgba(0,0,0,0.2);
            letter-spacing: -0.02em;
        }

        .festival-subtitle {
            font-size: 20px;
            font-weight: 500;
            opacity: 0.95;
            line-height: 1.6;
            margin-bottom: 30px;
        }

        .festival-features {
            list-style: none;
            padding: 0;
            margin-top: 30px;
        }

        .festival-features li {
            font-size: 16px;
            margin-bottom: 16px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .festival-features li i {
            font-size: 22px;
            color: var(--secondary-color);
        }

        .login-form-section {
            padding: 60px 50px;
            background: white;
            display: flex;
            flex-direction: column;
            justify-content: center;
        }

        .form-header {
            margin-bottom: 40px;
        }

        .welcome-text {
            color: var(--text-primary);
            font-size: 32px;
            font-weight: 800;
            margin-bottom: 12px;
            letter-spacing: -0.02em;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 16px;
            font-weight: 500;
            line-height: 1.6;
        }

        .form-label {
            color: var(--text-primary);
            font-weight: 700;
            margin-bottom: 10px;
            font-size: 15px;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-label i {
            color: var(--primary-color);
            font-size: 18px;
        }

        .form-control {
            border: 2px solid #E0E6ED;
            border-radius: 12px;
            padding: 14px 18px;
            font-size: 15px;
            font-weight: 500;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            background: #F8F9FA;
        }

        .form-control:focus {
            border-color: var(--primary-color);
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
            background: white;
            outline: none;
        }

        .form-control::placeholder {
            color: #9CA3AF;
        }

        .btn-login {
            background: linear-gradient(135deg, var(--primary-color) 0%, var(--primary-light) 100%);
            color: white;
            border: none;
            border-radius: 12px;
            padding: 16px 24px;
            font-size: 17px;
            font-weight: 700;
            width: 100%;
            margin-top: 24px;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 4px 16px rgba(74, 144, 226, 0.3);
            position: relative;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .btn-login::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: linear-gradient(135deg, rgba(255,255,255,0.15) 0%, rgba(255,255,255,0) 100%);
            opacity: 0;
            transition: opacity 0.3s ease;
        }

        .btn-login:hover {
            transform: translateY(-2px);
            box-shadow: 0 8px 24px rgba(74, 144, 226, 0.4);
        }

        .btn-login:hover::before {
            opacity: 1;
        }

        .btn-login:active {
            transform: translateY(0);
        }

        .remember-forgot {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 20px;
        }

        .form-check {
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .form-check-input {
            width: 20px;
            height: 20px;
            border: 2px solid #E0E6ED;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .form-check-input:checked {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
        }

        .form-check-input:focus {
            box-shadow: 0 0 0 4px rgba(74, 144, 226, 0.1);
        }

        .form-check-label {
            color: var(--text-secondary);
            font-size: 15px;
            font-weight: 600;
            cursor: pointer;
        }

        .forgot-link {
            color: var(--primary-color);
            text-decoration: none;
            font-size: 15px;
            font-weight: 700;
            transition: all 0.3s ease;
        }

        .forgot-link:hover {
            color: var(--secondary-color);
        }

        .alert {
            border-radius: 12px;
            font-size: 15px;
            font-weight: 600;
            border: none;
            padding: 16px 20px;
            margin-bottom: 24px;
            display: flex;
            align-items: center;
            gap: 12px;
        }

        .alert-danger {
            background: linear-gradient(135deg, #FEE2E2 0%, #FECACA 100%);
            color: #991B1B;
        }

        .alert i {
            font-size: 22px;
        }

        .login-footer {
            margin-top: 40px;
            padding-top: 30px;
            border-top: 2px solid #E0E6ED;
            text-align: center;
        }

        .footer-text {
            color: var(--text-secondary);
            font-size: 14px;
            font-weight: 600;
        }

        .footer-text strong {
            color: var(--primary-color);
            font-weight: 800;
        }

        @media (max-width: 992px) {
            .login-container {
                grid-template-columns: 1fr;
            }

            .login-image {
                display: none;
            }

            .login-form-section {
                padding: 50px 40px;
            }
        }

        @media (max-width: 576px) {
            .login-wrapper {
                padding: 10px;
            }

            .login-form-section {
                padding: 40px 30px;
            }

            .welcome-text {
                font-size: 28px;
            }

            .subtitle {
                font-size: 14px;
            }
        }
    </style>
</head>
<body>
    <div class="login-wrapper">
        <div class="login-container">
            <!-- Left Side - Image & Branding -->
            <div class="login-image">
                <div class="image-content">
                    <img src="{{ asset('sohar_fastival_logo_no_bg.png') }}" alt="Sohar Festival" class="festival-logo">
                    <h1 class="festival-title">مهرجان صحار</h1>
                    <p class="festival-subtitle">نظام إدارة المهرجان المتكامل</p>

                    <ul class="festival-features">
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>إدارة الفعاليات والمناسبات</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>متابعة الحجوزات والتذاكر</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>إدارة المطاعم والخدمات</span>
                        </li>
                        <li>
                            <i class="bi bi-check-circle-fill"></i>
                            <span>تحليلات وتقارير شاملة</span>
                        </li>
                    </ul>
                </div>
            </div>

            <!-- Right Side - Login Form -->
            <div class="login-form-section">
                <div class="form-header">
                    <h2 class="welcome-text">مرحباً بعودتك</h2>
                    <p class="subtitle">أدخل بيانات الاعتماد الخاصة بك للوصول إلى لوحة التحكم</p>
                </div>

                @if($errors->any())
                    <div class="alert alert-danger">
                        <i class="bi bi-exclamation-triangle-fill"></i>
                        <div>
                            @foreach($errors->all() as $error)
                                <div>{{ $error }}</div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <form method="POST" action="{{ route('admin.login') }}">
                    @csrf

                    <div class="mb-4">
                        <label for="email" class="form-label">
                            <i class="bi bi-envelope-fill"></i>
                            البريد الإلكتروني
                        </label>
                        <input type="email" class="form-control @error('email') is-invalid @enderror"
                               id="email" name="email" value="{{ old('email') }}"
                               placeholder="admin@soharfestival.om" dir="ltr" required autofocus>
                    </div>

                    <div class="mb-4">
                        <label for="password" class="form-label">
                            <i class="bi bi-lock-fill"></i>
                            كلمة المرور
                        </label>
                        <input type="password" class="form-control @error('password') is-invalid @enderror"
                               id="password" name="password" placeholder="••••••••" dir="ltr" required>
                    </div>

                    <div class="remember-forgot">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="remember" id="remember">
                            <label class="form-check-label" for="remember">
                                تذكرني
                            </label>
                        </div>
                        <a href="#" class="forgot-link">نسيت كلمة المرور؟</a>
                    </div>

                    <button type="submit" class="btn btn-login">
                        <i class="bi bi-box-arrow-in-right"></i>
                        <span>تسجيل الدخول</span>
                    </button>
                </form>

                <div class="login-footer">
                    <p class="footer-text">
                        © 2025 <strong>مهرجان صحار</strong> - جميع الحقوق محفوظة
                    </p>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
