@extends('layouts.app')
@section('title', __('Workspaces'))
@section('header-button')
    <button class="btn btn-primary btn-sm" onclick="openCompanyModal()"><i class="fas fa-plus"></i> {{ __('New workspace') }}</button>
@endsection
@section('content')
<div class="alert alert-info"><i class="fas fa-layer-group"></i> {!! __('Each workspace is an isolated company: its users only ever see their own data. As super-admin you create workspaces and can <strong>enter</strong> one to administer it.') !!}</div>

<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table" data-server-sort>
            <thead><tr>
                @include('partials.th-sort', ['key' => 'name', 'label' => __('Workspace')])
                <th>{{ __('Plan') }}</th>
                @include('partials.th-sort', ['key' => 'users', 'label' => __('Users')])
                @include('partials.th-sort', ['key' => 'employees', 'label' => __('Employees')])
                @include('partials.th-sort', ['key' => 'sites', 'label' => __('Sites')])
                <th>{{ __('Status') }}</th>
                <th style="width:280px">{{ __('Actions') }}</th>
            </tr></thead>
            <tbody>
            @forelse($companies as $company)
                <tr class="{{ $company->trashed() ? 'table-danger' : (!$company->is_active ? 'table-warning' : '') }}">
                    <td class="font-weight-500">
                        {{ $company->name }}
                        @if($actingCompanyId == $company->id)<span class="badge badge-success">{{ __('current') }}</span>@endif
                        @if($company->tax_id)<br><small class="text-muted">{{ $company->tax_id }}</small>@endif
                    </td>
                    <td>
                        @if($company->modules === null)
                            <span class="badge badge-primary">{{ __('All modules') }}</span>
                        @else
                            <span class="badge badge-info">{{ count($company->modules) }} {{ __('module(s)') }}</span>
                        @endif
                        <br><small class="text-muted">
                            {{ $company->max_employees ? __(':max empl. max', ['max' => $company->max_employees]) : __('Unlimited empl.') }}
                            · {{ $company->max_sites ? __(':max sites max', ['max' => $company->max_sites]) : __('Unlimited sites') }}
                        </small>
                    </td>
                    <td class="text-center">{{ $company->users_count }}</td>
                    <td class="text-center">{{ $company->employees_count }}</td>
                    <td class="text-center">{{ $company->sites_count }}</td>
                    <td>
                        @if($company->trashed())
                            <span class="badge badge-danger" title="{{ $company->delete_reason }}">{{ __('Deleted') }}</span>
                        @elseif(!$company->is_active)
                            <span class="badge badge-warning" title="{{ $company->suspended_reason }}">{{ __('Suspended') }}</span>
                            @if($company->suspended_reason)<br><small class="text-muted">{{ $company->suspended_reason }}</small>@endif
                        @else
                            <span class="badge badge-success">{{ __('Active') }}</span>
                        @endif
                    </td>
                    <td>
                        @if($company->trashed())
                            <form method="POST" action="{{ route('admin.companies.restore', $company->id) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="{{ __('Restore') }}"><i class="fas fa-trash-restore"></i> {{ __('Restore') }}</button>
                            </form>
                        @else
                            <form method="POST" action="{{ route('admin.companies.enter', $company) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="{{ __('Enter') }}"><i class="fas fa-sign-in-alt"></i></button>
                            </form>
                            @php
                                $payload = json_encode(['action' => route('admin.companies.update', $company), 'name' => $company->name, 'tax_id' => $company->tax_id, 'is_active' => $company->is_active]);
                                $planPayload = json_encode(['action' => route('admin.companies.plan', $company), 'name' => $company->name, 'modules' => $company->modules, 'max_employees' => $company->max_employees, 'max_sites' => $company->max_sites]);
                                $recognitionPayload = json_encode(['action' => route('admin.companies.recognition', $company), 'name' => $company->name, 'threshold' => $company->recognition['threshold'], 'seconds' => $company->recognition['seconds']]);
                            @endphp
                            <button class="btn btn-sm btn-info" title="{{ __('Edit') }}" data-payload="{{ $payload }}" onclick="openCompanyModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                            <button class="btn btn-sm btn-primary" title="{{ __('Plan (modules and limits)') }}" data-payload="{{ $planPayload }}" onclick="openPlanModal(JSON.parse(this.dataset.payload))"><i class="fas fa-cubes"></i></button>
                            <button class="btn btn-sm btn-secondary" title="{{ __('Recognition calibration') }}" data-payload="{{ $recognitionPayload }}" onclick="openRecognitionModal(JSON.parse(this.dataset.payload))"><i class="fas fa-sliders-h"></i></button>
                            @if($company->is_active)
                                <button class="btn btn-sm btn-warning" title="{{ __('Suspend (e.g. non-payment)') }}" data-action="{{ route('admin.companies.suspend', $company) }}" data-name="{{ $company->name }}" onclick="openSuspendModal(this.dataset.action, this.dataset.name)"><i class="fas fa-pause"></i></button>
                            @else
                                <form method="POST" action="{{ route('admin.companies.reactivate', $company) }}" class="d-inline">
                                    @csrf
                                    <button class="btn btn-sm btn-success" title="{{ __('Reactivate') }}"><i class="fas fa-play"></i></button>
                                </form>
                            @endif
                            <form method="POST" action="{{ route('admin.companies.destroy', $company) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                            </form>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="7" class="text-center text-muted py-4">{{ __('No workspaces yet.') }}</td></tr>
            @endforelse
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
                    <div class="form-group">
                        <label>{{ __('Default language') }}</label>
                        <select name="locale" class="form-control">
                            <option value="es" @selected(old('locale', 'es') === 'es')>Español</option>
                            <option value="en" @selected(old('locale') === 'en')>English</option>
                        </select>
                        <small class="text-muted">{{ __('Applies to everyone in the workspace (and its kiosks) unless a user picks their own language with the toggle.') }}</small>
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
{{-- Suspend modal (reason required, e.g. "pending payment") --}}
<div class="modal fade" id="suspendModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="suspendForm">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-pause-circle text-warning"></i> {{ __('Suspend workspace') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="mb-2">{{ __('Its users will be signed out immediately and its kiosks will stop marking. All data is kept: reactivating restores everything.') }}</p>
                <div class="form-group mb-0">
                    <label>{{ __('Suspension reason') }} <span class="text-danger">*</span></label>
                    <input name="suspended_reason" id="suspendReason" class="form-control" maxlength="200" required placeholder="{{ __('E.g.: pending payment — invoice #123') }}">
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-warning"><i class="fas fa-pause"></i> {{ __('Suspend') }}</button>
            </div>
        </form>
    </div>
</div>

{{-- Plan modal (contracted modules + limits) --}}
<div class="modal fade" id="planModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="planForm">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-cubes"></i> {{ __('Plan') }}: <span id="planCompanyName"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="custom-control custom-switch mb-2">
                    <input type="checkbox" name="all_modules" value="1" class="custom-control-input" id="planAllModules" onchange="togglePlanModules()">
                    <label class="custom-control-label" for="planAllModules">{{ __('All modules (no restriction)') }}</label>
                </div>
                <div id="planModulesBox" class="border rounded p-2 mb-3" style="max-height:220px;overflow-y:auto">
                    @foreach($modules as $key => $label)
                        <div class="custom-control custom-checkbox">
                            <input type="checkbox" name="modules[]" value="{{ $key }}" class="custom-control-input plan-module" id="planMod_{{ $key }}">
                            <label class="custom-control-label" for="planMod_{{ $key }}">{{ __($label) }}</label>
                        </div>
                    @endforeach
                </div>
                <div class="row">
                    <div class="col-6 form-group">
                        <label>{{ __('Employee limit') }} <small class="text-muted">({{ __('empty = unlimited') }})</small></label>
                        <input type="number" name="max_employees" id="planMaxEmployees" min="1" max="100000" class="form-control">
                    </div>
                    <div class="col-6 form-group">
                        <label>{{ __('Site limit') }} <small class="text-muted">({{ __('empty = unlimited') }})</small></label>
                        <input type="number" name="max_sites" id="planMaxSites" min="1" max="1000" class="form-control">
                    </div>
                </div>
                <small class="text-muted">{{ __('The workspace admin distributes the contracted modules to their people through Profiles; they can never grant a module outside the plan.') }}</small>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save plan') }}</button>
            </div>
        </form>
    </div>
</div>
{{-- Recognition calibration modal: core engine screws, super-only by design --}}
<div class="modal fade" id="recognitionModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" class="modal-content" id="recognitionForm">
            @csrf @method('PUT')
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-sliders-h"></i> {{ __('Recognition calibration') }}: <span id="recognitionCompanyName"></span></h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <p class="text-muted small">{{ __('Core calibration of this workspace\'s kiosks. Workspace admins never see these values: a wrong threshold lets anyone pass as anyone.') }}</p>
                <div class="form-group">
                    <label>{{ __('Match strictness') }} <small class="text-muted">({{ __('lower = stricter; 0.50 recommended') }})</small></label>
                    <input type="number" step="0.01" min="0.35" max="0.65" name="kiosk_face_threshold" id="recognitionThreshold" class="form-control" style="max-width:140px" required>
                </div>
                <div class="form-group mb-0">
                    <label>{{ __('Verification time') }} <small class="text-muted">({{ __('seconds the camera tries; 10 recommended') }})</small></label>
                    <input type="number" min="5" max="60" name="kiosk_verify_seconds" id="recognitionSeconds" class="form-control" style="max-width:140px" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save calibration') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
const COMPANY_STORE_URL = @json(route('admin.companies.store'));

function openSuspendModal(action, name) {
    const form = document.getElementById('suspendForm');
    form.action = action;
    document.getElementById('suspendReason').value = '';
    $('#suspendModal').modal('show');
}

function togglePlanModules() {
    const all = document.getElementById('planAllModules').checked;
    document.getElementById('planModulesBox').style.opacity = all ? '.4' : '1';
    document.querySelectorAll('.plan-module').forEach(cb => cb.disabled = all);
}

function openPlanModal(data) {
    document.getElementById('planForm').action = data.action;
    document.getElementById('planCompanyName').textContent = data.name;
    const all = data.modules === null;
    document.getElementById('planAllModules').checked = all;
    document.querySelectorAll('.plan-module').forEach(cb => {
        cb.checked = !all && (data.modules || []).includes(cb.value);
    });
    document.getElementById('planMaxEmployees').value = data.max_employees || '';
    document.getElementById('planMaxSites').value = data.max_sites || '';
    togglePlanModules();
    $('#planModal').modal('show');
}
function openRecognitionModal(data) {
    document.getElementById('recognitionForm').action = data.action;
    document.getElementById('recognitionCompanyName').textContent = data.name;
    document.getElementById('recognitionThreshold').value = data.threshold;
    document.getElementById('recognitionSeconds').value = data.seconds;
    $('#recognitionModal').modal('show');
}
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
