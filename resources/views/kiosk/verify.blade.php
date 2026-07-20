<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Face confirmation') }}</title>
    <link href="{{ vendor_asset('vendor/bootstrap5/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    @include('kiosk.partials.style')
</head>
<body class="kiosk-cam">
<div class="container py-3 text-center">
    <div class="d-flex justify-content-between align-items-center mb-2" style="max-width:560px;margin:0 auto">
        <a href="{{ route('kiosk') }}" class="btn btn-sm btn-outline-light"><i class="fas fa-arrow-left"></i> {{ __('Back') }}</a>
        <span class="person-chip"><i class="fas fa-user"></i> {{ $employee->first_name }} {{ $employee->last_name }}</span>
        <span style="width:74px"></span>
    </div>

    <div class="video-frame">
        <video id="video" autoplay muted playsinline></video>
        <canvas id="overlay"></canvas>
    </div>
    {{-- Countdown BELOW the circle (readable), not over the camera --}}
    <div id="countdown" class="kiosk-countdown" style="display:none"></div>
    <div class="progress kiosk-progress"><div class="progress-bar bg-info" id="verifyProgress" style="width:0%"></div></div>
    {{-- Face presence indicator: instant feedback while searching --}}
    <div class="mt-2" id="faceChip" style="display:none">
        <span class="badge px-3 py-2" id="faceChipBadge" style="font-size:.85rem"></span>
    </div>

    <div id="result" class="mt-3">
        <div id="status" class="alert alert-secondary d-inline-block px-4">{{ __('Loading models...') }}</div>
    </div>

    {{-- End-of-window options: explicit buttons, nothing happens behind your back.
         Document marking is ONLY the fallback for someone already enrolled whose
         recognition failed — a non-enrolled person never gets that button. --}}
    <div id="actionRow" class="mt-1" style="display:none">
        <button class="btn btn-primary px-4 m-1" id="retryBtn" onclick="retryVerify()"><i class="fas fa-redo"></i> {{ __('Try again') }}</button>
        @if($employee->hasFace())
            <button class="btn btn-outline-warning px-4 m-1" id="markDocBtn" onclick="markByDocument()" style="display:none"><i class="fas fa-id-card"></i> {{ __('Mark by document (photo evidence)') }}</button>
        @endif
        <a href="{{ route('kiosk') }}" class="btn btn-outline-light px-4 m-1">{{ __('Cancel') }}</a>
    </div>

    {{-- Self-enrollment (only when this person has no enrolled face yet) --}}
    @unless($employee->hasFace())
        <div class="kiosk-card mt-3 text-start" id="enrollCard" style="max-width:560px;display:none">
            <h6 class="text-white mb-2"><i class="fas fa-user-plus"></i> {{ __('You have no enrolled face yet — enroll it now (one time only)') }}</h6>
            @if($enrollPinConfigured && !$enrollUnlocked)
                <div id="enrollPinStep">
                    <p class="kiosk-help small mb-2">{{ __('Ask your supervisor to enter the enrollment PIN (it unlocks this tablet for 15 minutes).') }}</p>
                    <div class="d-flex gap-2">
                        <input type="password" id="enrollPin" class="form-control text-center" maxlength="8" inputmode="numeric" placeholder="PIN" style="max-width:160px">
                        <button class="btn btn-primary" onclick="unlockEnroll()">{{ __('Unlock') }}</button>
                    </div>
                    <div id="enrollPinMessage" class="mt-2"></div>
                </div>
            @endif
            <div id="enrollCaptureStep" @if($enrollPinConfigured && !$enrollUnlocked) style="display:none" @endif>
                <div class="consent-box mb-2">
                    {{ __('The employee declares that they have been informed and consent to the processing of their biometric data (a 128-value mathematical vector of the face, not the photograph) for the sole purpose of attendance control, in accordance with the personal data protection law.') }}
                </div>
                <div class="form-check mb-2">
                    <input class="form-check-input" type="checkbox" id="enrollConsent">
                    <label class="form-check-label small text-light" for="enrollConsent">{{ __('I accept the biometric data consent') }}</label>
                </div>
                <div id="enrollMessage"></div>
                <button class="btn btn-success w-100" id="enrollBtn" onclick="enrollNow()"><i class="fas fa-camera"></i> {{ __('Capture my face (3 samples) and continue') }}</button>
            </div>
            @if(!$enrollPinConfigured)
                <p class="kiosk-help small mb-0 mt-2"><i class="fas fa-info-circle"></i> {{ __('Note: no enrollment PIN is configured in Settings, so enrollment here is open. Configure a PIN to require a supervisor.') }}</p>
            @endif
        </div>
    @endunless
