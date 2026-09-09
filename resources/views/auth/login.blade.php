@php
    /*
    |--------------------------------------------------------------------------
    | الهوية الديناميكية لصفحة تسجيل الدخول
    |--------------------------------------------------------------------------
    */

    $brandName = \App\Models\SystemSetting::get(
        'system_name',
        'حلويات دهب'
    );

    $brandNameEn = \App\Models\SystemSetting::get(
        'system_name_en',
        'Dahab Sweets'
    );

    $brandLogoPath = \App\Models\SystemSetting::get(
        'brand_logo'
    );

    $brandReportLogoPath = \App\Models\SystemSetting::get(
        'brand_report_logo'
    );

    $brandFaviconPath = \App\Models\SystemSetting::get(
        'brand_favicon'
    );

    $loginBackgroundPath = \App\Models\SystemSetting::get(
        'brand_login_background'
    );

    /*
    |--------------------------------------------------------------------------
    | الثيم
    |--------------------------------------------------------------------------
    */

    $themePrimary = \App\Models\SystemSetting::get(
        'theme_primary',
        '#0A2948'
    );

    $themeAccent = \App\Models\SystemSetting::get(
        'theme_accent',
        '#C98516'
    );

    $themeDanger = \App\Models\SystemSetting::get(
        'theme_danger',
        '#E22929'
    );

    $themeFont = \App\Models\SystemSetting::get(
        'theme_font_family',
        'Cairo'
    );

    /*
    |--------------------------------------------------------------------------
    | روابط الملفات
    |--------------------------------------------------------------------------
    */

    $brandLogoUrl = $brandLogoPath
        ? asset($brandLogoPath)
        : (
            $brandReportLogoPath
                ? asset($brandReportLogoPath)
                : (
                    file_exists(public_path('images/pdf-assets/logo.png'))
                        ? asset('images/pdf-assets/logo.png')
                        : (
                            file_exists(public_path('assets/images/logo.png'))
                                ? asset('assets/images/logo.png')
                                : null
                        )
                )
        );

    $brandFaviconUrl = $brandFaviconPath
        ? asset($brandFaviconPath)
        : null;

    $loginBackgroundUrl = $loginBackgroundPath
        ? asset($loginBackgroundPath)
        : (
            file_exists(public_path('images/hero-bg-cake.png'))
                ? asset('images/hero-bg-cake.png')
                : null
        );

    /*
    |--------------------------------------------------------------------------
    | تحويل HEX إلى RGB لاستخدام اللون مع الشفافية
    |--------------------------------------------------------------------------
    */

    $hexToRgb = static function (string $hex): string {
        $hex = ltrim($hex, '#');

        if (strlen($hex) === 3) {
            $hex =
                $hex[0] . $hex[0] .
                $hex[1] . $hex[1] .
                $hex[2] . $hex[2];
        }

        if (strlen($hex) !== 6) {
            return '201,133,22';
        }

        return
            hexdec(substr($hex, 0, 2)) . ',' .
            hexdec(substr($hex, 2, 2)) . ',' .
            hexdec(substr($hex, 4, 2));
    };

    $accentRgb = $hexToRgb($themeAccent);
    $primaryRgb = $hexToRgb($themePrimary);
@endphp

<!DOCTYPE html>
<html lang="ar" dir="rtl">

