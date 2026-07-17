<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar Contraseña</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <style>body{background:linear-gradient(135deg,#0f2b46,#2e75b6)}</style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center"><span class="h5"><i class="fas fa-unlock-alt text-primary"></i> Recuperar contraseña</span></div>
        <div class="card-body">
            <p class="login-box-msg">Ingrese su correo y le enviaremos un enlace para restablecerla</p>
            @if(session('ok'))<div class="alert alert-success py-2 text-sm">{{ session('ok') }}</div>@endif
            @if($errors->any())<div class="alert alert-danger py-2 text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('password.email') }}">
                @csrf
                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email') }}" class="form-control" placeholder="Correo electrónico" required autofocus>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <button class="btn btn-primary btn-block"><i class="fas fa-paper-plane"></i> Enviar enlace de recuperación</button>
            </form>
            <p class="mt-3 mb-0 text-center"><a href="{{ route('login') }}">Volver al inicio de sesión</a></p>
        </div>
    </div>
</div>
</body>
</html>
