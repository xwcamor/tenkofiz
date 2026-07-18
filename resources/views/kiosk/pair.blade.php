<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ __('Pair kiosk device') }}</title>
    <link href="{{ vendor_asset('vendor/bootstrap5/bootstrap.min.css', 'https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css') }}" rel="stylesheet">
    <link rel="stylesheet" href="{{ vendor_asset('vendor/fontawesome/css/all.min.css', 'https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.2/css/all.min.css') }}">
    <style>
        body { background: radial-gradient(1200px 600px at 50% -12%, #17324f 0%, transparent 55%), #0d1420; color: #e8eef6; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .pair-card { background: #16202e; border: 1px solid #2b3a4e; border-radius: 18px; padding: 2rem; width: 100%; max-width: 420px; text-align: center; }
        .pair-card input { background: #0d141d; border: 1px solid #2b3a4e; color: #fff; font-size: 1.6rem; letter-spacing: 4px; text-align: center; text-transform: uppercase; }
        .pair-card input::placeholder { letter-spacing: normal; color: #7f93aa; }
    </style>
</head>
<body>
<div class="pair-card">
    <div style="font-size:2.4rem; color:#4a90e2"><i class="fas fa-tablet-alt"></i></div>
    <h4 class="mt-2">{{ __('Pair this device') }}</h4>
    <p class="text-secondary small">{{ __('Enter the pairing code an administrator generated in Settings. After pairing, only this device will be able to open the kiosk.') }}</p>

    @if($errors->any())
        <div class="alert alert-danger py-2">{{ $errors->first() }}</div>
    @endif

    <form method="POST" action="{{ route('kiosk.pair.submit') }}">
        @csrf
        <input type="text" name="code" value="{{ $code }}" maxlength="16" class="form-control form-control-lg mb-3" placeholder="{{ __('Pairing code') }}" autofocus autocomplete="off" required>
        <button class="btn btn-primary btn-lg w-100"><i class="fas fa-link"></i> {{ __('Pair device') }}</button>
    </form>
</div>
</body>
</html>