<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <meta
        name="theme-color"
        content="{{ $themePrimary }}"
    >

    <title>
        تسجيل الدخول — {{ $brandName }}
    </title>

    @if($brandFaviconUrl)
        <link
            rel="icon"
            href="{{ $brandFaviconUrl }}"
        >
        <link
            rel="shortcut icon"
            href="{{ $brandFaviconUrl }}"
        >
    @endif

    <link
        rel="preconnect"
        href="https://fonts.googleapis.com"
    >

    <link
        rel="preconnect"
        href="https://fonts.gstatic.com"
        crossorigin
    >

    <link
        href="https://fonts.googleapis.com/css2?family=Amiri:wght@700&family=Cairo:wght@400;500;600;700;800&display=swap"
        rel="stylesheet"
    >

    <style>
        :root {
            --primary: {{ $themePrimary }};
            --primary-rgb: {{ $primaryRgb }};

            --accent: {{ $themeAccent }};
            --accent-rgb: {{ $accentRgb }};

            --danger: {{ $themeDanger }};

            --bg: #090704;
            --card: rgba(17, 13, 7, .86);
            --text: #fffaf0;
            --muted: rgba(255, 250, 240, .58);
            --border: rgba(var(--accent-rgb), .20);

            --login-font:
                '{{ $themeFont }}',
                'Cairo',
                sans-serif;
        }

        *,
        *::before,
        *::after {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        html,
        body {
            min-height: 100%;
        }

        body {
            min-height: 100vh;
            min-height: 100dvh;

            font-family: var(--login-font);

            color: var(--text);

            background:
                var(--bg);

            overflow-x: hidden;

            -webkit-font-smoothing:
                antialiased;
        }

        button,
        input {
            font: inherit;
        }


        /*
        |--------------------------------------------------------------------------
        | الخلفية
        |--------------------------------------------------------------------------
        */

        .auth-page {
            position: relative;

            isolation: isolate;

            display: grid;

            place-items: center;

            min-height: 100vh;
            min-height: 100dvh;

            padding:
                clamp(20px, 4vw, 56px);

            overflow: hidden;
        }

        .auth-page::before {
            content: "";

            position: absolute;

            inset: -4%;

            z-index: -3;

            @if($loginBackgroundUrl)

                background-image:
                    linear-gradient(
                        115deg,
                        rgba(4, 3, 1, .88),
                        rgba(8, 6, 2, .50) 48%,
                        rgba(4, 3, 1, .84)
                    ),
                    url('{{ $loginBackgroundUrl }}');

                background-position:
                    center,
                    center;

                background-size:
                    cover,
                    cover;

                background-repeat:
                    no-repeat,
                    no-repeat;

            @else

                background:
                    linear-gradient(
                        135deg,
                        var(--primary),
                        #080808
                    );

            @endif

            animation:
                backgroundMove
                20s
                ease-in-out
                infinite
                alternate;
        }

        .auth-page::after {
            content: "";

            position: absolute;

            inset: 0;

            z-index: -2;

            background:
                radial-gradient(
                    circle at 18% 20%,
                    rgba(var(--accent-rgb), .18),
                    transparent 28%
                ),
                radial-gradient(
                    circle at 82% 78%,
                    rgba(var(--accent-rgb), .12),
                    transparent 30%
                ),
                linear-gradient(
                    to bottom,
                    rgba(0, 0, 0, .08),
                    rgba(0, 0, 0, .55)
                );
        }

        @keyframes backgroundMove {
            from {
                transform:
                    scale(1.04);
            }

            to {
                transform:
                    scale(1.10)
                    translate3d(-8px, -5px, 0);
            }
        }


        /*
        |--------------------------------------------------------------------------
        | الخط العلوي
        |--------------------------------------------------------------------------
        */

        .top-line {
            position: fixed;

            inset:
                0 0 auto;

            height: 3px;

            background:
                linear-gradient(
                    90deg,
                    transparent,
                    var(--accent),
                    #FFFFFF,
                    var(--accent),
                    transparent
                );

            background-size:
                220% 100%;

            animation:
                shimmer
                4s
                linear
                infinite;
        }

        @keyframes shimmer {
            from {
                background-position:
                    220% 0;
            }

            to {
                background-position:
                    -220% 0;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | الكارد
        |--------------------------------------------------------------------------
        */

        .login-card {
            position: relative;

            width:
                min(100%, 460px);

            padding:
                clamp(28px, 4vw, 44px);

            background:
                var(--card);

            border:
                1px solid
                var(--border);

            border-radius:
                26px;

            box-shadow:
                0 28px 80px rgba(0, 0, 0, .55),
                inset 0 1px rgba(255, 255, 255, .05);

            backdrop-filter:
                blur(24px);

            -webkit-backdrop-filter:
                blur(24px);

            animation:
                cardIn
                .75s
                cubic-bezier(.22, 1, .36, 1)
                both;
        }

        .login-card::before {
            content: "";

            position: absolute;

            inset: 0;

            border-radius:
                inherit;

            pointer-events: none;

            background:
                linear-gradient(
                    145deg,
                    rgba(var(--accent-rgb), .08),
                    transparent 34%
                );
        }

        @keyframes cardIn {
            from {
                opacity: 0;

                transform:
                    translateY(24px)
                    scale(.98);
            }

            to {
                opacity: 1;

                transform:
                    none;
            }
        }


        /*
        |--------------------------------------------------------------------------
        | الشعار
        |--------------------------------------------------------------------------
        */

        .brand {
            position: relative;

            text-align: center;

            margin-bottom:
                30px;
        }

        .brand-logo {
            display: block;

            width:
                min(250px, 78%);

            height:
                150px;

            margin:
                0 auto 10px;

            object-fit:
                contain;

            object-position:
                center;

            filter:
                drop-shadow(
                    0 9px 20px
                    rgba(0, 0, 0, .30)
                );
        }

        .brand-fallback {
            margin-bottom:
                10px;

            color:
                var(--accent);

            font-size:
                2rem;

            font-weight:
                800;
        }

        .brand-name {
            display: block;

            font-size:
                1.3rem;

            font-weight:
                800;
        }

        .brand-en {
            display: block;

            margin-top:
                3px;

            color:
                rgba(var(--accent-rgb), .80);

            font-size:
                .7rem;

            font-weight:
                600;

            letter-spacing:
                .14em;
        }


        /*
        |--------------------------------------------------------------------------
        | النصوص
        |--------------------------------------------------------------------------
        */

        .heading {
            margin-bottom:
                5px;

            text-align:
                center;

            font-size:
                1.28rem;

            font-weight:
                700;
        }

        .subtitle {
            margin-bottom:
                25px;

            text-align:
                center;

            color:
                var(--muted);

            font-size:
                .84rem;

            line-height:
                1.8;
        }


        /*
        |--------------------------------------------------------------------------
        | الأخطاء
        |--------------------------------------------------------------------------
        */

        .alert-error {
            margin-bottom:
                18px;

            padding:
                11px 13px;

            color:
                #FFD1D1;

            background:
                rgba(185, 28, 28, .16);

            border:
                1px solid
                rgba(248, 113, 113, .30);

            border-radius:
                11px;

            font-size:
                .8rem;

            line-height:
                1.7;
        }


        /*
        |--------------------------------------------------------------------------
        | الحقول
        |--------------------------------------------------------------------------
        */

        .field {
            margin-bottom:
                17px;
        }

        .field-label {
            display: block;

            margin:
                0 3px 7px;

            color:
                rgba(255, 250, 240, .72);

            font-size:
                .8rem;

            font-weight:
                600;
        }

        .field-wrap {
            position: relative;
        }

        .field-icon {
            position: absolute;

            top: 50%;
            right: 15px;

            display: flex;

            color:
                rgba(var(--accent-rgb), .72);

            transform:
                translateY(-50%);

            pointer-events:
                none;
        }

        .field-input {
            width: 100%;

            height: 51px;

            padding:
                0 45px;

            color:
                var(--text);

            caret-color:
                var(--accent);

            background:
                rgba(255, 255, 255, .055);

            border:
                1px solid
                rgba(var(--accent-rgb), .20);

            border-radius:
                12px;

            outline:
                none;

            font-size:
                .88rem;

            transition:
                .22s ease;
        }

        .field-input::placeholder {
            color:
                rgba(255, 255, 255, .25);
        }

        .field-input:hover {
            border-color:
                rgba(var(--accent-rgb), .40);
        }

        .field-input:focus {
            background:
                rgba(255, 255, 255, .08);

            border-color:
                rgba(var(--accent-rgb), .78);

            box-shadow:
                0 0 0 4px
                rgba(var(--accent-rgb), .10);
        }

        .field-input.error-field {
            border-color:
                rgba(248, 113, 113, .62);
        }

        .password-toggle {
            position: absolute;

            top: 50%;
            left: 12px;

            display: flex;

            padding: 5px;

            color:
                rgba(255, 255, 255, .38);

            background:
                none;

            border: 0;

            cursor: pointer;

            transform:
                translateY(-50%);

            transition:
                color .2s;
        }

        .password-toggle:hover,
        .password-toggle:focus-visible {
            color:
                var(--accent);
        }


        /*
        |--------------------------------------------------------------------------
        | تذكرني
        |--------------------------------------------------------------------------
        */

        .remember-row {
            display: flex;

            align-items: center;

            justify-content:
                space-between;

            margin:
                3px 2px 24px;
        }

        .check-label {
            display:
                inline-flex;

            align-items:
                center;

            gap:
                8px;

            color:
                var(--muted);

            font-size:
                .8rem;

            cursor:
                pointer;

            user-select:
                none;
        }

        .check-label input {
            width: 16px;
            height: 16px;

            accent-color:
                var(--accent);

            cursor:
                pointer;
        }


        /*
        |--------------------------------------------------------------------------
        | زر الدخول
        |--------------------------------------------------------------------------
        */

        .login-button {
            display: flex;

            align-items: center;

            justify-content:
                center;

            gap:
                10px;

            width:
                100%;

            height:
                52px;

            color:
                #FFFFFF;

            background:
                linear-gradient(
                    135deg,
                    var(--accent),
                    var(--primary)
                );

            border:
                0;

            border-radius:
                12px;

            box-shadow:
                0 9px 25px
                rgba(var(--accent-rgb), .20);

            font-size:
                .94rem;

            font-weight:
                800;

            cursor:
                pointer;

            overflow:
                hidden;

            transition:
                transform .18s,
                box-shadow .18s;
        }

        .login-button:hover {
            transform:
                translateY(-2px);

            box-shadow:
                0 13px 32px
                rgba(var(--accent-rgb), .30);
        }

        .login-button:active {
            transform:
                translateY(0);
        }

        .login-button svg {
            transition:
                transform .2s;
        }

        .login-button:hover svg {
            transform:
                translateX(-4px);
        }


        /*
        |--------------------------------------------------------------------------
        | Footer
        |--------------------------------------------------------------------------
        */

        .footer {
            margin-top:
                27px;

            text-align:
                center;

            color:
                rgba(255, 255, 255, .32);

            font-size:
                .7rem;
        }

        .footer strong {
            color:
                rgba(var(--accent-rgb), .78);
        }


        /*
        |--------------------------------------------------------------------------
        | Responsive
        |--------------------------------------------------------------------------
        */

        @media (max-width: 560px) {

            .auth-page {
                place-items:
                    center;

                padding:
                    16px;
            }

            .auth-page::before {
                inset:
                    -8%;
            }

            .login-card {
                padding:
                    28px 21px;

                border-radius:
                    21px;
            }

            .brand {
                margin-bottom:
                    24px;
            }

            .brand-logo {
                width:
                    min(210px, 72%);

                height:
                    120px;
            }

            .subtitle {
                margin-bottom:
                    22px;
            }
        }

        @media (
            max-height: 720px
        ) and (
            min-width: 561px
        ) {

            .auth-page {
                padding-block:
                    18px;
            }

            .login-card {
                padding-block:
                    25px;
            }

            .brand {
                margin-bottom:
                    19px;
            }

            .brand-logo {
                width:
                    180px;

                height:
                    80px;

                margin-bottom:
                    10px;
            }

            .subtitle {
                margin-bottom:
                    18px;
            }

            .footer {
                margin-top:
                    20px;
            }
        }

        @media (
            prefers-reduced-motion: reduce
        ) {

            *,
            *::before,
            *::after {
                animation-duration:
                    .01ms !important;

                animation-iteration-count:
                    1 !important;

                scroll-behavior:
                    auto !important;
            }
        }
    </style>
</head>

<body>

    <main class="auth-page">

        <div
            class="top-line"
            aria-hidden="true"
        ></div>


        <section
            class="login-card"
            aria-labelledby="login-title"
        >

            <header class="brand">

                @if($brandLogoUrl)

                    <img
                        class="brand-logo"
                        src="{{ $brandLogoUrl }}"
                        alt="{{ $brandName }}"
                    >

                @else

                    <div class="brand-fallback">
                        {{ $brandName }}
                    </div>

                @endif

                <span class="brand-name">
                    {{ $brandName }}
                </span>

                @if($brandNameEn)
                    <span class="brand-en">
                        {{ $brandNameEn }}
                    </span>
                @endif

            </header>


            <h1
                class="heading"
                id="login-title"
            >
                مرحبًا بعودتك
            </h1>


            <p class="subtitle">
                أدخل بياناتك للوصول إلى لوحة التحكم
            </p>


            @if($errors->any())

                <div
                    class="alert-error"
                    role="alert"
                >

                    @foreach($errors->all() as $error)

                        <div>
                            ⚠ {{ $error }}
                        </div>

                    @endforeach

                </div>

            @endif


            @if(session('error'))

                <div
                    class="alert-error"
                    role="alert"
                >
                    ⚠ {{ session('error') }}
                </div>

            @endif


            <form
                method="POST"
                action="{{ route('login') }}"
            >

                @csrf


                <div class="field">

                    <label
                        class="field-label"
                        for="login"
                    >
                        اسم المستخدم أو البريد الإلكتروني
                    </label>


                    <div class="field-wrap">

                        <span
                            class="field-icon"
                            aria-hidden="true"
                        >
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <path
                                    d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"
                                />

                                <circle
                                    cx="12"
                                    cy="7"
                                    r="4"
                                />
                            </svg>
                        </span>


                        <input
                            class="field-input {{ $errors->has('login') ? 'error-field' : '' }}"
                            id="login"
                            name="login"
                            type="text"
                            value="{{ old('login') }}"
                            placeholder="اسم المستخدم أو البريد الإلكتروني"
                            autocomplete="username"
                            required
                            autofocus
                        >

                    </div>
                </div>


                <div class="field">

                    <label
                        class="field-label"
                        for="password"
                    >
                        كلمة المرور
                    </label>


                    <div class="field-wrap">

                        <span
                            class="field-icon"
                            aria-hidden="true"
                        >
                            <svg
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                            >
                                <rect
                                    x="3"
                                    y="11"
                                    width="18"
                                    height="10"
                                    rx="2"
                                />

                                <path
                                    d="M7 11V7a5 5 0 0 1 10 0v4"
                                />
                            </svg>
                        </span>


                        <input
                            class="field-input {{ $errors->has('password') ? 'error-field' : '' }}"
                            id="password"
                            name="password"
                            type="password"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >


                        <button
                            class="password-toggle"
                            id="passwordToggle"
                            type="button"
                            aria-label="إظهار كلمة المرور"
                            aria-pressed="false"
                        >
                            <svg
                                id="eyeIcon"
                                width="18"
                                height="18"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                stroke-width="2"
                                aria-hidden="true"
                            >
                                <path
                                    d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"
                                />

                                <circle
                                    cx="12"
                                    cy="12"
                                    r="3"
                                />
                            </svg>
                        </button>

                    </div>
                </div>


                <div class="remember-row">

                    <label class="check-label">

                        <input
                            type="checkbox"
                            name="remember"
                            {{ old('remember') ? 'checked' : '' }}
                        >

                        <span>
                            تذكرني
                        </span>

                    </label>

                </div>


                <button
                    class="login-button"
                    type="submit"
                >

                    <span>
                        تسجيل الدخول
                    </span>

                    <svg
                        width="19"
                        height="19"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        stroke-width="2.2"
                        aria-hidden="true"
                    >
                        <path
                            d="M19 12H5M12 19l-7-7 7-7"
                        />
                    </svg>

                </button>

            </form>


            <footer class="footer">

                <strong>
                    {{ $brandName }}
                </strong>

                &nbsp;©&nbsp;

                {{ date('Y') }}

                — جميع الحقوق محفوظة

            </footer>

        </section>

    </main>


    <script>
        const passwordInput =
            document.getElementById('password');

        const passwordToggle =
            document.getElementById('passwordToggle');

        const eyeIcon =
            document.getElementById('eyeIcon');


        passwordToggle.addEventListener(
            'click',
            () => {

                const showPassword =
                    passwordInput.type ===
                    'password';

                passwordInput.type =
                    showPassword
                        ? 'text'
                        : 'password';

                passwordToggle.setAttribute(
                    'aria-pressed',
                    String(showPassword)
                );

                passwordToggle.setAttribute(
                    'aria-label',
                    showPassword
                        ? 'إخفاء كلمة المرور'
                        : 'إظهار كلمة المرور'
                );

                eyeIcon.innerHTML =
                    showPassword

                        ? '<path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"/><line x1="1" y1="1" x2="23" y2="23"/>'

                        : '<path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"/><circle cx="12" cy="12" r="3"/>';
            }
        );
    </script>

</body>
</html>