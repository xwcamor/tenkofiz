@extends('layouts.app')
@section('title', __('Sites'))
@section('header-button')
    <button class="btn btn-primary btn-sm" onclick="openSiteModal()"><i class="fas fa-plus"></i> {{ __('New site') }}</button>
@endsection
@section('content')
<div class="alert alert-info"><i class="fas fa-info-circle"></i> {!! __('Each site has its own kiosk link, token and paired tablet. A kiosk opened with a site link only recognizes and marks the employees of <strong>that site</strong>.') !!}</div>
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Address') }}</th><th>{{ __('Timezone') }}</th><th>{{ __('Employees') }}</th><th>{{ __('Status') }}</th><th>{{ __('Kiosk') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($sites as $site)
                <tr>
                    <td class="font-weight-500">{{ $site->name }}</td>
                    <td>{{ $site->address ?? '—' }}</td>
                    <td>{{ $site->timezone ?? __('Company default') }}</td>
                    <td class="text-center">{{ $site->employees_count }}</td>
                    <td><span class="badge badge-{{ $site->is_active ? 'success' : 'secondary' }}">{{ $site->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td>
                        @if($site->kiosk_device_hash)
                            <span class="badge badge-success" title="{{ __('A tablet is paired to this site') }}"><i class="fas fa-fingerprint"></i> {{ __('Device paired') }}</span>
                        @elseif($site->kiosk_token)
                            <span class="badge badge-info" title="{{ __('Restricted by token') }}"><i class="fas fa-lock"></i> {{ __('Token') }}</span>
                        @else
                            <span class="badge badge-warning" title="{{ __('Anyone with the URL can open it') }}"><i class="fas fa-lock-open"></i> {{ __('Open') }}</span>
                        @endif
                        <a href="#kiosk-site-{{ $site->id }}" class="btn btn-xs btn-outline-secondary ml-1"><i class="fas fa-cog"></i> {{ __('Manage') }}</a>
                    </td>
                    <td>
                        @php
                            $payload = json_encode(['action' => route('sites.update', $site), 'name' => $site->name, 'address' => $site->address, 'timezone' => $site->timezone, 'is_active' => $site->is_active]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-payload="{{ $payload }}" onclick="openSiteModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        <form method="POST" action="{{ route('sites.destroy', $site) }}" class="d-inline delete-form">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            @if($sites->isEmpty())
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No sites registered yet.') }}</td></tr>
            @endif
            </tbody>
        </table>
    </div>
</div>

{{-- ---------- Per-site kiosk security (token + device binding) ---------- --}}
@if($sites->isNotEmpty())
    <h5 class="mt-4 mb-2"><i class="fas fa-tablet-alt"></i> {{ __('Kiosk (tablet) security per site') }}</h5>
    <div class="row">
        @foreach($sites as $site)
            <div class="col-md-6">
                <div class="card card-warning card-outline" id="kiosk-site-{{ $site->id }}">
                    <div class="card-header"><h3 class="card-title"><i class="fas fa-map-marker-alt"></i> {{ $site->name }}</h3></div>
                    <div class="card-body">
                        {{-- Authorized link --}}
                        <label class="text-sm mb-1">{{ __('Authorized kiosk link (open it once on this site\'s tablet):') }}</label>
                        <div class="input-group input-group-sm mb-3">
                            <input type="text" class="form-control" value="{{ $site->kioskLink() }}" readonly onclick="this.select()" style="font-size:.72rem">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" title="{{ __('Copy') }}" data-link="{{ $site->kioskLink() }}" data-msg="{{ __('Link copied') }}" onclick="navigator.clipboard.writeText(this.dataset.link); Swal.fire({toast:true,position:'top-end',icon:'success',title:this.dataset.msg,showConfirmButton:false,timer:1500})"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>

                        {{-- Token --}}
                        @if($site->kiosk_token)
                            <p class="text-sm mb-1"><i class="fas fa-lock text-success"></i> {{ __('Restricted: only devices that opened the authorized link can use it.') }}</p>
                        @else
                            <div class="alert alert-warning py-2 mb-2"><i class="fas fa-exclamation-triangle"></i> {{ __('Open: anyone with the URL could open it. Generate a token to restrict it.') }}</div>
                        @endif
                        <form method="POST" action="{{ route('sites.kioskToken', $site) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-warning btn-sm"><i class="fas fa-sync-alt"></i> {{ $site->kiosk_token ? __('Rotate token') : __('Generate token') }}</button>
                        </form>
                        @if($site->kiosk_token)
                            <form method="POST" action="{{ route('sites.kioskToken.clear', $site) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm"><i class="fas fa-unlock"></i> {{ __('Remove token') }}</button>
                            </form>
                        @endif

                        <hr>
                        {{-- Device binding --}}
                        <h6 class="font-weight-bold"><i class="fas fa-fingerprint"></i> {{ __('Device binding (recommended)') }}</h6>
                        @if($site->kiosk_device_hash)
                            <p class="text-sm mb-2"><i class="fas fa-check-circle text-success"></i> {{ __('A tablet is paired. Only that device can open this site\'s kiosk; a copied URL elsewhere is rejected.') }}</p>
                            <form method="POST" action="{{ route('sites.kioskUnpair', $site) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-outline-danger btn-sm"><i class="fas fa-unlink"></i> {{ __('Unpair device') }}</button>
                            </form>
                        @else
                            <p class="text-sm text-muted mb-2">{{ __('Bind this site to one tablet: generate a one-time code, open the pairing page on that tablet and enter it.') }}</p>
                            @if(session('pair_code') && session('pair_site') == $site->id)
                                <div class="alert alert-success py-2">
                                    {{ __('Pairing code (valid 15 min):') }} <span class="h4 font-weight-bold">{{ session('pair_code') }}</span><br>
                                    <span class="text-sm">{{ __('On the tablet open:') }} <a href="{{ route('kiosk.pair') }}" target="_blank">{{ route('kiosk.pair') }}</a></span>
                                </div>
                            @endif
                            <form method="POST" action="{{ route('sites.kioskPair', $site) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-primary btn-sm"><i class="fas fa-key"></i> {{ __('Generate pairing code') }}</button>
                            </form>
                        @endif
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@endif

{{-- Create / edit modal --}}
<div class="modal fade" id="siteModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_form_action', route('sites.store')) }}" class="modal-content" id="siteForm">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}" id="siteMethod">
            <input type="hidden" name="_form_action" value="{{ old('_form_action', route('sites.store')) }}" id="siteFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-map-marker-alt"></i> {{ __('Site') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Name') }}</label>
                    <input name="name" id="siteName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required placeholder="{{ __('E.g.: Main office') }}">
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Address') }} <small class="text-muted">({{ __('optional') }})</small></label>
                    <input name="address" id="siteAddress" value="{{ old('address') }}" class="form-control @error('address') is-invalid @enderror">
                    @error('address')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Timezone') }} <small class="text-muted">({{ __('optional; overrides the company timezone for this site') }})</small></label>
                    <select name="timezone" id="siteTimezone" class="form-control @error('timezone') is-invalid @enderror">
                        <option value="">— {{ __('Company default') }} —</option>
                        @foreach(\DateTimeZone::listIdentifiers() as $tz)
                            <option value="{{ $tz }}">{{ $tz }}</option>
                        @endforeach
                    </select>
                    @error('timezone')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="custom-control custom-switch" id="siteActiveRow">
                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="siteActive" checked>
                    <label class="custom-control-label" for="siteActive">{{ __('Active') }}</label>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const SITE_STORE_URL = @json(route('sites.store'));
function openSiteModal(data = null) {
    const form = document.getElementById('siteForm');
    form.action = data ? data.action : SITE_STORE_URL;
    document.getElementById('siteFormAction').value = form.action;
    document.getElementById('siteMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('siteName').value = data ? data.name : '';
    document.getElementById('siteAddress').value = data ? (data.address || '') : '';
    document.getElementById('siteTimezone').value = data ? (data.timezone || '') : '';
    document.getElementById('siteActive').checked = data ? !!data.is_active : true;
    document.getElementById('siteActiveRow').style.display = data ? '' : 'none';
    $('#siteModal').modal('show');
}
@if($errors->any())
    $('#siteModal').modal('show');
@endif
// After generating a pairing code, jump to that site's card
@if(session('pair_site'))
    document.getElementById('kiosk-site-{{ session('pair_site') }}')?.scrollIntoView({behavior:'smooth', block:'center'});
@endif
</script>
@endpush
