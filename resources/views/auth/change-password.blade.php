<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>تغيير كلمة المرور — حلويات دهب</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@300;400;500;600;700;800&family=Amiri:wght@400;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="{{ asset('assets/css/app.css') }}">
</head>
<body class="auth-body">

<div class="auth-wrap">
    <div class="auth-card">

        <!-- Brand -->
        <div class="auth-brand">
            <div class="auth-logo-wrap">
                @if(file_exists(public_path('assets/images/logo.png')))
                    <img src="{{ asset('assets/images/logo.png') }}" alt="حلويات دهب" class="auth-logo">
                @else
                    <div class="auth-logo-text"><span class="logo-ar">د</span></div>
                @endif
            </div>
            <h1 class="auth-title">تغيير كلمة المرور</h1>
            @if(auth()->user()?->must_change_password)
                <p class="auth-subtitle" style="color:var(--gold);font-weight:700;">مطلوب قبل المتابعة</p>
            @else
                <p class="auth-subtitle">DAHAB SWEETS</p>
            @endif
        </div>

        @if($errors->any())
            <div class="alert alert-danger" style="margin-bottom:1rem">
                @foreach($errors->all() as $error)<p>{{ $error }}</p>@endforeach
            </div>
        @endif

        @if(session('warning'))
            <div class="alert alert-warning" style="margin-bottom:1rem">{{ session('warning') }}</div>
        @endif

        @if(session('success'))
            <div class="alert alert-success" style="margin-bottom:1rem">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('auth.change-password.post') }}" class="auth-form">
            @csrf

            @if(!auth()->user()?->must_change_password)
            <div class="form-group">
                <label class="form-label">كلمة المرور الحالية</label>
                <div class="input-with-toggle">
                    <input type="password" name="current_password"
                        class="form-input {{ $errors->has('current_password') ? 'is-invalid' : '' }}"
                        placeholder="••••••••••" autocomplete="current-password">
                    <button type="button" class="input-toggle-btn" onclick="togglePwd(this)" tabindex="-1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                @error('current_password')<span class="form-error">{{ $message }}</span>@enderror
            </div>
            @endif

            <div class="form-group">
                <label class="form-label">كلمة المرور الجديدة</label>
                <div class="input-with-toggle">
                    <input type="password" name="password"
                        class="form-input {{ $errors->has('password') ? 'is-invalid' : '' }}"
                        placeholder="••••••••••" autocomplete="new-password">
                    <button type="button" class="input-toggle-btn" onclick="togglePwd(this)" tabindex="-1">
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                            <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/>
                            <circle cx="12" cy="12" r="3"/>
                        </svg>
                    </button>
                </div>
                <div class="password-rules">10 أحرف على الأقل · حرف كبير وصغير · رقم · رمز خاص</div>
                @error('password')<span class="form-error">{{ $message }}</span>@enderror
            </div>

            <div class="form-group">
                <label class="form-label">تأكيد كلمة المرور</label>
                <input type="password" name="password_confirmation"
                    class="form-input" placeholder="••••••••••" autocomplete="new-password">
            </div>

            <button type="submit" class="btn btn-gold btn-full btn-lg">
                <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <polyline points="20 6 9 17 4 12"/>
                </svg>
                حفظ كلمة المرور الجديدة
            </button>
        </form>

        <div class="auth-footer">
            حلويات دهب &copy; {{ date('Y') }}
        </div>
    </div>
</div>

<script>
function togglePwd(btn) {
    const input = btn.closest('.input-with-toggle').querySelector('input');
    input.type = input.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>
