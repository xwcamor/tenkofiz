@extends('layouts.app')
@section('title', __('Workspaces'))
@section('header-button')
    <button class="btn btn-primary btn-sm" onclick="openCompanyModal()"><i class="fas fa-plus"></i> {{ __('New workspace') }}</button>
@endsection
@section('content')
<div class="alert alert-info"><i class="fas fa-layer-group"></i> {!! __('Each workspace is an isolated company: its users only ever see their own data. As super-admin you create workspaces and can <strong>enter</strong> one to administer it.') !!}</div>

<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Workspace') }}</th><th>{{ __('Tax ID') }}</th><th>{{ __('Users') }}</th><th>{{ __('Employees') }}</th><th>{{ __('Sites') }}</th><th>{{ __('Status') }}</th><th style="width:200px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($companies as $company)
                <tr>
                    <td class="font-weight-500">{{ $company->name }} @if($actingCompanyId == $company->id)<span class="badge badge-success">{{ __('current') }}</span>@endif</td>
                    <td>{{ $company->tax_id ?? '—' }}</td>
                    <td class="text-center">{{ $company->users_count }}</td>
                    <td class="text-center">{{ $company->employees_count }}</td>
                    <td class="text-center">{{ $company->sites_count }}</td>
                    <td><span class="badge badge-{{ $company->is_active ? 'success' : 'secondary' }}">{{ $company->is_active ? __('Active') : __('Inactive') }}</span></td>
                    <td>
                        <form method="POST" action="{{ route('admin.companies.enter', $company) }}" class="d-inline">
                            @csrf
                            <button class="btn btn-sm btn-success"><i class="fas fa-sign-in-alt"></i> {{ __('Enter') }}</button>
                        </form>
                        @php
                            $payload = json_encode(['action' => route('admin.companies.update', $company), 'name' => $company->name, 'tax_id' => $company->tax_id, 'is_active' => $company->is_active]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-payload="{{ $payload }}" onclick="openCompanyModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Create / edit modal --}}
<div class="modal fade" id="companyModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('admin.companies.store') }}" class="modal-content" id="companyForm">
            @csrf
            <input type="hidden" name="_method" value="POST" id="companyMethod">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-building"></i> {{ __('Workspace') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Workspace name') }}</label>
                    <input name="name" id="companyName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required placeholder="{{ __('E.g.: Empresa 1') }}">
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Tax ID') }} <small class="text-muted">({{ __('optional') }})</small></label>
                    <input name="tax_id" id="companyTaxId" value="{{ old('tax_id') }}" class="form-control @error('tax_id') is-invalid @enderror" maxlength="20">
                    @error('tax_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                {{-- Fields only for a NEW workspace (settings + first admin) --}}
                <div id="companyNewFields">
                    <div class="row">
                        <div class="col-md-6 form-group">
                            <label>{{ __('Country') }}</label>
                            <select name="country" class="form-control">
                                @foreach($countries as $code => $label)
                                    <option value="{{ $code }}" @selected(old('country') === $code)>{{ $label }}</option>
                                @endforeach
                            </select>
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Timezone') }}</label>
                            <select name="timezone" class="form-control">
                                @foreach($timezones as $tz)
                                    <option value="{{ $tz }}" @selected(old('timezone', 'America/Lima') === $tz)>{{ $tz }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                    <hr>
                    <p class="text-muted small mb-2"><i class="fas fa-user-shield"></i> {{ __('First administrator of the workspace (they manage everything inside it).') }}</p>
                    <div class="form-group">
                        <label>{{ __('Administrator name') }}</label>
                        <input name="admin_name" value="{{ old('admin_name') }}" class="form-control @error('admin_name') is-invalid @enderror">
                        @error('admin_name')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>
                    <div class="row">
                        <div class="col-md-7 form-group">
                            <label>{{ __('Administrator email') }}</label>
                            <input type="email" name="admin_email" value="{{ old('admin_email') }}" class="form-control @error('admin_email') is-invalid @enderror">
                            @error('admin_email')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-5 form-group">
                            <label>{{ __('Password') }}</label>
                            <input type="text" name="admin_password" value="{{ old('admin_password') }}" class="form-control @error('admin_password') is-invalid @enderror">
                            @error('admin_password')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                </div>
                <div class="custom-control custom-switch" id="companyActiveRow" style="display:none">
                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="companyActive" checked>
                    <label class="custom-control-label" for="companyActive">{{ __('Active') }}</label>
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
const COMPANY_STORE_URL = @json(route('admin.companies.store'));
function openCompanyModal(data = null) {
    const form = document.getElementById('companyForm');
    form.action = data ? data.action : COMPANY_STORE_URL;
    document.getElementById('companyMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('companyName').value = data ? data.name : '';
    document.getElementById('companyTaxId').value = data ? (data.tax_id || '') : '';
    // Settings + first-admin fields only apply when creating a new workspace
    document.getElementById('companyNewFields').style.display = data ? 'none' : '';
    document.getElementById('companyActiveRow').style.display = data ? '' : 'none';
    if (data) document.getElementById('companyActive').checked = !!data.is_active;
    document.querySelectorAll('#companyNewFields [required], #companyNewFields input, #companyNewFields select').forEach(el => {
        if (['admin_name', 'admin_email', 'admin_password'].includes(el.name)) el.required = !data;
    });
    $('#companyModal').modal('show');
}
@if($errors->any())
    $('#companyModal').modal('show');
@endif
</script>
@endpush
