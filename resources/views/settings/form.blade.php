@extends('layouts.app')
@section('title', __('System settings'))
@section('content')
<div class="row">
    <div class="col-lg-8">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-cog mr-1"></i> {{ __('System settings') }}</h3></div>
            <form method="POST" action="{{ route('settings.update') }}" enctype="multipart/form-data">
                @csrf @method('PUT')
                <div class="card-body">
                    <ul class="nav nav-tabs mb-3" id="settingsTabs" role="tablist">
                        <li class="nav-item"><a class="nav-link active" data-toggle="tab" href="#tab-company" role="tab"><i class="fas fa-building mr-1"></i> {{ __('Company') }}</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-attendance" role="tab"><i class="fas fa-clock mr-1"></i> {{ __('Attendance') }}</a></li>
                        <li class="nav-item"><a class="nav-link" data-toggle="tab" href="#tab-kiosk" role="tab"><i class="fas fa-tablet-alt mr-1"></i> {{ __('Kiosk') }}</a></li>
                    </ul>
                    <div class="tab-content">

                        {{-- ════════ TAB: Company ════════ --}}
                        <div class="tab-pane fade show active" id="tab-company" role="tabpanel">
                            <p class="text-muted mb-3" style="font-size:.82rem"><i class="fas fa-info-circle"></i> {{ __('Company data (shown in reports and sheets)') }}</p>
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
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>{{ __('Company timezone') }}@include('partials.help', ['text' => __('The server runs in UTC. Kiosk marks, tardiness rules and absence generation use this timezone. Each user can pick their own display timezone in My account.')])</label>
                                    <select name="timezone" class="form-control @error('timezone') is-invalid @enderror" required>
                                        @foreach($timezones as $tz)
                                            <option value="{{ $tz }}" @selected(old('timezone', $setting->timezone) === $tz)>{{ $tz }}</option>
                                        @endforeach
                                    </select>
                                    @error('timezone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>{{ __('Country') }}@include('partials.help', ['text' => __('Sets the default country for the "Generate year" of holidays. You can still edit the recurring holiday templates per country.')])</label>
                                    <select name="country" class="form-control @error('country') is-invalid @enderror" required>
                                        @foreach(\App\Models\HolidayTemplate::COUNTRIES as $code => $label)
                                            <option value="{{ $code }}" @selected(old('country', $setting->country ?? 'PE') === $code)>{{ $label }}</option>
                                        @endforeach
                                    </select>
                                    @error('country')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <div class="row">
                                <div class="col-md-6 form-group">
                                    <label>{{ __('Default language') }}@include('partials.help', ['text' => __('Applies to everyone in the workspace (and its kiosks) unless a user picks their own language with the toggle.')])</label>
                                    <select name="locale" class="form-control @error('locale') is-invalid @enderror" required>
                                        <option value="es" @selected(old('locale', $setting->locale ?? 'es') === 'es')>Español</option>
                                        <option value="en" @selected(old('locale', $setting->locale ?? 'es') === 'en')>English</option>
                                    </select>
                                    @error('locale')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                </div>
                                <div class="col-md-6 form-group">
                                    <label>{{ __('Logo') }} <small class="text-muted">({{ __('PNG/JPG, max. 2MB') }})</small></label>
                                    <input type="file" name="logo" class="form-control-file @error('logo') is-invalid @enderror" accept="image/png,image/jpeg">
                                    @error('logo')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                                    @if($setting->logo)
                                        <div class="mt-2"><img src="{{ asset($setting->logo) }}" alt="logo" style="max-height:64px" class="border rounded p-1 bg-white"></div>
                                    @endif
                                </div>
                            </div>
                        </div>

                        {{-- ════════ TAB: Attendance ════════ --}}
                        <div class="tab-pane fade" id="tab-attendance" role="tabpanel">
                            <div class="form-group">
                                <label>{{ __('Payroll cut-off day') }}@include('partials.help', ['text' => __('E.g. 19: worked days are counted from the 20th of one month to the 19th of the next. Attendance and Reports open on the current cut-off period by default.')])</label>
                                <select name="cutoff_day" class="form-control @error('cutoff_day') is-invalid @enderror" style="max-width:320px">
                                    <option value="">{{ __('Calendar month (1st to last day)') }}</option>
                                    @for($day = 1; $day <= 28; $day++)
                                        <option value="{{ $day }}" @selected(old('cutoff_day', $setting->cutoff_day) == $day)>{{ $day }}</option>
                                    @endfor
                                </select>
                                @error('cutoff_day')<span class="invalid-feedback">{{ $message }}</span>@enderror
                                @php [$periodStart, $periodEnd] = current_period(); @endphp
                                <small class="text-muted d-block mt-1"><strong>{{ __('Current period') }}:</strong> {{ $periodStart->format('d/m/Y') }} – {{ $periodEnd->format('d/m/Y') }}</small>
                            </div>
                            <div class="form-group">
                                <label>{{ __('Early check-in window') }} <small class="text-muted">({{ __('minutes; 0 = no limit') }})</small>@include('partials.help', ['text' => __('How many minutes before their scheduled start an employee may check in (default 15). E.g. 15: someone on an 08:00 shift can mark from 07:45; earlier marks are rejected, so nobody clocks in hours early. 0 = mark at any time (no restriction).')])</label>
                                <input type="number" name="early_check_in_minutes" min="0" max="720" value="{{ old('early_check_in_minutes', $setting->early_check_in_minutes ?? 15) }}" class="form-control @error('early_check_in_minutes') is-invalid @enderror" style="max-width:160px">
                                @error('early_check_in_minutes')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                            <hr>
                            {{-- Count worked hours within the schedule only --}}
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" name="clamp_worked_hours" value="1" class="custom-control-input" id="clampWorkedHours" @checked(old('clamp_worked_hours', $setting->clamp_worked_hours))>
                                <label class="custom-control-label" for="clampWorkedHours">{{ __('Count worked hours within the schedule only (recommended)') }}</label>
                                @include('partials.help', ['text' => __('ON: paid hours are capped to the shift — from the scheduled start (even if they marked earlier) to the scheduled end (even if they marked later). Punctuality is still judged on the real mark. This prevents "marking at 6am to rack up hours". OFF: hours are the raw check-out minus check-in.')])
                            </div>
                            {{-- Break control (multiple marks per day) --}}
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" name="kiosk_breaks_enabled" value="1" class="custom-control-input" id="kioskBreaksEnabled" @checked(old('kiosk_breaks_enabled', $setting->kiosk_breaks_enabled)) onchange="document.getElementById('breakOptions').style.display=this.checked?'':'none'">
                                <label class="custom-control-label" for="kioskBreaksEnabled">{{ __('Control breaks (allow leaving for break and back)') }}</label>
                                @include('partials.help', ['text' => __('OFF (default): one check-in and one check-out per day. ON: the kiosk asks "break or check-out?" on the second mark; the break time is subtracted from worked hours.')])
                            </div>
                            <div id="breakOptions" style="{{ old('kiosk_breaks_enabled', $setting->kiosk_breaks_enabled) ? '' : 'display:none' }}">
                                <div class="custom-control custom-switch mb-2 ml-3">
                                    <input type="checkbox" name="break_required" value="1" class="custom-control-input" id="breakRequired" @checked(old('break_required', $setting->break_required))>
                                    <label class="custom-control-label" for="breakRequired">{{ __('Break is mandatory (the second mark is always the break)') }}</label>
                                </div>
                                <div class="form-group ml-3">
                                    <label>{{ __('Break limit (minutes)') }}@include('partials.help', ['text' => __('If the break goes over this, the report just flags "time exceeded" — it never penalizes, only for analysis. 0 = no limit.')])</label>
                                    <input type="number" name="break_limit_minutes" min="0" max="480" value="{{ old('break_limit_minutes', $setting->break_limit_minutes ?? 60) }}" class="form-control @error('break_limit_minutes') is-invalid @enderror" style="max-width:160px">
                                    @error('break_limit_minutes')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                                </div>
                            </div>
                            <hr>
                            {{-- Allow marking on public holidays --}}
                            <div class="custom-control custom-switch mb-3">
                                <input type="checkbox" name="allow_holiday_marking" value="1" class="custom-control-input" id="allowHolidayMarking" @checked(old('allow_holiday_marking', $setting->allow_holiday_marking))>
                                <label class="custom-control-label" for="allowHolidayMarking">{{ __('Allow attendance marking on holidays') }}</label>
                                @include('partials.help', ['text' => __('ON: employees can mark on public holidays (retail, security, healthcare…); the mark is tagged as made on a holiday. OFF (default): the kiosk tells them marking is not required on a holiday.')])
                            </div>
                            {{-- Education vertical: async / credited hours (opt-in, off by default) --}}
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" name="async_hours_enabled" value="1" class="custom-control-input" id="asyncHoursEnabled" @checked(old('async_hours_enabled', $setting->async_hours_enabled))>
                                <label class="custom-control-label" for="asyncHoursEnabled">{{ __('Enable asynchronous / credited hours (educational institutions)') }}</label>
                                @include('partials.help', ['text' => __('OFF (default): nothing changes. ON: each schedule can carry "async minutes per day" — hours done remotely that cannot be marked at the kiosk. They are counted as completed (never a deficit) and shown in reports.')])
                            </div>
                        </div>

                        {{-- ════════ TAB: Kiosk ════════ --}}
                        <div class="tab-pane fade" id="tab-kiosk" role="tabpanel">
                            {{-- Geolocation on the kiosk mark (where the punch happened) --}}
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" name="kiosk_geolocation" value="1" class="custom-control-input" id="kioskGeolocation" @checked(old('kiosk_geolocation', $setting->kiosk_geolocation))>
                                <label class="custom-control-label" for="kioskGeolocation">{{ __('Record where each mark was made (GPS geolocation)') }}</label>
                                @include('partials.help', ['text' => __('ON: when marking, the kiosk asks the browser for permission and saves the coordinates with the punch, shown as a map link in Attendances. Useful for staff who mark from another site or work in the field. If the person denies permission the mark still goes through, just without a location. OFF (default): no location is requested or stored.')])
                            </div>
                            <div class="custom-control custom-switch mb-3 ml-4" id="kioskGeoRequiredRow">
                                <input type="checkbox" name="kiosk_geolocation_required" value="1" class="custom-control-input" id="kioskGeoRequired" @checked(old('kiosk_geolocation_required', $setting->kiosk_geolocation_required))>
                                <label class="custom-control-label" for="kioskGeoRequired">{{ __('Require location to mark (no GPS, no mark)') }}</label>
                                @include('partials.help', ['text' => __('ON: the camera will not even open until the browser shares a location, and a mark without coordinates is rejected. Use this for companies whose workers mark from anywhere with the shared link and must prove where they were. OFF (default): location is recorded when available but never blocks a mark.')])
                            </div>
                            <hr>
                            <h6 class="font-weight-bold"><i class="fas fa-user-check mr-1 text-primary"></i> {{ __('Facial recognition (kiosk)') }}@include('partials.help', ['text' => __('Flow: the employee types their document on the kiosk, and only then the camera page opens to confirm it is really them (1:1). If they have no enrolled face, they can enroll right there.')])</h6>
                            <div class="custom-control custom-switch mb-2">
                                <input type="checkbox" name="kiosk_liveness" value="1" class="custom-control-input" id="kioskLiveness" @checked(old('kiosk_liveness', $setting->kiosk_liveness))>
                                <label class="custom-control-label" for="kioskLiveness">{{ __('Require a liveness challenge (random gesture) — blocks marking with a photo or a video') }}</label>
                                @include('partials.help', ['text' => __('After recognizing the face, the kiosk asks for one random gesture (turn your head left / right, or nod). A printed photo cannot move and a pre-recorded video cannot know which gesture will be asked. Without a completed gesture, the mark falls back to document + evidence photo.')])
                            </div>
                        </div>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save settings') }}</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card card-warning card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-tablet-alt"></i> {{ __('Kiosk security') }}</h3></div>
            <div class="card-body">
                <p><i class="fas fa-info-circle text-info"></i> {{ __('Kiosk security is now managed per site: each site (tablet) has its own access token and its own paired device.') }}</p>
                <p class="text-sm text-muted">{{ __('This keeps every tablet locked to its own site — one site\'s link never opens another\'s kiosk.') }}</p>
                <a href="{{ route('sites.index') }}" class="btn btn-primary btn-sm"><i class="fas fa-map-marker-alt"></i> {{ __('Go to Sites to manage kiosk links and devices') }}</a>
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

@push('scripts')
<script>
// "Require location" only applies when geolocation is on: grey it out otherwise.
(function () {
    var geo = document.getElementById('kioskGeolocation');
    var req = document.getElementById('kioskGeoRequired');
    var row = document.getElementById('kioskGeoRequiredRow');
    if (!geo || !req || !row) return;
    function sync() {
        req.disabled = !geo.checked;
        row.style.opacity = geo.checked ? '1' : '.5';
        if (!geo.checked) req.checked = false;
    }
    geo.addEventListener('change', sync);
    sync();
})();

// If a validation error lands on a hidden tab, jump to that tab so the user sees it.
(function () {
    var invalid = document.querySelector('.tab-pane .is-invalid');
    if (!invalid) return;
    var pane = invalid.closest('.tab-pane');
    if (pane) $('#settingsTabs a[href="#' + pane.id + '"]').tab('show');
})();
</script>
@endpush
