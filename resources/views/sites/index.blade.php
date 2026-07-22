@extends('layouts.app')
@section('title', __('Sites'))
@section('header-button')
    <button class="btn btn-primary btn-sm" onclick="openSiteModal()"><i class="fas fa-plus"></i> {{ __('New site') }}</button>
@endsection
@section('content')
<div class="alert alert-info"><i class="fas fa-info-circle"></i> {!! __('Each site groups its own details and its kiosk security (link, token and paired tablets — one per area). A kiosk opened with a site link only recognizes and marks the employees of <strong>that site</strong>.') !!}</div>

@if($sites->isNotEmpty())
    {{-- Card layout keeps server-side ordering via this control (there are no
         column headers to click here). --}}
    <form method="GET" class="form-inline justify-content-end mb-2">
        <label class="text-sm text-muted mr-2 mb-0">{{ __('Sort by') }}:</label>
        <select name="sort" class="form-control form-control-sm mr-1" onchange="this.form.submit()">
            <option value="name" @selected($sort === 'name')>{{ __('Name') }}</option>
            <option value="employees" @selected($sort === 'employees')>{{ __('Employees') }}</option>
            <option value="status" @selected($sort === 'status')>{{ __('Status') }}</option>
        </select>
        <select name="dir" class="form-control form-control-sm" onchange="this.form.submit()">
            <option value="asc" @selected($dir === 'asc')>{{ __('Ascending') }}</option>
            <option value="desc" @selected($dir === 'desc')>{{ __('Descending') }}</option>
        </select>
    </form>
@endif

@if($sites->isEmpty())
    <div class="card card-primary card-outline">
        <div class="card-body text-center text-muted py-5">
            <i class="fas fa-map-marker-alt fa-2x mb-2 d-block"></i>
            {{ __('No sites registered yet.') }}
            <div class="mt-3"><button class="btn btn-primary btn-sm" onclick="openSiteModal()"><i class="fas fa-plus"></i> {{ __('New site') }}</button></div>
        </div>
    </div>
