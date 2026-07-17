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
                        <label>{{ __('Kiosk enrollment PIN') }} <small class="text-muted">({{ __('4-8 digits; empty = enrollment mode disabled') }})</small></label>
                        <input name="kiosk_enroll_pin" value="{{ old('kiosk_enroll_pin', $setting->kiosk_enroll_pin) }}" class="form-control @error('kiosk_enroll_pin') is-invalid @enderror" maxlength="8" pattern="[0-9]{4,8}" autocomplete="off">
                        @error('kiosk_enroll_pin')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="text-muted">{{ __('With this PIN, a supervisor unlocks the self-enrollment mode on the kiosk: the employee types their document, accepts the consent and captures their face — no admin needed per person.') }}</small>
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
                <p class="text-muted text-sm mb-0">
                    <i class="fas fa-info-circle"></i>
                    {{ __('Every kiosk mark also records the device IP and browser, visible to auditors. For stronger control, combine this with the tablet\'s kiosk mode (pinned app) and, if possible, a Wi-Fi/IP restriction on your network.') }}
                </p>
            </div>
        </div>
    </div>
</div>
@endsection
