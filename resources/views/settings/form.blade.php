@extends('layouts.app')
@section('title', __('System settings'))
@section('content')
<div class="row">
    <div class="col-md-6">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-building"></i> {{ __('Company data (shown in reports and sheets)') }}</h3></div>
            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label>{{ __('Name / Legal name') }}</label>
                        <input name="company_name" value="{{ old('company_name', $setting->company_name) }}" class="form-control @error('company_name') is-invalid @enderror" required>
                        @error('company_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{ __('Tax ID') }}</label>
                            <input name="tax_id" value="{{ old('tax_id', $setting->tax_id) }}" class="form-control @error('tax_id') is-invalid @enderror" maxlength="11">
                            @error('tax_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Phone') }}</label>
                            <input name="phone" value="{{ old('phone', $setting->phone) }}" class="form-control">
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Address') }}</label>
                        <input name="address" value="{{ old('address', $setting->address) }}" class="form-control">
                    </div>
                    <div class="form-group">
                        <label>{{ __('Company timezone') }}</label>
                        <select name="timezone" class="form-control @error('timezone') is-invalid @enderror" required>
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', $setting->timezone) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="text-muted">{{ __('The server runs in UTC. Kiosk marks, tardiness rules and absence generation use this timezone. Each user can pick their own display timezone in My account.') }}</small>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Payroll cut-off day') }}</label>
                        <select name="cutoff_day" class="form-control @error('cutoff_day') is-invalid @enderror">
                            <option value="">{{ __('Calendar month (1st to last day)') }}</option>
                            @for($day = 1; $day <= 28; $day++)
                                <option value="{{ $day }}" @selected(old('cutoff_day', $setting->cutoff_day) == $day)>{{ $day }}</option>
                            @endfor
                        </select>
                        @error('cutoff_day')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="text-muted">
                            {{ __('E.g. 19: worked days are counted from the 20th of one month to the 19th of the next. Attendance and Reports open on the current cut-off period by default.') }}
                            @php [$periodStart, $periodEnd] = current_period(); @endphp
                            <br><strong>{{ __('Current period') }}:</strong> {{ $periodStart->format('d/m/Y') }} – {{ $periodEnd->format('d/m/Y') }}
                        </small>
                    </div>
                    <div class="form-row">
                        <div class="col-md-6 form-group">
                            <label>{{ __('Early check-in window') }} <small class="text-muted">({{ __('minutes; 0 = no limit') }})</small></label>
                            <input type="number" name="early_check_in_minutes" min="0" max="720" value="{{ old('early_check_in_minutes', $setting->early_check_in_minutes) }}" class="form-control @error('early_check_in_minutes') is-invalid @enderror">
                            @error('early_check_in_minutes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            <small class="text-muted">{{ __('How many minutes before their scheduled start an employee may check in. E.g. 60: someone on an 08:00 shift can mark from 07:00; earlier marks are rejected. 0 = mark at any time (no restriction).') }}</small>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Early departure alert') }} <small class="text-muted">({{ __('minutes; 0 = disabled') }})</small></label>
                            <input type="number" name="early_departure_minutes" min="0" max="480" value="{{ old('early_departure_minutes', $setting->early_departure_minutes) }}" class="form-control @error('early_departure_minutes') is-invalid @enderror">
                            @error('early_departure_minutes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            <small class="text-muted">{{ __('If the check-out happens more than this many minutes before the scheduled end, the mark is kept but flagged with an automatic note for the supervisor. It never blocks the check-out. 0 = disabled.') }}</small>
                        </div>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Kiosk enrollment PIN') }} <small class="text-muted">({{ __('4-8 digits; empty = enrollment mode disabled') }})</small></label>
                        <input name="kiosk_enroll_pin" value="{{ old('kiosk_enroll_pin', $setting->kiosk_enroll_pin) }}" class="form-control @error('kiosk_enroll_pin') is-invalid @enderror" maxlength="8" pattern="[0-9]{4,8}" autocomplete="off">
                        @error('kiosk_enroll_pin')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="text-muted">{{ __('With this PIN, a supervisor unlocks the self-enrollment mode on the kiosk: the employee types their document, accepts the consent and captures their face — no admin needed per person.') }}</small>
                    </div>
                    {{-- Facial recognition (kiosk) --}}
                    <hr>
                    <h6 class="font-weight-bold"><i class="fas fa-user-check"></i> {{ __('Facial recognition (kiosk)') }}</h6>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" name="kiosk_require_face" value="1" class="custom-control-input" id="kioskRequireFace" @checked(old('kiosk_require_face', $setting->kiosk_require_face))>
                        <label class="custom-control-label" for="kioskRequireFace">{{ __('Require a detected face before marking — no face, no mark and no photo') }}</label>
                    </div>
                    <small class="text-muted d-block mb-2">{{ __('Recommended ON: the kiosk will not mark (nor save a photo) unless it actually sees a face in front of the camera. The person is asked to show their face and try again.') }}</small>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" name="kiosk_liveness" value="1" class="custom-control-input" id="kioskLiveness" @checked(old('kiosk_liveness', $setting->kiosk_liveness))>
                        <label class="custom-control-label" for="kioskLiveness">{{ __('Require a blink (liveness) — blocks marking with a photo') }}</label>
                    </div>
                    <div class="custom-control custom-switch mb-2">
                        <input type="checkbox" name="kiosk_fast_mode" value="1" class="custom-control-input" id="kioskFast" @checked(old('kiosk_fast_mode', $setting->kiosk_fast_mode))>
                        <label class="custom-control-label" for="kioskFast">{{ __('Fast mode (auto-scan): recognize the face without typing the document') }}</label>
                    </div>
                    <small class="text-muted d-block mb-2">{{ __('Default (fast mode OFF): the employee types their document and the camera confirms it is really them (1:1) — the most reliable, no confusion between similar faces. Fast mode ON: the camera recognizes anyone standing in front (1:N) — faster but can confuse look-alikes.') }}</small>
                    <div class="form-group">
                        <label>{{ __('Match strictness') }} <small class="text-muted">({{ __('lower = stricter; 0.50 recommended') }})</small></label>
                        <input type="number" step="0.01" min="0.35" max="0.65" name="kiosk_face_threshold" value="{{ old('kiosk_face_threshold', $setting->kiosk_face_threshold ?? 0.50) }}" class="form-control @error('kiosk_face_threshold') is-invalid @enderror" style="max-width:140px">
                        @error('kiosk_face_threshold')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('Logo') }} <small class="text-muted">({{ __('PNG/JPG, max. 2MB') }})</small></label>
                        <input type="file" name="logo" class="form-control-file @error('logo') is-invalid @enderror" accept="image/png,image/jpeg">
                        @error('logo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        @if($setting->logo)
                            <div class="mt-2"><img src="{{ asset($setting->logo) }}" alt="logo" style="max-height:80px" class="border rounded p-1 bg-white"></div>
                        @endif
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save settings') }}</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-6">
        <div class="card card-warning card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-tablet-alt"></i> {{ __('Kiosk security') }}</h3></div>
            <div class="card-body">
                @if($setting->kiosk_token)
                    <p><i class="fas fa-lock text-success"></i> {{ __('The kiosk is restricted: only devices that opened the authorized link can use it.') }}</p>
                    <label class="text-sm">{{ __('Authorized kiosk link (open it once on the tablet):') }}</label>
                    <div class="input-group mb-3">
                        <input type="text" readonly class="form-control text-sm" id="kioskUrl" value="{{ route('kiosk', ['token' => $setting->kiosk_token]) }}">
                        <div class="input-group-append">
                            <button type="button" class="btn btn-outline-secondary" onclick="navigator.clipboard.writeText(document.getElementById('kioskUrl').value)" title="{{ __('Copy') }}"><i class="fas fa-copy"></i></button>
                        </div>
                    </div>
                @else
                    <div class="alert alert-warning py-2"><i class="fas fa-exclamation-triangle"></i> {{ __('The kiosk is currently open: anyone with the URL (e.g. from their phone) could open it. Generate a token to restrict it to the authorized tablet.') }}</div>
                @endif
                <form method="POST" action="{{ route('settings.kioskToken') }}" class="d-inline">
                    @csrf
                    <button class="btn btn-warning btn-sm"><i class="fas fa-sync-alt"></i> {{ $setting->kiosk_token ? __('Rotate token') : __('Generate token') }}</button>
                </form>
                @if($setting->kiosk_token)
                    <form method="POST" action="{{ route('settings.kioskToken.clear') }}" class="d-inline delete-form">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="fas fa-unlock"></i> {{ __('Remove token (open kiosk)') }}</button>
                    </form>
                @endif
                <hr>
                {{-- Device binding: lock the kiosk to one physical tablet --}}
                <h6 class="font-weight-bold"><i class="fas fa-fingerprint"></i> {{ __('Device binding (recommended)') }}</h6>
                @if($setting->kiosk_device_hash)
                    <p class="text-sm mb-2"><i class="fas fa-check-circle text-success"></i> {{ __('A device is paired. Only that tablet (which holds the device cookie) can open the kiosk; a copied URL on another device is rejected.') }}</p>
                    <form method="POST" action="{{ route('settings.kioskUnpair') }}" class="d-inline delete-form">
                        @csrf @method('DELETE')
                        <button class="btn btn-outline-danger btn-sm"><i class="fas fa-unlink"></i> {{ __('Unpair device') }}</button>
                    </form>
                @else
                    <p class="text-sm text-muted mb-2">{{ __('Bind the kiosk to a single tablet: generate a one-time code, then open the pairing page on that tablet and enter it. From then on, only that device can open the kiosk.') }}</p>
                    @if(session('pair_code'))
                        <div class="alert alert-success py-2">
                            {{ __('Pairing code (valid 15 min):') }} <span class="h4 font-weight-bold">{{ session('pair_code') }}</span><br>
                            <span class="text-sm">{{ __('On the tablet open:') }} <a href="{{ route('kiosk.pair') }}" target="_blank">{{ route('kiosk.pair') }}</a></span>
                        </div>
                    @endif
                    <form method="POST" action="{{ route('settings.kioskPair') }}" class="d-inline">
                        @csrf
                        <button class="btn btn-primary btn-sm"><i class="fas fa-key"></i> {{ __('Generate pairing code') }}</button>
                    </form>
                @endif
                <hr>
                <p class="text-muted text-sm mb-0">
                    <i class="fas fa-info-circle"></i>
                    {{ __('Every kiosk mark also records the device IP and browser, visible to auditors. For stronger control, combine this with the tablet\'s kiosk mode (pinned app) and, if possible, a Wi-Fi/IP restriction on your network.') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
