<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Sign in') }} | {{ __('Facial Attendance') }}</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <style>body{background:linear-gradient(135deg,#0f2b46,#2e75b6)}</style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <span class="h4"><i class="fas fa-id-badge text-primary"></i> <b>{{ __('Facial') }}</b> {{ __('Attendance') }}</span>
        </div>
        <div class="card-body">
            <p class="login-box-msg">{{ __('Sign in to access the system') }}</p>
            @if(session('ok'))
                <div class="alert alert-success py-2 text-sm">{{ session('ok') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger py-2 text-sm">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="{{ __('Email address') }}" required autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="{{ __('Password') }}" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <div class="row">
                    <div class="col-7">
                        <div class="icheck-primary">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">{{ __('Remember me') }}</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> {{ __('Sign in') }}</button>
                    </div>
                </div>
            </form>
            <p class="mt-3 mb-1 text-center">
                <a href="{{ route('password.request') }}">{{ __('Forgot your password?') }}</a>
            </p>
            <p class="mb-0 text-center">
                <form method="POST" action="{{ route('locale.switch') }}" class="d-inline">@csrf
                    <input type="hidden" name="locale" value="{{ app()->getLocale() === 'es' ? 'en' : 'es' }}">
                    <button class="btn btn-link btn-sm p-0"><i class="fas fa-globe"></i> {{ app()->getLocale() === 'es' ? 'English' : 'Español' }}</button>
                </form>
            </p>
        </div>
    </div>
</div>
</body>
</html>
