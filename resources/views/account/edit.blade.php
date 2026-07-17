@extends('layouts.app')
@section('title', __('My account'))
@section('content')
<div class="row">
    <div class="col-md-5">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-key"></i> {{ __('Change password') }}</h3></div>
            <form method="POST" action="{{ route('account.password.update') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    @if(auth()->user()->must_change_password)
                        <div class="alert alert-warning py-2"><i class="fas fa-shield-alt"></i> {{ __('For security reasons, you must change your initial password to keep using the system.') }}</div>
                    @endif
                    <div class="form-group">
                        <label>{{ __('Current password') }}</label>
                        <input type="password" name="current_password" class="form-control @error('current_password') is-invalid @enderror" required>
                        @error('current_password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('New password') }} <small class="text-muted">({{ __('minimum 8 characters') }})</small></label>
                        <input type="password" name="password" class="form-control @error('password') is-invalid @enderror" required minlength="8">
                        @error('password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="form-group">
                        <label>{{ __('Confirm new password') }}</label>
                        <input type="password" name="password_confirmation" class="form-control" required minlength="8">
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Change password') }}</button>
                </div>
            </form>
        </div>
    </div>
    <div class="col-md-5">
        <div class="card card-info">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-sliders-h"></i> {{ __('Preferences') }}</h3></div>
            <form method="POST" action="{{ route('account.preferences.update') }}">
                @csrf @method('PUT')
                <div class="card-body">
                    <div class="form-group">
                        <label>{{ __('Language') }}</label>
                        <select name="locale" class="form-control">
                            <option value="es" @selected(old('locale', auth()->user()->locale ?? app()->getLocale()) === 'es')>Español</option>
                            <option value="en" @selected(old('locale', auth()->user()->locale ?? app()->getLocale()) === 'en')>English</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>{{ __('Timezone') }}</label>
                        <select name="timezone" class="form-control @error('timezone') is-invalid @enderror">
                            <option value="">{{ __('Company default') }} ({{ company_timezone() }})</option>
                            @foreach($timezones as $tz)
                                <option value="{{ $tz }}" @selected(old('timezone', auth()->user()->timezone) === $tz)>{{ $tz }}</option>
                            @endforeach
                        </select>
                        @error('timezone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        <small class="text-muted">{{ __('The server stores everything in UTC; dates are shown in this timezone.') }}</small>
                    </div>
                </div>
                <div class="card-footer">
                    <button class="btn btn-info"><i class="fas fa-save"></i> {{ __('Save preferences') }}</button>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection
