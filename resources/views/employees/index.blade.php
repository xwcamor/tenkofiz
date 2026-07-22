@extends('layouts.app')
@section('title', __('Employees'))
@section('header-button')
    <div>
        @if(auth()->user()->hasModule('settings'))
            @if($showDeleted)
                <a href="{{ route('employees.index') }}" class="btn btn-outline-secondary"><i class="fas fa-arrow-left"></i> {{ __('Back to list') }}</a>
            @else
                <a href="{{ route('employees.index', ['deleted' => 1]) }}" class="btn btn-outline-secondary" title="{{ __('Deleted records (only administrators see this view)') }}"><i class="fas fa-trash-restore"></i> {{ __('View deleted') }}</a>
            @endif
        @endif
        <a href="{{ route('employees.export', request()->only(['q', 'area_id', 'site_id', 'face', 'status'])) }}" class="btn btn-default" title="{{ __('Download the roster (current filters) as Excel') }}"><i class="fas fa-file-export text-success"></i> {{ __('Export') }}</a>
        <button class="btn btn-default" onclick="$('#importModal').modal('show')"><i class="fas fa-file-excel text-success"></i> {{ __('Import') }}</button>
        <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New employee') }}</a>
    </div>
@endsection

@push('scripts')
<script>
const PROFILE_OPTIONS = @json($profiles->map(fn ($p) => ['id' => $p->id, 'name' => $p->name])->values());
const AVAILABLE_USERS = @json($availableUsers->map(fn ($u) => ['id' => $u->id, 'label' => $u->name.' ('.$u->email.')'])->values());
const EMPLOYEE_PROFILE_NAME = 'Employee';

/** Creates the employee's access user: asks email + profile; initial password is their document number */
async function createUser(id, name) {
    const profileOptions = PROFILE_OPTIONS.map(profile =>
        `<option value="${profile.id}" ${profile.name === EMPLOYEE_PROFILE_NAME ? 'selected' : ''}>${profile.name}</option>`
    ).join('');

    const { value: form } = await Swal.fire({
        title: @json(__('Enable sign-in for')) + ' ' + name,
        html: `
            <input id="swalEmail" type="email" class="swal2-input" placeholder="email@company.com" style="width:85%">
            <p class="text-muted" style="font-size:.8rem;margin:.25rem 0 0">${@json(__('They will sign in with this email. The initial password will be their document number.'))}</p>
            <div style="margin-top:.75rem">
                <a href="#" id="swalAdvToggle" style="font-size:.82rem"><i class="fas fa-sliders-h mr-1"></i>${@json(__('Give more permissions (advanced)'))}</a>
                <div id="swalAdvBox" style="display:none;margin-top:.5rem">
                    <select id="swalProfile" class="swal2-select" style="width:85%;margin:0 auto">${profileOptions}</select>
                    <p class="text-muted" style="font-size:.75rem;margin:.15rem 0 0">${@json(__('Only for staff who also manage others (supervisor, HR). Regular employees keep the Employee profile.'))}</p>
                </div>
            </div>
        `,
        focusConfirm: false,
        showCancelButton: true,
        confirmButtonText: @json(__('Enable sign-in')),
        cancelButtonText: @json(__('Cancel')),
        didOpen: () => {
            const toggle = document.getElementById('swalAdvToggle');
            const box = document.getElementById('swalAdvBox');
            toggle.addEventListener('click', (e) => {
                e.preventDefault();
                box.style.display = 'block';
                toggle.style.display = 'none';
            });
        },
        preConfirm: () => {
            const email = document.getElementById('swalEmail').value.trim();
            if (!/^\S+@\S+\.\S+$/.test(email)) {
                Swal.showValidationMessage(@json(__('Enter a valid email')));
                return false;
            }
            // Default is the Employee profile; only override it if the advanced picker was opened
            const payload = { email };
            if (document.getElementById('swalAdvBox').style.display !== 'none') {
                payload.profile_id = document.getElementById('swalProfile').value;
            }
            return payload;
        }
    });
    if (!form) return;

    const res = await fetch(`{{ url('employees') }}/${id}/create-user`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify(form)
    });
    const data = await res.json();

    if (res.ok && data.ok) {
        await Swal.fire({
            icon: 'success',
            title: @json(__('User created')),
            html: `<b>${@json(__('Email'))}:</b> ${data.email}<br><b>${@json(__('Initial password'))}:</b> ${data.password}<br><small class="text-muted">${@json(__('Hand these credentials to the employee.'))}</small>`
        });
        location.reload();
    } else {
        Swal.fire(@json(__('Attention')), data.message || @json(__('The user could not be created.')), 'warning');
    }
}

