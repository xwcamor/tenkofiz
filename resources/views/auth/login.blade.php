<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} | {{ __('Facial Attendance') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap@4.6.2/dist/css/bootstrap.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100vh;
            margin: 0;
            display: flex;
            align-items: center;
            justify-content: center;
            background: radial-gradient(1200px 600px at 15% -10%, #1d3a5f 0%, transparent 55%),
                        radial-gradient(1000px 500px at 110% 110%, #14487e 0%, transparent 50%),
                        #0f1b2d;
            padding: 1rem;
        }
        .auth-card {
            width: 100%;
            max-width: 400px;
            background: #fff;
            border-radius: 16px;
            box-shadow: 0 24px 64px rgba(0, 0, 0, .35);
            padding: 2.2rem 2rem;
        }
        .auth-logo {
            width: 52px; height: 52px;
            border-radius: 14px;
            background: #e8f1fc;
            color: #2a78d6;
            display: flex; align-items: center; justify-content: center;
            font-size: 1.5rem;
            margin: 0 auto 1rem;
        }
        h1 { font-size: 1.25rem; font-weight: 700; color: #101828; text-align: center; letter-spacing: -.01em; }
        .auth-sub { color: #667085; font-size: .875rem; text-align: center; margin-bottom: 1.5rem; }
        label { font-size: .8rem; font-weight: 600; color: #475467; }
        .form-control {
            border-radius: 9px;
            border-color: #d5dce8;
            padding: .6rem .85rem;
            height: auto;
            font-size: .9rem;
        }
        .form-control:focus { border-color: #2a78d6; box-shadow: 0 0 0 3px rgba(42, 120, 214, .15); }
        .btn-brand {
            background: #2a78d6; border: 0; color: #fff;
            border-radius: 9px; padding: .65rem;
            font-weight: 600; font-size: .9rem; width: 100%;
        }
        .btn-brand:hover { background: #1c5cab; color: #fff; }
        .auth-links { font-size: .84rem; }
        .auth-links a { color: #2a78d6; font-weight: 500; text-decoration: none; }
        .auth-footer { color: #9fb0c7; font-size: .75rem; text-align: center; margin-top: 1.25rem; }
        .custom-check { font-size: .84rem; color: #475467; }
        .alert { border: 0; border-radius: 9px; font-size: .84rem; padding: .55rem .9rem; }
    </style>
</head>
<body>
<div>
    <div class="auth-card">
        <div class="auth-logo"><i class="fas fa-id-badge"></i></div>
        <h1>{{ __('Facial Attendance') }}</h1>
        <p class="auth-sub">{{ __('Sign in to access the system') }}</p>

        @if(session('ok'))<div class="alert alert-success">{{ session('ok') }}</div>@endif
        @if($errors->any())<div class="alert alert-danger">{{ $errors->first() }}</div>@endif

        <form method="POST" action="{{ route('login') }}">
            @csrf
            <div class="form-group">
                <label>{{ __('Email address') }}</label>
                <input type="email" name="email" value="{{ old('email') }}" class="form-control" required autofocus>
            </div>
            <div class="form-group">
                <label>{{ __('Password') }}</label>
                <input type="password" name="password" class="form-control" required>
            </div>
            <div class="d-flex justify-content-between align-items-center mb-3">
                <label class="custom-check mb-0"><input type="checkbox" name="remember" class="mr-1">{{ __('Remember me') }}</label>
                <span class="auth-links"><a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a></span>
            </div>
            <button class="btn-brand">{{ __('Sign in') }}</button>
        </form>

        <div class="text-center mt-3 auth-links">
            <form method="POST" action="{{ route('locale.switch') }}" class="d-inline">@csrf
                <input type="hidden" name="locale" value="{{ app()->getLocale() === 'es' ? 'en' : 'es' }}">
                <button class="btn btn-link btn-sm p-0 auth-links" style="font-size:.84rem"><i class="fas fa-globe"></i> {{ app()->getLocale() === 'es' ? 'English' : 'Español' }}</button>
            </form>
        </div>
    </div>
    <p class="auth-footer">{{ __('Attendance Control System with Facial Recognition') }}</p>
</div>
</body>
</html>