@else
<div class="row">
    @foreach($sites as $site)
        <div class="col-xl-6">
            <div class="card card-outline {{ $site->is_active ? 'card-primary' : 'card-secondary' }}" id="kiosk-site-{{ $site->id }}">
                {{-- Header: identity + status + edit/delete, all together --}}
                <div class="card-header d-flex align-items-center">
                    <h3 class="card-title mb-0">
                        <i class="fas fa-map-marker-alt"></i> {{ $site->name }}
                        <span class="badge badge-{{ $site->is_active ? 'success' : 'secondary' }} ml-1">{{ $site->is_active ? __('Active') : __('Inactive') }}</span>
                    </h3>
                    <div class="ml-auto">
                        @php
                            $payload = json_encode(['action' => route('sites.update', $site), 'name' => $site->name, 'address' => $site->address, 'timezone' => $site->timezone, 'is_active' => $site->is_active]);
                        @endphp
                        <button class="btn btn-sm btn-outline-info" title="{{ __('Edit') }}" data-payload="{{ $payload }}" onclick="openSiteModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        @if($sites->count() <= 1)
                            <button class="btn btn-sm btn-outline-secondary" disabled title="{{ __('At least one site must exist') }}"><i class="fas fa-lock"></i></button>
                        @else
                            <form method="POST" action="{{ route('sites.destroy', $site) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-outline-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                            </form>
                        @endif
                    </div>
                </div>
                <div class="card-body">
                    {{-- Group 1: site details --}}
                    <div class="d-flex flex-wrap mb-3" style="gap:.35rem 1.5rem">
                        <span class="text-sm"><i class="fas fa-location-dot text-muted mr-1"></i>{{ $site->address ?: __('No address') }}</span>
                        <span class="text-sm"><i class="fas fa-clock text-muted mr-1"></i>{{ $site->timezone ?: __('Company default (:tz)', ['tz' => company_timezone()]) }}</span>
                        <span class="text-sm"><i class="fas fa-users text-muted mr-1"></i>{{ $site->employees_count }} {{ $site->employees_count == 1 ? __('employee') : __('employees') }}</span>
                    </div>

                    {{-- Group 2: kiosk security (link + token + devices), boxed together --}}
                    <div class="border rounded p-3" style="background: var(--footer-bg)">
                        {{-- Section header: title + current status --}}
                        <div class="d-flex align-items-center mb-3">
                            <h6 class="font-weight-bold mb-0"><i class="fas fa-tablet-alt text-muted mr-1"></i> {{ __('Kiosk security') }}</h6>
                            <span class="ml-auto">
                                @if($site->kiosk_devices_count > 0)
                                    <span class="badge badge-success" title="{{ __('Paired tablets can open this kiosk') }}"><i class="fas fa-fingerprint"></i> {{ $site->kiosk_devices_count }} {{ $site->kiosk_devices_count == 1 ? __('tablet') : __('tablets') }}</span>
                                @elseif($site->kiosk_token)
                                    <span class="badge badge-info" title="{{ __('Restricted by token') }}"><i class="fas fa-lock"></i> {{ __('Token') }}</span>
                                @else
                                    <span class="badge badge-warning" title="{{ __('Anyone with the URL can open it') }}"><i class="fas fa-lock-open"></i> {{ __('Open') }}</span>
                                @endif
                            </span>
                        </div>

                        {{-- Authorized link --}}
                        <div class="form-group mb-3">
                            <label class="text-sm mb-1 text-muted d-block">{{ __('Authorized kiosk link (open it once on this site\'s tablet):') }}</label>
                            <div class="input-group input-group-sm mb-0">
                                <input type="text" class="form-control" value="{{ $site->kioskLink() }}" readonly onclick="this.select()" style="font-size:.72rem">
                                <div class="input-group-append">
                                    <button class="btn btn-outline-secondary" type="button" title="{{ __('Copy') }}" data-link="{{ $site->kioskLink() }}" data-msg="{{ __('Link copied') }}" onclick="navigator.clipboard.writeText(this.dataset.link); Swal.fire({toast:true,position:'top-end',icon:'success',title:this.dataset.msg,showConfirmButton:false,timer:1500})"><i class="fas fa-copy"></i></button>
                                </div>
                            </div>
                        </div>

                        {{-- Token --}}
                        <div class="pt-3 mb-3 border-top">
                            <div class="d-flex align-items-center mb-2">
                                <span class="text-sm font-weight-bold"><i class="fas fa-key text-muted mr-1"></i> {{ __('Token') }}</span>
                                @if($site->kiosk_token)
                                    <span class="badge badge-success ml-2"><i class="fas fa-lock"></i> {{ __('Restricted by token') }}</span>
                                    @include('partials.help', ['text' => __('Restricted: only devices that opened the authorized link can use it.')])
                                @endif
                            </div>
                            @if(! $site->kiosk_token)
                                <div class="alert alert-warning py-1 px-2 mb-2 text-sm"><i class="fas fa-exclamation-triangle"></i> {{ __('Open: anyone with the URL could open it. Generate a token to restrict it.') }}</div>
                            @endif
                            <div class="d-flex flex-wrap align-items-center" style="gap:.5rem">
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
                            </div>
                        </div>

                        {{-- Device binding (multi-device: a site can have several tablets, one per area) --}}
                        <div class="pt-3 border-top">
                            <h6 class="font-weight-bold text-sm mb-2">
                                <i class="fas fa-fingerprint text-muted mr-1"></i> {{ __('Device binding (recommended)') }}
                                @if($site->kioskDevices->isNotEmpty())
                                    @include('partials.help', ['text' => __('Only the paired tablets below can open this site\'s kiosk; a copied URL on any other device is rejected.')])
                                @else
                                    @include('partials.help', ['text' => __('Bind this site to its tablets: generate a one-time code, open the pairing page on each tablet and enter it. You can pair several (one per area).')])
                                @endif
                            </h6>
                            <div class="border rounded p-2 mb-3" style="background: var(--card-bg)">
                                <span class="text-sm font-weight-bold d-block mb-1">{{ __('How to pair a tablet:') }}</span>
                                <ol class="text-sm text-muted pl-3 mb-0">
                                    <li>{{ __('On this screen, press «Generate pairing code».') }}</li>
                                    <li>{{ __('On the site\'s tablet, open the pairing page (the link shown below when you generate the code).') }}</li>
                                    <li>{{ __('Type the code there before it expires (15 min). That tablet stays bound to this site.') }}</li>
                                </ol>
                            </div>
                            @if($site->kioskDevices->isNotEmpty())
                                <ul class="list-group list-group-flush mb-3">
                                    @foreach($site->kioskDevices as $device)
                                        <li class="list-group-item bg-transparent px-0 py-1 d-flex justify-content-between align-items-center">
                                            <span>
                                                <i class="fas fa-tablet-alt text-muted"></i> <strong>{{ $device->name }}</strong>
                                                <small class="text-muted d-block">
                                                    {{ $device->last_seen_at ? __('Last seen :when', ['when' => $device->last_seen_at->diffForHumans()]) : __('Not used yet') }}
                                                </small>
                                            </span>
                                            <form method="POST" action="{{ route('sites.kioskRevoke', [$site, $device]) }}" class="d-inline delete-form">
                                                @csrf @method('DELETE')
                                                <button class="btn btn-outline-danger btn-xs" title="{{ __('Revoke this tablet') }}"><i class="fas fa-unlink"></i> {{ __('Revoke') }}</button>
                                            </form>
                                        </li>
                                    @endforeach
                                </ul>
                            @endif
                            @if(session('pair_code') && session('pair_site') == $site->id)
                                <div class="alert alert-success py-2 mb-3">
                                    {{ __('Pairing code (valid 15 min):') }} <span class="h4 font-weight-bold">{{ session('pair_code') }}</span><br>
                                    <span class="text-sm">{{ __('On the tablet open:') }} <a href="{{ route('kiosk.pair') }}" target="_blank">{{ route('kiosk.pair') }}</a></span>
                                </div>
                            @endif
                            <div class="d-flex flex-wrap align-items-center" style="gap:.5rem">
                                <form method="POST" action="{{ route('sites.kioskPair', $site) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-primary btn-sm"><i class="fas fa-key"></i> {{ $site->kioskDevices->isNotEmpty() ? __('Generate code for another tablet') : __('Generate pairing code') }}</button>
                                </form>
                                @if($site->kioskDevices->isNotEmpty())
                                    @include('partials.help', ['text' => __('Add another tablet (e.g. for a different area of this site): generate a code and enter it on that tablet.')])
                                @endif
                            </div>
                        </div>
                    </div>
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
                        <option value="">— {{ __('Company default (:tz)', ['tz' => company_timezone()]) }} —</option>
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
