<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Facial Marking Kiosk') }}</title>
    <link href="{{ vendor_asset('vendor/bootstrap5/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    <style>
        body {
            background: radial-gradient(1200px 600px at 50% -12%, #17324f 0%, transparent 55%), #0d1420;
            color: #e8eef6;
            min-height: 100vh;
        }
        #clock { font-size: clamp(1.9rem, 6vw, 2.7rem); font-weight: 700; letter-spacing: 3px; color: #f4f8fd; }
        #date { color: #94a6bd; }
        #date::first-letter { text-transform: uppercase; }
        h1.title { font-size: clamp(1rem, 4vw, 1.5rem); color: #cdd9e8; letter-spacing: 1px; font-weight: 600; }
        h1.title i { color: #4a90e2; }
        .kiosk-help { color: #8fa2b8; }
        .enroll-link { opacity: .75; }
        .enroll-link:hover { opacity: 1; }
        /* Fully responsive video container: adapts to phone, tablet and PC */
        .video-frame {
            border: 3px solid #2e75b6;
            border-radius: 18px;
            box-shadow: 0 14px 48px rgba(0, 0, 0, .45);
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

        /* Overlay panels (the kiosk page has no Bootstrap JS: plain show/hide) */
        .kiosk-panel {
            display: none;
            position: fixed; inset: 0;
            background: rgba(6, 10, 16, .92);
            z-index: 50;
            align-items: center; justify-content: center;
            padding: 1rem;
        }
        .kiosk-panel.open { display: flex; }
        /* While capturing samples the person must see themselves on camera */
        .kiosk-panel.capturing { background: rgba(6, 10, 16, .25); align-items: flex-end; }
        .kiosk-panel.capturing .panel-card { background: rgba(22, 32, 46, .95); }
        .kiosk-panel .panel-card {
            background: #16202e;
            border: 1px solid #2b3a4e;
            border-radius: 18px;
            padding: 1.6rem;
            width: 100%; max-width: 420px;
            text-align: center;
        }
        .dni-display {
            background: #0d141d;
            border: 1px solid #2b3a4e;
            border-radius: 12px;
            font-size: 1.9rem; font-weight: 700; letter-spacing: 6px;
            padding: .5rem; margin-bottom: 1rem; min-height: 3.4rem;
        }
        .keypad { display: grid; grid-template-columns: repeat(3, 1fr); gap: .55rem; }
        .keypad button {
            font-size: 1.5rem; font-weight: 600;
            padding: .7rem 0;
            border-radius: 12px;
            border: 1px solid #2b3a4e;
            background: #1d2a3a; color: #fff;
        }
        .keypad button:active { background: #2e75b6; }
        .consent-box { text-align: left; font-size: .8rem; color: #b9c4d2; background: #0d141d; border: 1px solid #2b3a4e; border-radius: 10px; padding: .7rem .8rem; max-height: 150px; overflow-y: auto; }
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

    <div class="mb-3">
        <button id="dniBtn" class="btn btn-outline-light btn-lg px-4" onclick="openDniPanel()">
            <i class="fas fa-keyboard"></i> {{ __('Mark with document number') }}
        </button>
    </div>

    <p class="kiosk-help small px-2 mb-1">{{ __('Stand in front of the camera. The system will recognize you and record your check-in or check-out automatically.') }}</p>
    <p class="kiosk-help px-2 mb-3" style="font-size:.72rem"><i class="fas fa-user-shield"></i> {{ __('Privacy: the camera image is processed on this device; only the match result, time, IP and device are stored. Marking by document number saves an evidence snapshot.') }}</p>
    <button class="btn btn-sm btn-outline-light enroll-link" onclick="openEnrollPanel(); return false;"><i class="fas fa-user-plus"></i> {{ __('Enroll a face (supervisor)') }}</button>
</div>

{{-- ---------- DNI keypad panel (marking fallback) ---------- --}}
<div class="kiosk-panel" id="dniPanel">
    <div class="panel-card">
        <h5 class="mb-3"><i class="fas fa-keyboard"></i> {{ __('Mark with document number') }}</h5>
        <div class="dni-display" id="dniDisplay">&nbsp;</div>
        <div class="keypad mb-3">
            <button onclick="dniKey('1')">1</button><button onclick="dniKey('2')">2</button><button onclick="dniKey('3')">3</button>
            <button onclick="dniKey('4')">4</button><button onclick="dniKey('5')">5</button><button onclick="dniKey('6')">6</button>
            <button onclick="dniKey('7')">7</button><button onclick="dniKey('8')">8</button><button onclick="dniKey('9')">9</button>
            <button onclick="dniBackspace()"><i class="fas fa-backspace"></i></button>
            <button onclick="dniKey('0')">0</button>
            <button onclick="dniClear()"><i class="fas fa-times"></i></button>
        </div>
        <p class="text-secondary small">{{ __('A snapshot from the camera will be saved as evidence of this mark.') }}</p>
        <div id="dniMessage"></div>
        <div class="d-flex gap-2 justify-content-center mt-2">
            <button class="btn btn-secondary px-4" onclick="closeDniPanel()">{{ __('Cancel') }}</button>
            <button class="btn btn-primary px-4" id="dniSubmitBtn" onclick="submitDniMark()"><i class="fas fa-check"></i> {{ __('Mark') }}</button>
        </div>
    </div>
</div>

{{-- ---------- Enrollment mode panel (PIN protected) ---------- --}}
<div class="kiosk-panel" id="enrollPanel">
    <div class="panel-card">
        {{-- Step 1: PIN --}}
        <div id="enrollStepPin">
            <h5 class="mb-3"><i class="fas fa-lock"></i> {{ __('Enrollment mode') }}</h5>
            <p class="text-secondary small">{{ __('A supervisor must enter the PIN configured in Settings.') }}</p>
            <input type="password" id="enrollPin" class="form-control form-control-lg text-center mb-3" maxlength="8" inputmode="numeric" placeholder="PIN">
            <div id="enrollPinMessage"></div>
            <div class="d-flex gap-2 justify-content-center mt-2">
                <button class="btn btn-secondary px-4" onclick="closeEnrollPanel()">{{ __('Cancel') }}</button>
                <button class="btn btn-primary px-4" onclick="enrollUnlock()">{{ __('Unlock') }}</button>
            </div>
        </div>

        {{-- Step 2: find the employee by document --}}
        <div id="enrollStepLookup" style="display:none">
            <h5 class="mb-3"><i class="fas fa-user-plus"></i> {{ __('Enroll employee') }}</h5>
            <p class="text-secondary small">{{ __('Type the document number of the employee to enroll (they must already be registered).') }}</p>
            <input type="text" id="enrollDni" class="form-control form-control-lg text-center mb-3" maxlength="12" inputmode="numeric" placeholder="{{ __('Document number') }}">
            <div id="enrollLookupMessage"></div>
            <div class="d-flex gap-2 justify-content-center mt-2">
                <button class="btn btn-secondary px-4" onclick="closeEnrollPanel()">{{ __('Cancel') }}</button>
                <button class="btn btn-primary px-4" onclick="enrollLookup()">{{ __('Search') }}</button>
            </div>
        </div>

        {{-- Step 3: consent + capture --}}
        <div id="enrollStepCapture" style="display:none">
            <h5 class="mb-2" id="enrollName">—</h5>
            <div id="enrollHasFaceWarning" class="alert alert-warning py-1 small" style="display:none">{{ __('A face is already enrolled; capturing again will replace it.') }}</div>
            <div class="consent-box mb-2" id="enrollConsentText">
                {{ __('The employee declares that they have been informed and consent to the processing of their biometric data (a 128-value mathematical vector of the face, not the photograph) for the sole purpose of attendance control, in accordance with the personal data protection law.') }}
            </div>
            <div class="form-check text-start mb-3">
                <input class="form-check-input" type="checkbox" id="enrollConsent">
                <label class="form-check-label small" for="enrollConsent">{{ __('I accept the biometric data consent') }}</label>
            </div>
            <div id="enrollCaptureMessage"></div>
            <div class="d-flex gap-2 justify-content-center mt-2">
                <button class="btn btn-secondary px-4" onclick="closeEnrollPanel()">{{ __('Cancel') }}</button>
                <button class="btn btn-success px-4" id="enrollCaptureBtn" onclick="enrollCapture()"><i class="fas fa-camera"></i> {{ __('Capture (3 samples)') }}</button>
            </div>
        </div>
    </div>
</div>

<script defer src="{{ vendor_asset('vendor/faceapi/face-api.min.js', 'https://cdn.jsdelivr.net/npm/@vladmandic/face-api@1.7.13/dist/face-api.min.js') }}"></script>
<script>
    window.DESCRIPTORS_URL = "{{ route('kiosk.descriptors') }}";
    window.VERSION_URL = "{{ route('kiosk.version') }}";
    window.MARK_URL = "{{ route('kiosk.mark') }}";
    window.MARK_DNI_URL = "{{ route('kiosk.markDni') }}";
    window.ENROLL_UNLOCK_URL = "{{ route('kiosk.enroll.unlock') }}";
    window.ENROLL_LOOKUP_URL = "{{ route('kiosk.enroll.lookup') }}";
    window.ENROLL_DESCRIPTOR_URL = "{{ route('kiosk.enroll.descriptor') }}";
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
        dniIncomplete: @json(__('Type a document number of 8 to 12 digits.')),
        marking: @json(__('Recording the mark...')),
        listUpdated: @json(__('Face list updated.')),
        pinRequired: @json(__('Enter the PIN.')),
        unlocking: @json(__('Verifying PIN...')),
        searching: @json(__('Searching...')),
        consentRequired: @json(__('You must accept the biometric data consent before enrolling.')),
        capturingSample: @json(__('Capturing sample :current of :total... move your head slightly between captures.')),
        noFaceInSample: @json(__('No face was detected in sample :current. Move closer, improve the lighting and try again.')),
        saving: @json(__('Saving to the database...')),
        enrolled: @json(__('Enrolled! The kiosk will recognize this face from now on.')),
    };
</script>
<script defer src="{{ asset('js/kiosk.js') }}?v={{ @filemtime(public_path('js/kiosk.js')) ?: 1 }}"></script>
<script src="{{ asset('js/trim-inputs.js') }}?v={{ @filemtime(public_path('js/trim-inputs.js')) ?: 1 }}"></script>
</body>
</html>
