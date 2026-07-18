<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Enrollment mode') }}</title>
    <link href="{{ vendor_asset('vendor/bootstrap5/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    @include('kiosk.partials.style')
</head>
<body>
<div class="container py-3 text-center">
    <div class="d-flex justify-content-between align-items-center mb-2" style="max-width:560px;margin:0 auto">
        <a href="{{ route('kiosk') }}" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
        <h1 class="title mb-0"><i class="fas fa-user-plus"></i> {{ __('Enrollment mode') }}</h1>
        <span style="width:74px"></span>
    </div>
    @isset($site)
        @if($site)<div class="mb-2"><span class="badge" style="background:#2e75b6"><i class="fas fa-map-marker-alt"></i> {{ $site->name }}</span></div>@endif
    @endisset

    {{-- The camera stays ALWAYS visible at the top; the steps live below it --}}
    <div class="video-frame mb-3">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="overlay"></canvas>
    </div>

    <div id="result" class="mb-2">
        <div id="status" class="alert alert-secondary d-inline-block px-4">{{ __('Loading models...') }}</div>
    </div>

    {{-- Step 1: supervisor PIN --}}
    <div class="kiosk-card text-start mb-3" id="stepPin" @if($enrollUnlocked) style="display:none" @endif>
        <h6 class="text-white"><i class="fas fa-lock"></i> {{ __('Supervisor PIN') }}</h6>
        <p class="kiosk-help small mb-2">{{ __('A supervisor must enter the PIN configured in Settings. It unlocks this tablet\'s enrollment for 15 minutes.') }}</p>
        <div class="d-flex gap-2">
            <input type="password" id="enrollPin" class="form-control text-center" maxlength="8" inputmode="numeric" placeholder="PIN" style="max-width:160px">
            <button class="btn btn-primary" onclick="unlockEnroll()">{{ __('Unlock') }}</button>
        </div>
        <div id="pinMessage" class="mt-2"></div>
    </div>

    {{-- Step 2: find the employee --}}
    <div class="kiosk-card text-start mb-3" id="stepLookup" @unless($enrollUnlocked) style="display:none" @endunless>
        <h6 class="text-white"><i class="fas fa-search"></i> {{ __('Employee to enroll') }}</h6>
        <p class="kiosk-help small mb-2">{{ __('Type the document number of the employee to enroll (they must already be registered).') }}</p>
        <div class="d-flex gap-2">
            <input type="text" id="enrollDni" class="form-control text-center" maxlength="12" inputmode="numeric" placeholder="{{ __('Document number') }}" style="max-width:220px">
            <button class="btn btn-primary" onclick="lookupEmployee()">{{ __('Search') }}</button>
        </div>
        <div id="lookupMessage" class="mt-2"></div>
    </div>

    {{-- Step 3: consent + capture (camera above stays fully visible) --}}
    <div class="kiosk-card text-start" id="stepCapture" style="display:none">
        <h6 class="text-white mb-1" id="enrollName">—</h6>
        <div id="hasFaceWarning" class="alert alert-warning py-1 small" style="display:none">{{ __('A face is already enrolled; capturing again will replace it.') }}</div>
        <div class="consent-box mb-2">
            {{ __('The employee declares that they have been informed and consent to the processing of their biometric data (a 128-value mathematical vector of the face, not the photograph) for the sole purpose of attendance control, in accordance with the personal data protection law.') }}
        </div>
        <div class="form-check mb-2">
            <input class="form-check-input" type="checkbox" id="enrollConsent">
            <label class="form-check-label small text-light" for="enrollConsent">{{ __('I accept the biometric data consent') }}</label>
        </div>
        <div id="captureMessage"></div>
        <button class="btn btn-success w-100" id="captureBtn" onclick="captureSamples()"><i class="fas fa-camera"></i> {{ __('Capture (3 samples)') }}</button>
    </div>
</div>

<script>
    window.HOME_URL = "{{ route('kiosk') }}";
    window.ENROLL_UNLOCK_URL = "{{ route('kiosk.enroll.unlock') }}";
    window.ENROLL_LOOKUP_URL = "{{ route('kiosk.enroll.lookup') }}";
    window.ENROLL_DESCRIPTOR_URL = "{{ route('kiosk.enroll.descriptor') }}";
    window.CSRF = "{{ csrf_token() }}";
    window.KIOSK_I18N = {
        loadingModels: @json(__('Loading recognition models...')),
        startingCamera: @json(__('Starting camera...')),
        cameraReady: @json(__('Camera ready. Complete the steps below.')),
        startError: @json(__('Startup error:')),
        pinRequired: @json(__('Enter the PIN.')),
        unlocking: @json(__('Verifying PIN...')),
        dniIncomplete: @json(__('Type a document number of 8 to 12 digits.')),
        searching: @json(__('Searching...')),
        consentRequired: @json(__('You must accept the biometric data consent before enrolling.')),
        capturingSample: @json(__('Capturing sample :current of :total... move your head slightly between captures.')),
        noFaceInSample: @json(__('No face was detected in sample :current. Move closer, improve the lighting and try again.')),
        saving: @json(__('Saving to the database...')),
        enrolled: @json(__('Enrolled! The kiosk will recognize this face from now on.')),
        couldNotRecord: @json(__('Could not record.')),
        connectionError: @json(__('Connection error with the server. Retrying in a few seconds...')),
    };
</script>
<script defer src="{{ vendor_asset('vendor/faceapi/face-api.min.js', 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js') }}"></script>
<script defer src="{{ asset('js/kiosk-enroll.js') }}?v={{ @filemtime(public_path('js/kiosk-enroll.js')) ?: 1 }}"></script>
</body>
</html>
