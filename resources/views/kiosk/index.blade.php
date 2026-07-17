<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Facial Marking Kiosk') }}</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css">
    <style>
        body { background: #101820; color: #fff; min-height: 100vh; }
        #clock { font-size: clamp(1.6rem, 6vw, 2.4rem); font-weight: 700; letter-spacing: 2px; }
        h1.title { font-size: clamp(1rem, 4vw, 1.6rem); }
        /* Fully responsive video container: adapts to phone, tablet and PC */
        .video-frame {
            border: 4px solid #2e75b6;
            border-radius: 16px;
            overflow: hidden;
            width: 100%;
            max-width: 560px;
            margin: 0 auto;
            position: relative;
        }
        .video-frame video, .video-frame canvas {
            display: block;
            width: 100%;
            height: auto;
        }
        .video-frame canvas { position: absolute; top: 0; left: 0; }
        #result { min-height: 100px; }
    </style>
</head>
<body>
<div class="container py-3 py-md-4 text-center">
    <h1 class="title"><i class="fas fa-id-badge"></i> {{ strtoupper(__('Attendance Marking Kiosk')) }}</h1>
    <div id="clock">--:--:--</div>
    <p class="text-secondary" id="date"></p>

    <div class="video-frame my-3">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="overlay"></canvas>
    </div>

    <div id="result">
        <div id="status" class="alert alert-secondary d-inline-block px-4 px-md-5">{{ __('Loading models...') }}</div>
    </div>
    <p class="text-secondary small px-2">{{ __('Stand in front of the camera. The system will recognize you and record your check-in or check-out automatically.') }}</p>
    <p class="text-secondary small px-2 mb-1"><i class="fas fa-user-shield"></i> {{ __('Privacy: the camera image is processed on this device; only the match result, time, IP and device are stored.') }}</p>
    <a href="{{ route('login') }}" class="text-secondary small">{{ __('Go to the system') }} <i class="fas fa-sign-in-alt"></i></a>
</div>
<script defer src="https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js"></script>
<script>
    window.DESCRIPTORS_URL = "{{ route('kiosk.descriptors') }}";
    window.MARK_URL = "{{ route('kiosk.mark') }}";
    window.CSRF = "{{ csrf_token() }}";
    window.KIOSK_LOCALE = @json(app()->getLocale() === 'es' ? 'es-PE' : 'en-US');
    window.KIOSK_TZ = @json(company_timezone());
    window.KIOSK_I18N = {
        loadingModels1: @json(__('Loading recognition models (1/3)...')),
        loadingModels2: @json(__('Loading facial landmarks (2/3)...')),
        loadingModels3: @json(__('Loading recognition network (3/3)...')),
        loadingEmployees: @json(__('Fetching enrolled employees...')),
        noEmployees: @json(__('There are no employees with an enrolled face.')),
        startingCamera: @json(__('Starting camera...')),
        waitingFace: @json(__('Waiting for a face...')),
        notRecognized: @json(__('Face not recognized')),
        verifying: @json(__('Verifying identity of :name...')),
        savingSlow: @json(__('Saving to the database, one moment please...')),
        recorded: @json(__('recorded')),
        checkIn: @json(__('CHECK-IN')),
        checkOut: @json(__('CHECK-OUT')),
        couldNotRecord: @json(__('Could not record.')),
        connectionError: @json(__('Connection error with the server. Retrying in a few seconds...')),
        startError: @json(__('Startup error:')),
        startErrorHint: @json(__('Check the camera and the /public/models folder')),
    };
</script>
<script defer src="{{ asset('js/kiosk.js') }}?v={{ @filemtime(public_path('js/kiosk.js')) ?: 1 }}"></script>
</body>
</html>
