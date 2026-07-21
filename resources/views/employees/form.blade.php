@extends('layouts.app')
@section('title', $employee->exists ? __('Edit employee') : __('New employee'))
@section('content')
<div class="row">
    <div class="col-md-8">
        <div class="card card-primary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user"></i> {{ __('Employee data') }}</h3></div>
            <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
                @csrf
                @if($employee->exists) @method('PUT') @endif
                <div class="card-body">
                    <div class="row">
                        <div class="col-md-4 form-group">
                            <label>{{ __('Document') }}</label>
                            <div class="input-group">
                                <div class="input-group-prepend">
                                    <select name="document_type" id="documentType" class="form-control @error('document_type') is-invalid @enderror" style="max-width:110px">
                                        @foreach(\App\Models\Employee::DOCUMENT_TYPES as $key => $label)
                                            <option value="{{ $key }}" title="{{ __($label) }}" @selected(old('document_type', $employee->document_type ?? 'DNI') === $key)>{{ $key }}</option>
                                        @endforeach
                                    </select>
                                </div>
                                <input name="document_number" id="documentInput" value="{{ old('document_number', $employee->document_number) }}" class="form-control @error('document_number') is-invalid @enderror" required maxlength="12" autocomplete="off">
                            </div>
                            @error('document_type')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            @error('document_number')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                            <small class="text-muted d-block" id="dniLookupStatus">{{ __('8 digits. Validated against RENIEC automatically.') }}</small>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('First names') }}</label>
                            <input name="first_name" id="firstNameInput" value="{{ old('first_name', $employee->first_name) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('Last names') }}</label>
                            <input name="last_name" id="lastNameInput" value="{{ old('last_name', $employee->last_name) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('Area') }}</label>
                            <div class="input-group">
                                <select name="area_id" id="areaSelect" class="form-control @error('area_id') is-invalid @enderror">
                                    <option value="">— {{ __('No area') }} —</option>
                                    @foreach($areas as $area)
                                        <option value="{{ $area->id }}" @selected(old('area_id', $employee->area_id) == $area->id)>{{ $area->name }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" data-url="{{ route('areas.store') }}" data-select="areaSelect" data-label="{{ __('area') }}" onclick="addCatalogItem(this.dataset.url, this.dataset.select, this.dataset.label)" title="{{ __('Add new area') }}"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('Position') }}</label>
                            <div class="input-group">
                                <select name="position_id" id="positionSelect" class="form-control @error('position_id') is-invalid @enderror">
                                    <option value="">— {{ __('No position') }} —</option>
                                    @foreach($positions as $position)
                                        <option value="{{ $position->id }}" @selected(old('position_id', $employee->position_id) == $position->id)>{{ $position->name }}</option>
                                    @endforeach
                                </select>
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" data-url="{{ route('positions.store') }}" data-select="positionSelect" data-label="{{ __('position') }}" onclick="addCatalogItem(this.dataset.url, this.dataset.select, this.dataset.label)" title="{{ __('Add new position') }}"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('Hire date') }}</label>
                            <input type="date" name="hire_date" value="{{ old('hire_date', $employee->hire_date?->toDateString()) }}" class="form-control">
                        </div>
                        <div class="col-md-3 form-group">
                            <label>{{ __('Contract type') }}</label>
                            <select name="contract_type" class="form-control @error('contract_type') is-invalid @enderror">
                                @foreach(\App\Models\Employee::CONTRACT_TYPES as $key => $label)
                                    <option value="{{ $key }}" @selected(old('contract_type', $employee->contract_type ?? 'full_time') === $key)>{{ __($label) }}</option>
                                @endforeach
                            </select>
                            @error('contract_type')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-2 form-group">
                            <label>{{ __('Vacation days/year') }}</label>
                            <input type="number" name="vacation_days_per_year" value="{{ old('vacation_days_per_year', $employee->vacation_days_per_year ?? 30) }}" class="form-control @error('vacation_days_per_year') is-invalid @enderror" min="0" max="60" required>
                            @error('vacation_days_per_year')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('Site') }} <span class="text-danger">*</span></label>
                            <select name="site_id" class="form-control @error('site_id') is-invalid @enderror" required>
                                <option value="">— {{ __('Select a site') }} —</option>
                                @foreach($sites as $site)
                                    <option value="{{ $site->id }}" @selected(old('site_id', $employee->site_id) == $site->id)>{{ $site->name }}</option>
                                @endforeach
                            </select>
                            @error('site_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Assigned schedule') }} <span class="text-danger">*</span></label>
                            <select name="schedule_id" class="form-control @error('schedule_id') is-invalid @enderror" required>
                                <option value="">— {{ __('Select a schedule') }} —</option>
                                @foreach($schedules as $schedule)
                                    <option value="{{ $schedule->id }}" @selected(old('schedule_id', $employee->schedule_id) == $schedule->id)>{{ $schedule->name }} — {{ $schedule->daysSummary() }}</option>
                                @endforeach
                            </select>
                            @error('schedule_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    @if($employee->exists && $employee->user)
                        <p class="text-muted mb-2"><i class="fas fa-link"></i> {{ __('System access') }}: <strong>{{ $employee->user->email }}</strong> — {{ __('managed from the employee list (create / link / unlink user).') }}</p>
                    @endif
                    @if($employee->exists)
                        <div class="row align-items-center">
                            <div class="col-md-4">
                                <div class="custom-control custom-switch">
                                    <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="employeeActive" @checked(old('is_active', $employee->is_active))>
                                    <label class="custom-control-label" for="employeeActive">{{ __('Active') }} <small class="text-muted">({{ __('turn off to mark as terminated') }})</small></label>
                                </div>
                            </div>
                            <div class="col-md-4 form-group mb-0">
                                <label class="mb-1">{{ __('Termination date') }} <small class="text-muted">({{ __('optional') }})</small></label>
                                <input type="date" name="termination_date" value="{{ old('termination_date', $employee->termination_date?->toDateString()) }}" class="form-control form-control-sm @error('termination_date') is-invalid @enderror">
                                <small class="text-muted">{{ __('Stops counting absences after this day.') }}</small>
                                @error('termination_date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                            </div>
                        </div>
                    @endif
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
                    <a href="{{ route('employees.index') }}" class="btn btn-default">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
    @if($employee->exists)
    <div class="col-md-4">
        <div class="card card-outline card-secondary">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user-shield"></i> {{ __('Data protection') }}</h3></div>
            <div class="card-body text-sm">
                @if($employee->hasBiometricConsent())
                    <p class="mb-0"><i class="fas fa-check-circle text-success"></i> {{ __('Biometric consent accepted on :date.', ['date' => to_user_tz($employee->biometric_consent_at)->format('d/m/Y H:i')]) }}</p>
                @else
                    <p class="mb-0"><i class="fas fa-exclamation-triangle text-warning"></i> {{ __('No biometric consent recorded yet. It will be requested during face enrollment.') }}</p>
                @endif
            </div>
        </div>
    </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
// Searchable catalog selects: type to filter areas, positions, sites and schedules
$(function () {
    $('#areaSelect, #positionSelect, select[name="site_id"], select[name="schedule_id"]').select2({
        theme: 'bootstrap4',
        width: '100%',
        language: @json(app()->getLocale()),
    });
});
</script>
<script>
/**
 * Optional RENIEC autofill (Decolecta API), no button needed:
 * - Fires automatically ONCE when the type is DNI and the 8th digit is typed.
 * - Never repeats a lookup for the same number (plus the server caches 1 day).
 * - Silent on failure: a small hint invites typing the names manually.
 * - Never overwrites names the user already typed.
 */
(function () {
    const typeSelect = document.getElementById('documentType');
    const documentInput = document.getElementById('documentInput');
    const firstName = document.getElementById('firstNameInput');
    const lastName = document.getElementById('lastNameInput');
    const status = document.getElementById('dniLookupStatus');

    // Per-type rules: length, allowed characters and a short hint
    const DOC_RULES = {
        DNI: { pattern: '[0-9]{8}', maxLength: 8, allowed: /[^0-9]/g, hint: @json(__('8 digits. Validated against RENIEC automatically.')) },
        CE: { pattern: '[0-9A-Z]{9,12}', maxLength: 12, allowed: /[^0-9A-Za-z]/g, hint: @json(__('9 to 12 characters.')) },
        PASSPORT: { pattern: '[0-9A-Z]{6,12}', maxLength: 12, allowed: /[^0-9A-Za-z]/g, hint: @json(__('6 to 12 characters.')) },
    };

    let lastLookedUp = null;
    let debounceTimer = null;

    function currentRule() {
        return DOC_RULES[typeSelect.value] || DOC_RULES.DNI;
    }

    function applyType() {
        const rule = currentRule();
        documentInput.pattern = rule.pattern;
        documentInput.maxLength = rule.maxLength;
        cleanDocument();
        setStatus('muted', rule.hint);
    }

    // Live cleanup: strips characters the selected type does not allow, uppercases, caps the length
    function cleanDocument() {
        const rule = currentRule();
        const cleaned = documentInput.value.replace(rule.allowed, '').toUpperCase().slice(0, rule.maxLength);
        if (cleaned !== documentInput.value) documentInput.value = cleaned;
    }

    function setStatus(kind, text) {
        status.className = 'd-block ' + (kind === 'ok' ? 'text-success' : kind === 'warn' ? 'text-warning' : 'text-muted');
        status.innerHTML = text;
    }

    async function maybeLookup() {
        const dni = documentInput.value.trim();

        if (typeSelect.value !== 'DNI' || !/^\d{8}$/.test(dni) || dni === lastLookedUp) return;
        lastLookedUp = dni; // one lookup per number, even if the request fails

        setStatus('muted', '<span class="spinner-border spinner-border-sm mr-1"></span> ' + @json(__('Validating...')));

        try {
            const res = await fetch(`{{ url('dni-lookup') }}/${dni}`, { headers: { 'Accept': 'application/json' } });
            const data = await res.json();

            if (res.ok && data.ok) {
                // Only fill fields the user has not typed in
                if (!firstName.value.trim()) firstName.value = data.first_name;
                if (!lastName.value.trim()) lastName.value = data.last_name;
                setStatus('ok', '<i class="fas fa-check-circle"></i> ' + @json(__('Validated in RENIEC:')) + ' ' + data.last_name + ', ' + data.first_name);
            } else {
                setStatus('warn', '<i class="fas fa-info-circle"></i> ' + (data.message || @json(__('Not found: type the names manually.'))));
            }
        } catch (e) {
            setStatus('warn', '<i class="fas fa-info-circle"></i> ' + @json(__('Validation unavailable: type the names manually.')));
        }
    }

    documentInput.addEventListener('input', function () {
        cleanDocument();
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(maybeLookup, 500); // waits half a second after the last keystroke
    });
    documentInput.addEventListener('blur', maybeLookup);
    typeSelect.addEventListener('change', applyType);
    applyType();
})();

/** Quick creation of areas and positions without leaving the form (SweetAlert2 + AJAX) */
async function addCatalogItem(url, selectId, label) {
    const { value: name } = await Swal.fire({
        title: @json(__('New')) + ' ' + label,
        input: 'text',
        inputPlaceholder: @json(__('Name')),
        showCancelButton: true,
        confirmButtonText: @json(__('Save')),
        cancelButtonText: @json(__('Cancel')),
        inputValidator: value => !value && @json(__('Enter a name'))
    });
    if (!name) return;

    const res = await fetch(url, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ name })
    });

    if (res.ok) {
        const data = await res.json();
        const select = document.getElementById(selectId);
        const option = new Option(data.name, data.id, true, true);
        select.add(option);
        $(select).trigger('change'); // refresh Select2 so the new item shows as selected
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: @json(__('Added')), showConfirmButton: false, timer: 2500 });
    } else {
        const err = await res.json();
        Swal.fire(@json(__('Attention')), err.message || @json(__('Could not save (duplicate name?).')), 'warning');
    }
}
</script>
@endpush
