@extends('layouts.app')
@section('title', $employee->exists ? __('Edit employee') : __('New employee'))
@section('content')
<div class="row">
    <div class="col-12">
        <div class="card card-primary card-outline">
            <div class="card-header"><h3 class="card-title"><i class="fas fa-user mr-1"></i> {{ __('Employee data') }}</h3></div>
            <form method="POST" action="{{ $employee->exists ? route('employees.update', $employee) : route('employees.store') }}">
                @csrf
                @if($employee->exists) @method('PUT') @endif
                <div class="card-body">

                    {{-- Data protection / biometric consent, as a slim banner up top --}}
                    @if($employee->exists)
                        <div class="alert {{ $employee->hasBiometricConsent() ? 'alert-success' : 'alert-warning' }} py-2 px-3 d-flex align-items-center mb-3">
                            <i class="fas fa-user-shield mr-2"></i>
                            <span class="text-sm">
                                @if($employee->hasBiometricConsent())
                                    {{ __('Biometric consent accepted on :date.', ['date' => to_user_tz($employee->biometric_consent_at)->format('d/m/Y H:i')]) }}
                                @else
                                    {{ __('No biometric consent recorded yet. It will be requested during face enrollment.') }}
                                @endif
                            </span>
                        </div>
                    @endif

                    {{-- ── Identity ── --}}
                    <h6 class="section-title"><i class="fas fa-id-card mr-1 text-primary"></i> {{ __('Identity') }}</h6>
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
                    </div>

                    {{-- ── Role & location ── --}}
                    <h6 class="section-title mt-2"><i class="fas fa-briefcase mr-1 text-primary"></i> {{ __('Role & location') }}</h6>
                    <div class="row">
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
                    </div>

                    {{-- ── Contract & status ── --}}
                    <h6 class="section-title mt-2"><i class="fas fa-file-contract mr-1 text-primary"></i> {{ __('Contract & status') }}</h6>
                    <div class="row">
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
                        <div class="col-md-3 form-group">
                            <label>{{ __('Vacation days/year') }}</label>
                            <input type="number" name="vacation_days_per_year" value="{{ old('vacation_days_per_year', $employee->vacation_days_per_year ?? 30) }}" class="form-control @error('vacation_days_per_year') is-invalid @enderror" min="0" max="60" required>
                            @error('vacation_days_per_year')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        @if($employee->exists)
                        <div class="col-md-3 form-group">
                            <label>{{ __('Termination date') }} <small class="text-muted">({{ __('optional') }})</small></label>
                            <input type="date" name="termination_date" value="{{ old('termination_date', $employee->termination_date?->toDateString()) }}" class="form-control @error('termination_date') is-invalid @enderror">
                            @error('termination_date')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                        </div>
                        @endif
                    </div>
                    @if($employee->exists)
                        <div class="custom-control custom-switch mb-1">
                            <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="employeeActive" @checked(old('is_active', $employee->is_active))>
                            <label class="custom-control-label" for="employeeActive">{{ __('Active') }} <small class="text-muted">({{ __('turn off to mark as terminated') }})</small></label>
                        </div>
                        <small class="text-muted d-block mb-1"><i class="fas fa-info-circle"></i> {{ __('The termination date stops counting absences after that day.') }}</small>
                    @endif

                    {{-- ── Schedule ── --}}
                    <h6 class="section-title mt-3"><i class="fas fa-clock mr-1 text-primary"></i> {{ __('Schedule') }}</h6>
                    <div class="form-group">
                        <label>{{ __('Assigned schedule') }} <span class="text-danger">*</span></label>
                        <div class="input-group">
                            <select name="schedule_id" id="scheduleSelect" class="form-control @error('schedule_id') is-invalid @enderror" required>
                                <option value="">— {{ __('Select a schedule') }} —</option>
                                @foreach($schedules as $schedule)
                                    <option value="{{ $schedule->id }}" @selected(old('schedule_id', $employee->schedule_id) == $schedule->id)>{{ $schedule->name }} — {{ $schedule->daysSummary() }}</option>
                                @endforeach
                            </select>
                            @if(auth()->user()->hasModule('schedules'))
                                <div class="input-group-append">
                                    <button type="button" class="btn btn-outline-primary" data-url="{{ route('schedules.quickStore') }}" onclick="createSchedule(this.dataset.url)" title="{{ __('Create a new schedule') }}"><i class="fas fa-plus"></i></button>
                                </div>
                            @endif
                        </div>
                        @error('schedule_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                    </div>

                    {{-- Schedule "vigencias": the assigned schedule can change over time (e.g. Jan–Jul one, Aug–Dec another) --}}
                    @php
                        $periodRows = old('schedule_periods', $employee->scheduleAssignments->map(fn ($a) => [
                            'schedule_id' => $a->schedule_id,
                            'from' => $a->effective_from?->toDateString(),
                            'to' => $a->effective_to?->toDateString(),
                        ])->all());
                    @endphp
                    <div class="card card-outline card-secondary mt-1 mb-0">
                        <div class="card-header py-2">
                            <h3 class="card-title" style="font-size:.95rem"><i class="fas fa-history mr-1"></i> {{ __('Schedules by period (optional)') }}</h3>
                        </div>
                        <div class="card-body py-2">
                            <p class="text-muted mb-2" style="font-size:.82rem">{{ __('For rotating shifts: assign a schedule for a date range (e.g. Jan–Jul one shift, Aug–Dec another). The system uses the one in force on each date for tardiness, absences and reports. Leave empty to always use the assigned schedule above.') }}</p>
                            <p class="text-muted mb-2" style="font-size:.78rem"><i class="fas fa-info-circle"></i> {{ __('Pick a shared schedule from the catalog, or click the pencil to define a personalized one (its own days/hours) just for this person — it stays out of the catalog.') }}</p>
                            @error('schedule_periods')<div class="alert alert-danger py-2 px-3 mb-2" style="font-size:.82rem"><i class="fas fa-exclamation-circle"></i> {{ $message }}</div>@enderror
                            <div id="schedulePeriods">
                                @foreach($periodRows as $i => $p)
                                    <div class="form-row align-items-end mb-2 period-row">
                                        <div class="col-md-5 form-group mb-1">
                                            <label class="mb-1 small">{{ __('Schedule') }}</label>
                                            <div class="input-group input-group-sm">
                                                <select name="schedule_periods[{{ $i }}][schedule_id]" class="form-control form-control-sm period-schedule">
                                                    @include('partials.schedule-options', ['selected' => $p['schedule_id'] ?? null])
                                                </select>
                                                <div class="input-group-append">
                                                    <button type="button" class="btn btn-outline-primary personalize-period" title="{{ __('Create a personalized schedule for this period') }}"><i class="fas fa-sliders-h"></i></button>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="col-md-3 form-group mb-1">
                                            <label class="mb-1 small">{{ __('From') }}</label>
                                            <input type="date" name="schedule_periods[{{ $i }}][from]" value="{{ $p['from'] ?? '' }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-3 form-group mb-1">
                                            <label class="mb-1 small">{{ __('To') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                            <input type="date" name="schedule_periods[{{ $i }}][to]" value="{{ $p['to'] ?? '' }}" class="form-control form-control-sm">
                                        </div>
                                        <div class="col-md-1 form-group mb-1">
                                            <button type="button" class="btn btn-sm btn-outline-danger btn-block remove-period" title="{{ __('Remove') }}"><i class="fas fa-times"></i></button>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <button type="button" class="btn btn-sm btn-outline-secondary" id="addPeriod"><i class="fas fa-plus mr-1"></i> {{ __('Add period') }}</button>
                        </div>
                    </div>
                    <template id="periodRowTpl">
                        <div class="form-row align-items-end mb-2 period-row">
                            <div class="col-md-5 form-group mb-1">
                                <label class="mb-1 small">{{ __('Schedule') }}</label>
                                <div class="input-group input-group-sm">
                                    <select name="schedule_periods[__I__][schedule_id]" class="form-control form-control-sm period-schedule">
                                        @include('partials.schedule-options', ['selected' => null])
                                    </select>
                                    <div class="input-group-append">
                                        <button type="button" class="btn btn-outline-primary personalize-period" title="{{ __('Create a personalized schedule for this period') }}"><i class="fas fa-sliders-h"></i></button>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-3 form-group mb-1">
                                <label class="mb-1 small">{{ __('From') }}</label>
                                <input type="date" name="schedule_periods[__I__][from]" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-3 form-group mb-1">
                                <label class="mb-1 small">{{ __('To') }} <span class="text-muted">({{ __('optional') }})</span></label>
                                <input type="date" name="schedule_periods[__I__][to]" class="form-control form-control-sm">
                            </div>
                            <div class="col-md-1 form-group mb-1">
                                <button type="button" class="btn btn-sm btn-outline-danger btn-block remove-period"><i class="fas fa-times"></i></button>
                            </div>
                        </div>
                    </template>

                    @if($employee->exists && $employee->user)
                        <p class="text-muted mt-3 mb-0"><i class="fas fa-link"></i> {{ __('System access') }}: <strong>{{ $employee->user->email }}</strong> — {{ __('managed from the employee list (create / link / unlink user).') }}</p>
                    @endif
                </div>
                <div class="card-footer">
                    <button class="btn btn-primary"><i class="fas fa-save"></i> {{ __('Save') }}</button>
                    <a href="{{ route('employees.index') }}" class="btn btn-default">{{ __('Cancel') }}</a>
                </div>
            </form>
        </div>
    </div>
</div>
@endsection

@push('styles')
<style>
    .section-title {
        font-size: .78rem;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: .4px;
        color: #6c757d;
        border-bottom: 1px solid #e9ecef;
        padding-bottom: .4rem;
        margin-bottom: 1rem;
    }
</style>
@endpush

@push('scripts')
<script>
// Searchable catalog selects: type to filter areas, positions, sites and schedules
$(function () {
    $('#areaSelect, #positionSelect, select[name="site_id"], select[name="schedule_id"]').select2({
        theme: 'bootstrap4',
        width: '100%',
        language: @json(app()->getLocale()),
    });

    // Schedule "vigencias": add / remove period rows
    let periodIdx = document.querySelectorAll('#schedulePeriods .period-row').length;
    const tpl = document.getElementById('periodRowTpl');
    document.getElementById('addPeriod')?.addEventListener('click', function () {
        const html = tpl.innerHTML.replace(/__I__/g, periodIdx++);
        document.getElementById('schedulePeriods').insertAdjacentHTML('beforeend', html);
    });
    document.getElementById('schedulePeriods')?.addEventListener('click', function (e) {
        const btn = e.target.closest('.remove-period');
        if (btn) btn.closest('.period-row').remove();
    });
    // Once a schedule is chosen in a period row, its "From" date becomes required
    // (a period with no start date is meaningless and used to be dropped silently).
    document.getElementById('schedulePeriods')?.addEventListener('change', function (e) {
        const sel = e.target.closest('.period-schedule');
        if (sel) syncPeriodRequired(sel);
    });
    document.querySelectorAll('#schedulePeriods .period-schedule').forEach(syncPeriodRequired);
});

// Toggle the "From" date's required state to match whether a schedule is picked.
function syncPeriodRequired(select) {
    const row = select.closest('.period-row');
    const from = row?.querySelector('input[name$="[from]"]');
    if (from) from.required = !!select.value;
}
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

// "+ New schedule" shortcut: create a fixed schedule (days + same start/end) without
// leaving the employee form. It lands in the shared catalog, so it stays reusable.
@php
    $weekdayOptions = [
        ['v' => 1, 'l' => __('Mon')], ['v' => 2, 'l' => __('Tue')], ['v' => 3, 'l' => __('Wed')],
        ['v' => 4, 'l' => __('Thu')], ['v' => 5, 'l' => __('Fri')], ['v' => 6, 'l' => __('Sat')], ['v' => 0, 'l' => __('Sun')],
    ];
@endphp
const WEEKDAYS = @json($weekdayOptions);
const ASYNC_ENABLED = @json((bool) app_setting()->async_hours_enabled);
const SCHEDULE_QUICK_URL = @json(route('schedules.quickStore'));

/**
 * Schedule editor modal. opts.personal = true creates a PERSONALIZED schedule
 * (is_shared:false) tied to this person and injects it only into opts.targetSelect;
 * otherwise a SHARED catalog schedule that lands in every schedule select.
 */
async function createSchedule(url, opts = {}) {
    const personal = !!opts.personal;
    const dayBtns = WEEKDAYS.map(d =>
        `<label class="btn btn-sm btn-outline-secondary mb-1 ${d.v >= 1 && d.v <= 5 ? 'active' : ''}" style="margin-right:.2rem">
            <input type="checkbox" value="${d.v}" ${d.v >= 1 && d.v <= 5 ? 'checked' : ''} style="display:none">${d.l}
        </label>`).join('');
    const asyncField = ASYNC_ENABLED
        ? `<div><div style="font-size:.75rem;color:#667085">${@json(__('Async min/day'))}</div><input id="scAsync" type="number" value="0" min="0" max="600" class="form-control form-control-sm" style="width:90px"></div>`
        : '';

    const { value: form } = await Swal.fire({
        title: personal ? @json(__('Personalized schedule')) : @json(__('New schedule')),
        html: `
            <input id="scName" class="swal2-input" placeholder="${@json(__('Schedule name'))}" style="width:85%">
            <div style="margin:.5rem 0 .25rem;font-size:.8rem;color:#667085">${@json(__('Working days'))}</div>
            <div id="scDays" style="display:flex;flex-wrap:wrap;justify-content:center;gap:.15rem">${dayBtns}</div>
            <div style="display:flex;gap:.5rem;justify-content:center;margin-top:.6rem;flex-wrap:wrap">
                <div><div style="font-size:.75rem;color:#667085">${@json(__('Start'))}</div><input id="scStart" type="time" value="09:00" class="form-control form-control-sm"></div>
                <div><div style="font-size:.75rem;color:#667085">${@json(__('End'))}</div><input id="scEnd" type="time" value="18:00" class="form-control form-control-sm"></div>
                <div><div style="font-size:.75rem;color:#667085">${@json(__('Tolerance (min)'))}</div><input id="scTol" type="number" value="5" min="0" max="60" class="form-control form-control-sm" style="width:80px"></div>
                ${asyncField}
            </div>
            <p class="text-muted" style="font-size:.72rem;margin:.5rem 0 0">${personal ? @json(__('Only for this person — it will not appear in the shared catalog.')) : @json(__('Same hours on every chosen day. For different hours per day or overnight shifts, use the Schedules page.'))}</p>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: @json(__('Save')),
        cancelButtonText: @json(__('Cancel')),
        didOpen: () => {
            document.querySelectorAll('#scDays label').forEach(lbl => {
                lbl.addEventListener('click', () => setTimeout(() => lbl.classList.toggle('active', lbl.querySelector('input').checked), 0));
            });
        },
        preConfirm: () => {
            const name = document.getElementById('scName').value.trim();
            const weekdays = [...document.querySelectorAll('#scDays input:checked')].map(i => parseInt(i.value, 10));
            const start = document.getElementById('scStart').value, end = document.getElementById('scEnd').value;
            if (!name) { Swal.showValidationMessage(@json(__('Enter a name'))); return false; }
            if (!weekdays.length) { Swal.showValidationMessage(@json(__('Select at least one working day.'))); return false; }
            if (!start || !end || start === end) { Swal.showValidationMessage(@json(__('Enter a valid start and end time.'))); return false; }
            return {
                name, weekdays, start, end,
                tolerance_minutes: parseInt(document.getElementById('scTol').value || '5', 10),
                async_minutes_per_day: ASYNC_ENABLED ? parseInt(document.getElementById('scAsync').value || '0', 10) : 0,
                is_shared: personal ? 0 : 1,
            };
        }
    });
    if (!form) return;

    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content },
        body: JSON.stringify(form)
    });

    if (!res.ok) {
        const err = await res.json().catch(() => ({}));
        Swal.fire(@json(__('Attention')), err.message || (err.errors && Object.values(err.errors)[0][0]) || @json(__('Could not save (duplicate name?).')), 'warning');
        return;
    }
    const data = await res.json();
    // Show "name — days · hours" in the option, like the seeded options do
    const label = data.summary ? (data.name + ' — ' + data.summary) : data.name;

    if (personal && opts.targetSelect) {
        // Inject into THIS row only, under a "Personalized" optgroup, and select it
        let grp = opts.targetSelect.querySelector('optgroup.js-personal-group');
        if (!grp) { grp = document.createElement('optgroup'); grp.className = 'js-personal-group'; grp.label = @json(__('Personalized')); opts.targetSelect.appendChild(grp); }
        const opt = new Option(label, data.id, true, true);
        grp.appendChild(opt);
        opts.targetSelect.value = data.id;
        syncPeriodRequired(opts.targetSelect); // picking a schedule makes "From" required
    } else {
        // Shared: add to the base select (selected) and to every period-row select
        const base = document.getElementById('scheduleSelect');
        base.add(new Option(label, data.id, true, true));
        $(base).trigger('change');
        document.querySelectorAll('#schedulePeriods select, #periodRowTpl select').forEach(sel => {
            if (![...sel.options].some(o => o.value == data.id)) sel.add(new Option(label, data.id));
        });
    }
    Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: @json(__('Added')), showConfirmButton: false, timer: 2500 });
}

// "Personalize" pencil on each period row → create a personalized schedule for that row
document.getElementById('schedulePeriods')?.addEventListener('click', function (e) {
    const btn = e.target.closest('.personalize-period');
    if (!btn) return;
    const select = btn.closest('.period-row').querySelector('select.period-schedule');
    createSchedule(SCHEDULE_QUICK_URL, { personal: true, targetSelect: select });
});
</script>
@endpush
