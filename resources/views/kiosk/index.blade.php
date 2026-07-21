<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Attendance Marking Kiosk') }}</title>
    <link href="{{ vendor_asset('vendor/bootstrap5/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    @include('kiosk.partials.style')
</head>
<body>
<div class="container py-3 py-md-4 text-center">
    <h1 class="title"><i class="fas fa-id-badge"></i> {{ strtoupper(__('Attendance Marking Kiosk')) }}</h1>
    @isset($site)
        @if($site)<div class="mb-1"><span class="badge" style="background:#2e75b6"><i class="fas fa-map-marker-alt"></i> {{ $site->name }}</span></div>@endif
    @endisset
    <div id="clock">--:--:--</div>
    <p class="text-secondary" id="date"></p>

    {{-- Step 1: the document filters BEFORE any camera opens --}}
    <div class="kiosk-card mt-2">
        <h5 class="mb-3 text-white"><i class="fas fa-keyboard"></i> {{ __('Type your document number') }}</h5>
        <div class="dni-display" id="dniDisplay">&nbsp;</div>
        <div class="keypad mb-3">
            <button onclick="dniKey('1')">1</button><button onclick="dniKey('2')">2</button><button onclick="dniKey('3')">3</button>
            <button onclick="dniKey('4')">4</button><button onclick="dniKey('5')">5</button><button onclick="dniKey('6')">6</button>
            <button onclick="dniKey('7')">7</button><button onclick="dniKey('8')">8</button><button onclick="dniKey('9')">9</button>
            <button onclick="dniBackspace()"><i class="fas fa-backspace"></i></button>
            <button onclick="dniKey('0')">0</button>
            <button onclick="dniClear()"><i class="fas fa-times"></i></button>
        </div>
        <div id="dniMessage"></div>
        <button class="btn btn-primary btn-lg w-100" id="dniSubmitBtn" onclick="submitLookup()"><i class="fas fa-arrow-right"></i> {{ __('Continue') }}</button>
        <p class="kiosk-help small mt-3 mb-0">{{ __('Next step: the camera opens to confirm it is you. If your face is not enrolled yet, you can enroll it there.') }}</p>
    </div>

    <p class="kiosk-help px-2 mt-3 mb-2" style="font-size:.72rem"><i class="fas fa-user-shield"></i> {{ __('Privacy: the camera image is processed on this device; only the match result, time, IP and device are stored. Marking by document number saves an evidence snapshot.') }}</p>
</div>

<script>
    window.LOOKUP_URL = "{{ route('kiosk.lookup') }}";
    window.CSRF = "{{ csrf_token() }}";
    window.KIOSK_LOCALE = @json(app()->getLocale() === 'es' ? 'es-PE' : 'en-US');
    window.KIOSK_TZ = @json(company_timezone());
    window.KIOSK_I18N = {
        dniIncomplete: @json(__('Type a document number of 8 to 12 digits.')),
        searching: @json(__('Searching...')),
        connectionError: @json(__('Connection error with the server. Retrying in a few seconds...')),
    };
</script>
<script src="{{ asset('js/kiosk-home.js') }}?v={{ @filemtime(public_path('js/kiosk-home.js')) ?: 1 }}"></script>
</body>
</html>