</div>

<script>
    window.HOME_URL = "{{ route('kiosk') }}";
    window.MARK_URL = "{{ route('kiosk.mark') }}";
    window.MARK_DNI_URL = "{{ route('kiosk.markDni') }}";
    window.FACE_URL = "{{ route('kiosk.face', ['document' => $employee->document_number]) }}";
    window.ENROLL_UNLOCK_URL = "{{ route('kiosk.enroll.unlock') }}";
    window.ENROLL_DESCRIPTOR_URL = "{{ route('kiosk.enroll.descriptor') }}";
    window.CSRF = "{{ csrf_token() }}";
    window.EMPLOYEE = @json(['id' => $employee->id, 'name' => $employee->first_name, 'document' => $employee->document_number]);
    window.HAS_FACE = @json($employee->hasFace());
    window.KIOSK_THRESHOLD = @json((float) (app_setting()->kiosk_face_threshold ?: 0.5));
    window.KIOSK_LIVENESS = @json((bool) app_setting()->kiosk_liveness);
    window.KIOSK_VERIFY_SECONDS = @json((int) (app_setting()->kiosk_verify_seconds ?: 10));
    window.KIOSK_I18N = {
        loadingModels: @json(__('Loading recognition models...')),
        startingCamera: @json(__('Starting camera...')),
        lookAtCamera: @json(__('Confirming it is you, :name...')),
        comeCloser: @json(__('Move closer and look at the camera, :name...')),
        placeFaceInOval: @json(__('Bring your face into the circle')),
        comeCloser2: @json(__('Come closer — fill the circle with your face')),
        moveBack: @json(__('Move back a little')),
        centerFace: @json(__('Center your face in the circle')),
        challengeTurn: @json(__('Turn your head to one side')),
        challengeNod: @json(__('Nod — move your head up and down')),
        savingSlow: @json(__('Saving to the database, one moment please...')),
        recorded: @json(__('recorded')),
        checkIn: @json(__('CHECK-IN')),
        checkOut: @json(__('CHECK-OUT')),
        couldNotRecord: @json(__('Could not record.')),
        connectionError: @json(__('Connection error with the server. Retrying in a few seconds...')),
        startError: @json(__('Startup error:')),
        cameraFallback: @json(__('The camera is not available. You can still mark by document (no photo evidence).')),
        notConfirmed: @json(__('We could not confirm your face in :sec seconds. You can try again or mark by document (an evidence photo will be saved for review).')),
        noFaceSeen: @json(__('No face was detected in front of the camera — nothing was recorded. Come closer, improve the lighting and try again.')),
        enrollFirst: @json(__('To mark attendance you must first enroll your face (one time only). Complete the steps below.')),
        faceDetected: @json(__('Face detected')),
        noFaceYet: @json(__('Looking for a face...')),
        evidenceIntro: @json(__('Retrying detection — stay in front of the camera...')),
        evidenceClosing: @json(__('No face was detected — nothing was recorded. Returning to the kiosk...')),
        cameraNeededToEnroll: @json(__('The camera is not available and your face is not enrolled yet. Ask your supervisor to register your mark manually.')),
        verifyFailedPhoto: @json(__('Recorded by document (facial verification not completed).')),
        showYourFace: @json(__('Retrying detection — look at the camera...')),
        backSoon: @json(__('Returning to the kiosk...')),
        pinRequired: @json(__('Enter the PIN.')),
        unlocking: @json(__('Verifying PIN...')),
        consentRequired: @json(__('You must accept the biometric data consent before enrolling.')),
        capturingSample: @json(__('Capturing sample :current of :total... move your head slightly between captures.')),
        noFaceInSample: @json(__('No face was detected in sample :current. Move closer, improve the lighting and try again.')),
        saving: @json(__('Saving to the database...')),
        enrolled: @json(__('Enrolled! Now look at the camera to confirm and mark.')),
    };
</script>
<script defer src="{{ vendor_asset('vendor/faceapi/face-api.min.js', 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js') }}"></script>
<script defer src="{{ asset('js/kiosk-verify.js') }}?v={{ @filemtime(public_path('js/kiosk-verify.js')) ?: 1 }}"></script>
</body>
</html>
