@extends('layouts.app')
@section('title', __('Sites'))
@section('header-button')
    <button class="btn btn-primary btn-sm" onclick="openSiteModal()"><i class="fas fa-plus"></i> {{ __('New site') }}</button>
@endsection
@section('content')
@php $kioskToken = app_setting()->kiosk_token; @endphp
<div class="alert alert-info"><i class="fas fa-info-circle"></i> {!! __('Each site has its own kiosk link. A kiosk opened with a site link only recognizes and marks the employees of <strong>that site</strong>.') !!}</div>
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Name') }}</th><th>{{ __('Address') }}</th><th>{{ __('Timezone') }}</th><th>{{ __('Employees') }}</th><th>{{ __('Status') }}</th><th>{{ __('Kiosk link') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($sites as $site)
                <tr>
                    <td class="font-weight-500">{{ $site->name }}</td>
                    <td>{{ $site->address ?? '—' }}</td>
                    <td>{{ $site->timezone ?? __('Company default') }}</td>
                    <td class="text-center">{{ $site->employees_count }}</td>
                    <td><span class="badge badge-{{ $site->is_active ? 'success' : 'secondary' }}">{{ $site->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td>
                        @php $link = url('kiosk').'?'.http_build_query(array_filter(['token' => $kioskToken, 'site' => $site->id])); @endphp
                        <div class="input-group input-group-sm">
                            <input type="text" class="form-control" value="{{ $link }}" readonly onclick="this.select()" style="font-size:.72rem">
                            <div class="input-group-append">
                                <button class="btn btn-outline-secondary" type="button" title="{{ __('Copy') }}" onclick="navigator.clipboard.writeText('{{ $link }}'); Swal.fire({toast:true,position:'top-end',icon:'success',title:@json(__('Link copied')),showConfirmButton:false,timer:1500})"><i class="fas fa-copy"></i></button>
                            </div>
                        </div>
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
                <div class="custom-control custom-switch">
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
    $('#siteModal').modal('show');
}
@if($errors->any())
    $('#siteModal').modal('show');
@endif
</script>
@endpush
