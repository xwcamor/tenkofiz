<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Iniciar Sesión | Asistencia Facial</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <style>body{background:linear-gradient(135deg,#0f2b46,#2e75b6)}</style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center">
            <span class="h4"><i class="fas fa-id-badge text-primary"></i> <b>Asistencia</b> Facial</span>
        </div>
        <div class="card-body">
            <p class="login-box-msg">Inicie sesión para acceder al sistema</p>
            @if(session('ok'))
                <div class="alert alert-success py-2 text-sm">{{ session('ok') }}</div>
            @endif
            @if($errors->any())
                <div class="alert alert-danger py-2 text-sm">{{ $errors->first() }}</div>
            @endif
            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="Correo electrónico" required autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Contraseña" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <div class="row">
                    <div class="col-7">
                        <div class="icheck-primary">
                            <input type="checkbox" name="remember" id="remember">
                            <label for="remember">Recordarme</label>
                        </div>
                    </div>
                    <div class="col-5">
                        <button class="btn btn-primary btn-block"><i class="fas fa-sign-in-alt"></i> Ingresar</button>
                    </div>
                </div>
            </form>
            <p class="mt-3 mb-1 text-center">
                <a href="{{ route('password.request') }}">¿Olvidó su contraseña?</a>
            </p>
            <p class="mb-0 text-center">
                <a href="{{ route('kiosco') }}"><i class="fas fa-camera"></i> Ir al kiosco de marcado</a>
            </p>
        </div>
    </div>
</div>
</body>
</html>
