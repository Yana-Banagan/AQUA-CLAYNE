<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, viewport-fit=cover">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta http-equiv="Content-Security-Policy" content="upgrade-insecure-requests">
    <meta name="description" content="AQUA CLAYNE Purified Water - Unified Portal Login for Customers, Staff, and Administrators.">

    <title>AQUA CLAYNE | Unified Portal Login</title>

    <!-- Browser Favicon & Touch Icons (Maximized Artwork Display) -->
    <link rel="icon" type="image/png" sizes="32x32" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="icon" type="image/png" sizes="16x16" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">
    <link rel="shortcut icon" type="image/x-icon" href="{{ asset('favicon.ico') }}?v={{ time() }}">
    <link rel="apple-touch-icon" href="{{ asset('images/aqua_clayne_favicon.png') }}?v={{ time() }}">

    <!-- Google Fonts: Inter -->
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <!-- Stylesheet -->
    <link rel="stylesheet" href="{{ asset('css/admin-login.css') }}?v=20261101-1">
</head>
<body>

    <div class="login-page">

        <!-- ===================================================================
             DECORATIVE WATER BACKGROUND ELEMENTS
        =================================================================== -->
        <div class="water-effects" aria-hidden="true">
            <div class="water-bubble bubble-1"></div>
            <div class="water-bubble bubble-2"></div>
            <div class="water-bubble bubble-3"></div>
            <div class="water-bubble bubble-4"></div>
            <div class="water-bubble bubble-5"></div>
            <div class="water-glow glow-1"></div>
            <div class="water-glow glow-2"></div>
        </div>

        <!-- ===================================================================
             MAIN LOGIN CONTAINER
        =================================================================== -->
        <main class="main-login-container">

            <!-- ===============================================================
                 LEFT BRANDING PANEL (Desktop Experience)
            ================================================================ -->
            <aside class="brand-panel">
                <div class="brand-glass-badge">
                    <span class="badge-icon" aria-hidden="true">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                            <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                        </svg>
                    </span>
                    <span>Aqua Clayne Portal</span>
                </div>

                <div class="brand-title-wrap">
                    <h1 class="brand-name">AQUA CLAYNE</h1>
                    <div class="brand-tagline">Purified Drinking Water</div>
                    <div class="brand-headline">Clean Water, Healthier Lives.</div>
                    <p class="brand-description">
                        One unified portal for our valued customers and dedicated team. Order water refills, monitor real-time deliveries, or manage station operations seamlessly.
                    </p>
                </div>

                <div class="brand-features" aria-label="Portal Capabilities">
                    <div class="feature-pill">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M6 2L3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4z"></path>
                            <line x1="3" y1="6" x2="21" y2="6"></line>
                            <path d="M16 10a4 4 0 0 1-8 0"></path>
                        </svg>
                        <span>Customer Ordering</span>
                    </div>

                    <div class="feature-pill">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <rect x="1" y="3" width="15" height="13"></rect>
                            <polygon points="16 8 20 8 23 11 23 16 16 16 16 8"></polygon>
                            <circle cx="5.5" cy="18.5" r="2.5"></circle>
                            <circle cx="18.5" cy="18.5" r="2.5"></circle>
                        </svg>
                        <span>Live GPS Tracking</span>
                    </div>

                    <div class="feature-pill">
                        <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                        </svg>
                        <span>Station Management</span>
                    </div>
                </div>
            </aside>

            <!-- ===============================================================
                 RIGHT LOGIN CARD (Elevated Glassmorphism)
            ================================================================ -->
            <section class="login-card-wrapper" aria-labelledby="loginHeading">
                <div class="login-card">

                    <!-- TOP NAVIGATION BAR WITH VISIBLE BACK BUTTON -->
                    <div class="login-card-top-bar">
                        <a href="{{ route('landing') }}" class="login-back-btn" id="loginBackBtn" title="Back to Aqua Clayne Homepage">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <line x1="19" y1="12" x2="5" y2="12"></line>
                                <polyline points="12 19 5 12 12 5"></polyline>
                            </svg>
                            <span>Back to Home</span>
                        </a>

                        <div class="portal-badge" title="Unified Login for Customers, Staff, and Administrators">
                            <span class="portal-badge-dot" aria-hidden="true"></span>
                            <span>Unified Portal</span>
                        </div>
                    </div>

                    <!-- WELCOME MESSAGE -->
                    <div class="card-welcome-wrap">
                        <div class="mobile-logo-wrap" aria-hidden="true">
                            <div class="mobile-logo-circle">
                                <svg width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="#0878C9" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                    <path d="M12 2.69l5.66 5.66a8 8 0 1 1-11.31 0z"></path>
                                </svg>
                            </div>
                        </div>
                        <h2 id="loginHeading" class="login-title">Welcome Back!</h2>
                        <p class="login-description">
                            Sign in to your Aqua Clayne account. Customers, Staff, and Administrators can sign in below.
                        </p>
                    </div>

                    <!-- INTERACTIVE PASSWORD RESET NOTICE -->
                    <div id="forgotNotice" class="info-notice" role="alert" tabindex="-1">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                            <circle cx="12" cy="12" r="10"></circle>
                            <line x1="12" y1="16" x2="12" y2="12"></line>
                            <line x1="12" y1="8" x2="12.01" y2="8"></line>
                        </svg>
                        <span>To reset your password, please contact the Aqua Clayne station administrator or support team at (049) 501-2345.</span>
                        <button type="button" id="closeNoticeBtn" class="info-notice-close" aria-label="Close message">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                <line x1="18" y1="6" x2="6" y2="18"></line>
                                <line x1="6" y1="6" x2="18" y2="18"></line>
                            </svg>
                        </button>
                    </div>

                    <!-- LARAVEL VALIDATION ERRORS -->
                    @if ($errors->any())
                        <div class="alert alert-error" role="alert">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <ul>
                                @foreach ($errors->all() as $error)
                                    <li>{{ $error }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @endif

                    <!-- FLASH SESSION ERROR -->
                    @if (session('error'))
                        <div class="alert alert-error" role="alert">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <circle cx="12" cy="12" r="10"></circle>
                                <line x1="12" y1="8" x2="12" y2="12"></line>
                                <line x1="12" y1="16" x2="12.01" y2="16"></line>
                            </svg>
                            <span>{{ session('error') }}</span>
                        </div>
                    @endif

                    <!-- FLASH SESSION SUCCESS -->
                    @if (session('success'))
                        <div class="alert alert-success" role="status">
                            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path>
                                <polyline points="22 4 12 14.01 9 11.01"></polyline>
                            </svg>
                            <span>{{ session('success') }}</span>
                        </div>
                    @endif

                    <!-- LOGIN FORM -->
                    <form method="POST" action="{{ route('admin.login.submit') }}" id="loginForm" class="login-form" novalidate>
                        @csrf

                        <!-- EMAIL OR USERNAME FIELD -->
                        <div class="form-group">
                            <label for="login_identifier" class="form-label">
                                <span>Email Address or Username</span>
                            </label>
                            <div class="input-wrapper {{ $errors->has('login') || $errors->has('email') ? 'has-error' : '' }}">
                                <span class="input-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"></path>
                                        <circle cx="12" cy="7" r="4"></circle>
                                    </svg>
                                </span>
                                <input
                                    type="text"
                                    id="login_identifier"
                                    name="login"
                                    class="form-input"
                                    placeholder="Enter your email or username"
                                    value="{{ old('login') ?? old('email') }}"
                                    autocomplete="username"
                                    required
                                    autofocus
                                >
                            </div>
                        </div>

                        <!-- PASSWORD FIELD -->
                        <div class="form-group">
                            <label for="password" class="form-label">Password</label>
                            <div class="input-wrapper has-suffix {{ $errors->has('password') ? 'has-error' : '' }}">
                                <span class="input-icon" aria-hidden="true">
                                    <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                                        <rect x="3" y="11" width="18" height="11" rx="2" ry="2"></rect>
                                        <path d="M7 11V7a5 5 0 0 1 10 0v4"></path>
                                    </svg>
                                </span>
                                <input
                                    type="password"
                                    id="password"
                                    name="password"
                                    class="form-input"
                                    placeholder="Enter your password"
                                    autocomplete="current-password"
                                    required
                                >
                                <button
                                    type="button"
                                    id="togglePassword"
                                    class="toggle-password-btn"
                                    aria-label="Show password"
                                >
                                    <!-- Eye Icon (shown by default) -->
                                    <svg id="eyeIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                        <path d="M1 12s4-8 11-8 11 8 11 8-4 8-11 8-11-8-11-8z"></path>
                                        <circle cx="12" cy="12" r="3"></circle>
                                    </svg>
                                    <!-- Eye Off Icon (hidden by default) -->
                                    <svg id="eyeOffIcon" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" style="display: none;" aria-hidden="true">
                                        <path d="M17.94 17.94A10.07 10.07 0 0 1 12 20c-7 0-11-8-11-8a18.45 18.45 0 0 1 5.06-5.94M9.9 4.24A9.12 9.12 0 0 1 12 4c7 0 11 8 11 8a18.5 18.5 0 0 1-2.16 3.19m-6.72-1.07a3 3 0 1 1-4.24-4.24"></path>
                                        <line x1="1" y1="1" x2="23" y2="23"></line>
                                    </svg>
                                </button>
                            </div>
                        </div>

                        <!-- REMEMBER ME & FORGOT PASSWORD -->
                        <div class="form-options-row">
                            <label for="remember" class="remember-label">
                                <input
                                    type="checkbox"
                                    id="remember"
                                    name="remember"
                                    class="remember-checkbox"
                                    {{ old('remember') ? 'checked' : '' }}
                                >
                                <span>Remember me</span>
                            </label>

                            <button type="button" id="forgotPasswordLink" class="forgot-password-link">
                                Forgot Password?
                            </button>
                        </div>

                        <!-- LOGIN SUBMIT BUTTON -->
                        <button type="submit" id="loginButton" class="submit-btn">
                            <span class="button-text">LOGIN</span>
                            <span class="button-icon" aria-hidden="true">
                                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                    <line x1="5" y1="12" x2="19" y2="12"></line>
                                    <polyline points="12 5 19 12 12 19"></polyline>
                                </svg>
                            </span>
                            <span class="button-spinner hidden" aria-hidden="true"></span>
                        </button>
                    </form>

                    <!-- OR DIVIDER -->
                    <div class="social-divider" role="separator" aria-label="Alternative sign in options">
                        <span class="divider-line"></span>
                        <span class="divider-text">OR</span>
                        <span class="divider-line"></span>
                    </div>

                    <!-- CONTINUE WITH SOCIAL LOGIN SECTION -->
                    <div class="social-auth-section">
                        <div class="social-section-title">Continue with</div>
                        <div class="social-buttons-grid">
                            <!-- GOOGLE LOGIN BUTTON -->
                            <a href="{{ route('auth.social.redirect', ['provider' => 'google']) }}" class="social-btn btn-google" id="googleLoginBtn">
                                <svg class="social-icon" width="18" height="18" viewBox="0 0 24 24" aria-hidden="true">
                                    <path fill="#4285F4" d="M22.56 12.25c0-.78-.07-1.53-.2-2.25H12v4.26h5.92c-.26 1.37-1.04 2.53-2.21 3.31v2.77h3.57c2.08-1.92 3.28-4.74 3.28-8.09z"/>
                                    <path fill="#34A853" d="M12 23c2.97 0 5.46-.98 7.28-2.66l-3.57-2.77c-.98.66-2.23 1.06-3.71 1.06-2.86 0-5.29-1.93-6.16-4.53H2.18v2.84C3.99 20.53 7.7 23 12 23z"/>
                                    <path fill="#FBBC05" d="M5.84 14.09c-.22-.66-.35-1.36-.35-2.09s.13-1.43.35-2.09V7.06H2.18C1.43 8.55 1 10.22 1 12s.43 3.45 1.18 4.94l2.85-2.22.81-.63z"/>
                                    <path fill="#EA4335" d="M12 5.38c1.62 0 3.06.56 4.21 1.64l3.15-3.15C17.45 2.09 14.97 1 12 1 7.7 1 3.99 3.47 2.18 7.06l3.66 2.84c.87-2.6 3.3-4.52 6.16-4.52z"/>
                                </svg>
                                <span>Continue with Google</span>
                            </a>

                            <!-- FACEBOOK LOGIN BUTTON -->
                            <a href="{{ route('auth.social.redirect', ['provider' => 'facebook']) }}" class="social-btn btn-facebook" id="facebookLoginBtn">
                                <svg class="social-icon" width="18" height="18" viewBox="0 0 24 24" fill="#1877F2" aria-hidden="true">
                                    <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
                                </svg>
                                <span>Continue with Facebook</span>
                            </a>
                        </div>
                    </div>

                    <!-- UNIFIED ROLE INDICATOR -->
                    <div class="card-role-indicator">
                        <div class="role-badge-pill">
                            <svg width="13" height="13" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.3" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                                <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"></path>
                            </svg>
                            <span>ROLE-BASED AUTHENTICATION</span>
                        </div>
                        <span class="role-indicator-text">
                            Automatic routing to Customer, Staff, or Admin dashboard
                        </span>
                    </div>

                    <!-- FOOTER -->
                    <footer class="login-page-footer">
                        &copy; 2026 AQUA CLAYNE &middot; Purified Water Refilling Station
                    </footer>

                </div>
            </section>

        </main>

    </div>

    <!-- JavaScript -->
    <script src="{{ asset('js/admin-login.js') }}?v=20261101-1" defer></script>

</body>
</html>
