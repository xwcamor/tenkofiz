<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Restablecer Contraseña</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/admin-lte@3.2.0/dist/css/adminlte.min.css">
    <style>body{background:linear-gradient(135deg,#0f2b46,#2e75b6)}</style>
</head>
<body class="hold-transition login-page">
<div class="login-box">
    <div class="card card-outline card-primary">
        <div class="card-header text-center"><span class="h5"><i class="fas fa-key text-primary"></i> Nueva contraseña</span></div>
        <div class="card-body">
            @if($errors->any())<div class="alert alert-danger py-2 text-sm">{{ $errors->first() }}</div>@endif
            <form method="POST" action="{{ route('password.update') }}">
                @csrf
                <input type="hidden" name="token" value="{{ $token }}">
                <div class="input-group mb-3">
                    <input type="email" name="email" value="{{ old('email', $email) }}" class="form-control" placeholder="Correo electrónico" required>
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-envelope"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password" class="form-control" placeholder="Nueva contraseña (mín. 8)" required minlength="8">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <div class="input-group mb-3">
                    <input type="password" name="password_confirmation" class="form-control" placeholder="Confirmar contraseña" required minlength="8">
                    <div class="input-group-append"><div class="input-group-text"><span class="fas fa-lock"></span></div></div>
                </div>
                <button class="btn btn-primary btn-block"><i class="fas fa-save"></i> Restablecer contraseña</button>
            </form>
        </div>
    </div>
</div>
</body>
</html>