/** Links an already-existing user account (e.g. a supervisor created in Users first) */
async function linkUser(id, name) {
    if (!AVAILABLE_USERS.length) {
        Swal.fire(@json(__('Attention')), @json(__('There are no unlinked users available.')), 'info');
        return;
    }

    const options = {};
    AVAILABLE_USERS.forEach(user => options[user.id] = user.label);

    const { value: userId } = await Swal.fire({
        title: @json(__('Link user to')) + ' ' + name,
        input: 'select',
        inputOptions: options,
        showCancelButton: true,
        confirmButtonText: @json(__('Link')),
        cancelButtonText: @json(__('Cancel'))
    });
    if (!userId) return;

    const form = document.createElement('form');
    form.method = 'POST';
    form.action = `{{ url('employees') }}/${id}/link-user`;
    form.innerHTML = `<input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]').content}">` +
                     `<input type="hidden" name="user_id" value="${userId}">`;
    document.body.appendChild(form);
    form.submit();
}
</script>
@endpush

@section('content')
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <div class="input-group input-group-sm mr-3" style="width:280px">
                <input type="text" name="q" value="{{ request('q') }}" class="form-control" placeholder="{{ __('Search by name or document…') }}">
                <div class="input-group-append"><span class="input-group-text"><i class="fas fa-search"></i></span></div>
            </div>
            <select name="area_id" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All areas') }}</option>
                @foreach($areas as $area)
                    <option value="{{ $area->id }}" @selected(request('area_id') == $area->id)>{{ $area->name }}</option>
                @endforeach
            </select>
            @if($sites->isNotEmpty())
            <select name="site_id" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All sites') }}</option>
                @foreach($sites as $site)
                    <option value="{{ $site->id }}" @selected(request('site_id') == $site->id)>{{ $site->name }}</option>
                @endforeach
            </select>
            @endif
            <select name="face" class="form-control form-control-sm mr-2">
                <option value="">{{ __('Face: all') }}</option>
                <option value="enrolled" @selected(request('face') === 'enrolled')>{{ __('Enrolled') }}</option>
                <option value="pending" @selected(request('face') === 'pending')>{{ __('Pending') }}</option>
            </select>
            <select name="status" class="form-control form-control-sm mr-2">
                <option value="">{{ __('All statuses') }}</option>
                <option value="active" @selected(request('status') === 'active')>{{ __('Active') }}</option>
                <option value="inactive" @selected(request('status') === 'inactive')>{{ __('Inactive') }}</option>
            </select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Filter') }}</button>
            @if(request()->hasAny(['q', 'area_id', 'face', 'status']))
                <a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary ml-1">{{ __('Clear') }}</a>
            @endif
            <span class="ml-auto text-muted small">{{ __(':total employee(s)', ['total' => $employees->total()]) }}</span>
        </form>
    </div>
    <div class="card-body">
        @if($showDeleted)
            <div class="alert alert-warning py-2"><i class="fas fa-trash-restore"></i> {{ __('You are viewing deleted records. Restoring brings them back with all their history.') }}</div>
        @endif
        @if(!$showDeleted && auth()->user()->hasModule('schedules'))
            {{-- Bulk actions: assign a schedule to several selected employees at once --}}
            <form method="POST" action="{{ route('employees.bulkSchedule') }}" id="bulkBar" class="d-none align-items-center bg-light border rounded px-3 py-2 mb-2" style="gap:.6rem;flex-wrap:wrap">
                @csrf
                <div id="bulkIds"></div>
                <span class="font-weight-bold"><i class="fas fa-check-square text-primary"></i> <span id="bulkCount">0</span> {{ __('selected') }}</span>
                <span class="text-muted">·</span>
                <label class="mb-0 small">{{ __('Assign schedule') }}:</label>
                <select name="schedule_id" class="form-control form-control-sm" style="width:auto" required>
                    <option value="">— {{ __('Select a schedule') }} —</option>
                    @foreach($schedules ?? [] as $schedule)
                        <option value="{{ $schedule->id }}">{{ $schedule->name }}</option>
                    @endforeach
                </select>
                <button class="btn btn-sm btn-primary">{{ __('Apply') }}</button>
                <button type="button" class="btn btn-sm btn-link text-muted" id="bulkClear">{{ __('Clear selection') }}</button>
            </form>
        @endif
        <table class="table table-bordered table-hover">
            <thead>
                @if($showDeleted)
                    <tr><th>{{ __('Document') }}</th><th>{{ __('Last and first names') }}</th><th>{{ __('Deleted on') }}</th><th>{{ __('Reason for deletion') }}</th><th style="width:130px">{{ __('Actions') }}</th></tr>
                @else
                    <tr>
                        <th style="width:34px" class="text-center"><input type="checkbox" id="empCheckAll" title="{{ __('Select all on this page') }}"></th>
                        @include('partials.th-sort', ['key' => 'document', 'label' => __('Document')])
                        @include('partials.th-sort', ['key' => 'name', 'label' => __('Last and first names')])
                        @include('partials.th-sort', ['key' => 'site', 'label' => __('Site')])
                        @include('partials.th-sort', ['key' => 'position', 'label' => __('Area / Position')])
                        <th>{{ __('Schedule') }}</th>
                        <th>{{ __('Web access') }}</th>
                        <th>{{ __('Face') }}</th>
                        @include('partials.th-sort', ['key' => 'status', 'label' => __('Status')])
                        <th style="width:150px">{{ __('Actions') }}</th>
                    </tr>
                @endif
            </thead>
            <tbody>
            @forelse($employees as $employee)
                @if($showDeleted)
                    <tr>
                        <td><span class="text-muted small">{{ $employee->document_type }}</span> {{ $employee->document_number }}</td>
                        <td>{{ $employee->full_name }}</td>
                        <td>{{ to_user_tz($employee->deleted_at)->format('d/m/Y H:i') }}</td>
                        <td>{{ $employee->delete_reason ?? '—' }}</td>
                        <td>
                            <form method="POST" action="{{ route('employees.restore', $employee) }}" class="d-inline">
                                @csrf
                                <button class="btn btn-sm btn-success" title="{{ __('Restore') }}"><i class="fas fa-trash-restore"></i> {{ __('Restore') }}</button>
                            </form>
                        </td>
                    </tr>
                @else
                    <tr>
                        <td class="text-center"><input type="checkbox" class="emp-check" value="{{ $employee->getRouteKey() }}"></td>
                        <td><span class="text-muted small">{{ $employee->document_type }}</span> {{ $employee->document_number }}</td>
                        <td>{{ $employee->full_name }}
                            @if($employee->contract_type === 'part_time')
                                <span class="badge badge-warning ml-1" title="{{ __('Contract type') }}"><i class="fas fa-hourglass-half"></i> {{ __('Part-time') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($employee->site)
                                <span class="badge badge-info"><i class="fas fa-map-marker-alt"></i> {{ $employee->site->name }}</span>
                            @else
                                <span class="text-muted small">—</span>
                            @endif
                        </td>
                        <td>{{ $employee->area?->name ?? '—' }}{{ $employee->position ? ' / '.$employee->position->name : '' }}</td>
                        <td>{{ $employee->schedule?->name ?? '—' }}</td>
                        <td>
                            @if($employee->user)
                                <span class="badge badge-primary"><i class="fas fa-link"></i> {{ $employee->user->name }}</span>
                                <div class="small text-muted" title="{{ __('Signs in with this email') }}"><i class="fas fa-envelope"></i> {{ $employee->user->email }}</div>
                                <form method="POST" action="{{ route('employees.unlinkUser', $employee) }}" class="d-inline delete-form">
                                    @csrf
                                    <button class="btn btn-xs btn-outline-secondary" title="{{ __('Unlink user (the account is kept)') }}"><i class="fas fa-unlink"></i></button>
                                </form>
                            @else
                                <button type="button" class="btn btn-xs btn-outline-success" data-name="{{ $employee->first_name.' '.$employee->last_name }}"
                                        onclick="createUser('{{ $employee->getRouteKey() }}', this.dataset.name)"
                                        title="{{ __('Creates a sign-in with their email (default profile: Employee; initial password: their document number)') }}"><i class="fas fa-user-plus"></i> {{ __('Enable sign-in') }}</button>
                                <button type="button" class="btn btn-xs btn-outline-primary" data-name="{{ $employee->first_name.' '.$employee->last_name }}"
                                        onclick="linkUser('{{ $employee->getRouteKey() }}', this.dataset.name)"
                                        title="{{ __('Link an existing user account') }}"><i class="fas fa-link"></i></button>
                            @endif
                        </td>
                        <td>
                            @if($employee->hasFace())
                                <span class="badge badge-success"><i class="fas fa-check"></i> {{ __('Enrolled') }}</span>
                            @else
                                <span class="badge badge-danger"><i class="fas fa-times"></i> {{ __('Pending') }}</span>
                            @endif
                        </td>
                        <td>
                            @if($employee->is_active)
                                <span class="badge badge-success">{{ __('Active') }}</span>
                            @else
                                <span class="badge badge-secondary" title="{{ __('Does not appear in the kiosk nor in automatic absences') }}">{{ __('Terminated') }}</span>
                            @endif
                        </td>
                        <td>
                            <a href="{{ route('employees.enroll', $employee) }}" class="btn btn-sm btn-success" title="{{ __('Enroll face') }}"><i class="fas fa-camera"></i></a>
                            <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-info" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
                            <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="d-inline delete-form">
                                @csrf @method('DELETE')
                                <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                            </form>
                        </td>
                    </tr>
                @endif
            @empty
                <tr><td colspan="{{ $showDeleted ? 5 : 10 }}" class="text-center py-5">
                    @if($showDeleted)
                        <span class="text-muted">{{ __('No deleted records.') }}</span>
                    @elseif(request()->hasAny(['q', 'area_id', 'site_id', 'schedule_id', 'status', 'face']))
                        <div class="text-muted">
                            <i class="fas fa-search fa-2x mb-2 d-block" style="opacity:.4"></i>
                            {{ __('No employees match the current filters.') }}
                            <div class="mt-2"><a href="{{ route('employees.index') }}" class="btn btn-sm btn-outline-secondary">{{ __('Clear filters') }}</a></div>
                        </div>
                    @else
                        <i class="fas fa-users fa-3x mb-3 d-block text-muted" style="opacity:.35"></i>
                        <h5 class="mb-1">{{ __('No employees yet') }}</h5>
                        <p class="text-muted">{{ __('Add your team to start tracking attendance — one by one or by importing an Excel.') }}</p>
                        <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New employee') }}</a>
                    @endif
                </td></tr>
            @endforelse
            </tbody>
        </table>
        <div class="d-flex justify-content-between align-items-center mt-2">
            <span class="text-muted small">
                @if($employees->total())
                    {{ __('Showing :from–:to of :total', ['from' => $employees->firstItem(), 'to' => $employees->lastItem(), 'total' => $employees->total()]) }}
                @endif
            </span>
            {{ $employees->links() }}
        </div>
    </div>
</div>

{{-- Import modal --}}
<div class="modal fade" id="importModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <form method="POST" action="{{ route('employees.import') }}" enctype="multipart/form-data" class="modal-content">
            @csrf
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-file-excel text-success"></i> {{ __('Import employees') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <ol class="text-muted pl-3 mb-3" style="font-size:.875rem">
                    <li>{{ __('Download the template: it includes dropdowns for schedule, area and position, plus an instructions sheet.') }}</li>
                    <li>{{ __('Fill one row per employee (required: document, first names, last names and schedule).') }}</li>
                    <li>{{ __('Upload the file. If any row has errors, nothing is imported and the errors are listed below so you can fix and retry.') }}</li>
                </ol>
                <a href="{{ route('employees.import.template') }}" class="btn btn-outline-primary mb-3">
                    <i class="fas fa-download"></i> {{ __('Download template') }} (.xlsx)
                </a>
                <div class="form-group">
                    <label>{{ __('File') }} <small class="text-muted">(.xlsx {{ __('or') }} .csv, {{ __('max.') }} 4MB)</small></label>
                    <input type="file" name="file" class="form-control-file @error('file') is-invalid @enderror" accept=".xlsx,.xls,.csv" required>
                    @error('file')<span class="invalid-feedback d-block">{{ $message }}</span>@enderror
                </div>

                @if(session('import_errors'))
                    <div class="alert alert-danger py-2 mb-2">
                        <i class="fas fa-exclamation-circle"></i>
                        {{ __('Nothing was imported: fix the :count row(s) with errors and upload the file again.', ['count' => count(session('import_errors'))]) }}
                    </div>
                    <div class="table-responsive border rounded" style="max-height:260px;overflow-y:auto">
                        <table class="table table-sm mb-0">
                            <thead><tr><th style="width:70px">{{ __('Row') }}</th><th>{{ __('Errors') }}</th></tr></thead>
                            <tbody>
                            @foreach(session('import_errors') as $error)
                                <tr>
                                    <td class="text-center font-weight-bold">{{ $error['row'] }}</td>
                                    <td>{{ ucfirst(implode('; ', $error['messages'])) }}</td>
                                </tr>
                            @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-default" data-dismiss="modal">{{ __('Cancel') }}</button>
                <button class="btn btn-primary"><i class="fas fa-upload"></i> {{ __('Import') }}</button>
            </div>
        </form>
    </div>
</div>
@endsection

@push('scripts')
<script>
@if(session('import_errors') || $errors->has('file'))
    $('#importModal').modal('show');
@endif

// Bulk selection → schedule assignment
(function () {
    const bar = document.getElementById('bulkBar');
    if (!bar) return;
    const all = document.getElementById('empCheckAll');
    const count = document.getElementById('bulkCount');
    const ids = document.getElementById('bulkIds');

    function refresh() {
        const checked = [...document.querySelectorAll('.emp-check:checked')];
        count.textContent = checked.length;
        ids.innerHTML = checked.map(c => `<input type="hidden" name="employee_ids[]" value="${c.value}">`).join('');
        bar.classList.toggle('d-none', checked.length === 0);
        bar.classList.toggle('d-flex', checked.length > 0);
        if (all) {
            const boxes = document.querySelectorAll('.emp-check');
            all.checked = boxes.length > 0 && checked.length === boxes.length;
            all.indeterminate = checked.length > 0 && checked.length < boxes.length;
        }
    }
    document.querySelectorAll('.emp-check').forEach(c => c.addEventListener('change', refresh));
    all?.addEventListener('change', () => {
        document.querySelectorAll('.emp-check').forEach(c => { c.checked = all.checked; });
        refresh();
    });
    document.getElementById('bulkClear')?.addEventListener('click', () => {
        document.querySelectorAll('.emp-check, #empCheckAll').forEach(c => { c.checked = false; });
        refresh();
    });
    bar.addEventListener('submit', e => {
        if (!document.querySelector('.emp-check:checked')) { e.preventDefault(); return; }
        const sel = bar.querySelector('select[name="schedule_id"]');
        if (!sel.value) { e.preventDefault(); sel.focus(); }
    });
})();
</script>
@endpush
