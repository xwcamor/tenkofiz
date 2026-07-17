<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kiosco de Marcado Facial</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #101820; color: #fff; min-height: 100vh; }
        #reloj { font-size: clamp(1.6rem, 6vw, 2.4rem); font-weight: 700; letter-spacing: 2px; }
        h1.titulo { font-size: clamp(1rem, 4vw, 1.6rem); }
        /* Contenedor de video 100% responsive: se adapta a celular, tablet y PC */
        .marco-video {
            border: 4px solid #2e75b6;
            border-radius: 16px;
            overflow: hidden;
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            position: relative;
        }
        .marco-video video, .marco-video canvas {
            display: block;
            width: 100%;
            height: auto;
        }
        .marco-video canvas { position: absolute; top: 0; left: 0; }
        #resultado { min-height: 100px; }
    </style>
</head>
<body>
<div class="container py-3 py-md-4 text-center">
    <h1 class="titulo"><i class="fas fa-id-badge"></i> KIOSCO DE MARCADO DE ASISTENCIA</h1>
    <div id="reloj">--:--:--</div>
    <p class="text-secondary" id="fecha"></p>

    <div class="marco-video my-3">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="overlay"></canvas>
    </div>

    <div id="resultado">
        <div id="estado" class="alert alert-secondary d-inline-block px-4 px-md-5">Cargando modelos...</div>
    </div>
    <p class="text-secondary small px-2">Colóquese frente a la cámara. El sistema lo reconocerá y marcará su entrada o salida automáticamente.</p>
    <a href="{{ route('login') }}" class="text-secondary small">Ir al sistema <i class="fas fa-sign-in-alt"></i></a>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js"></script>
<script>
    window.DESCRIPTORES_URL = "{{ route('kiosco.descriptores') }}";
    window.MARCAR_URL = "{{ route('kiosco.marcar') }}";
    window.CSRF = "{{ csrf_token() }}";
</script>
<script defer src="{{ asset('js/kiosco.js') }}?v={{ @filemtime(public_path('js/kiosco.js')) ?: 1 }}"></script>
</body>
</html>
