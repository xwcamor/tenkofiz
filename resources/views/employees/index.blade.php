@extends('layouts.app')
@section('title', __('Employees'))
@section('header-button')
    <div>
        <button class="btn btn-default" onclick="$('#importModal').modal('show')"><i class="fas fa-file-excel text-success"></i> {{ __('Import') }}</button>
        <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New employee') }}</a>
    </div>
@endsection

@push('scripts')
<script>
/** Creates the employee's user with one click: asks for the email, initial password is their document number */
async function createUser(id, name) {
    const { value: email } = await Swal.fire({
        title: @json(__('Create user for')) + ' ' + name,
        input: 'email',
        inputPlaceholder: 'email@company.com',
        text: @json(__('It will be created with the Employee profile. The initial password will be their document number.')),
        showCancelButton: true,
        confirmButtonText: @json(__('Create user')),
        cancelButtonText: @json(__('Cancel')),
        validationMessage: @json(__('Enter a valid email'))
    });
    if (!email) return;

    const res = await fetch(`/employees/${id}/create-user`, {
        method: 'POST',
        headers: {
            'Content-Type': 'application/json',
            'Accept': 'application/json',
            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').content
        },
        body: JSON.stringify({ email })
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
</script>
@endpush

@section('content')
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Document') }}</th><th>{{ __('Last and first names') }}</th><th>{{ __('Area / Position') }}</th><th>{{ __('Schedule') }}</th><th>{{ __('User') }}</th><th>{{ __('Face') }}</th><th style="width:150px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($employees as $employee)
                <tr>
                    <td><span class="text-muted small">{{ $employee->document_type }}</span> {{ $employee->document_number }}</td>
                    <td>{{ $employee->full_name }}</td>
                    <td>{{ $employee->area?->name ?? '—' }}{{ $employee->position ? ' / '.$employee->position->name : '' }}</td>
                    <td>{{ $employee->schedule?->name ?? '—' }}</td>
                    <td>
                        @if($employee->user)
                            <span class="badge badge-primary" title="{{ $employee->user->email }}"><i class="fas fa-link"></i> {{ $employee->user->name }}</span>
                        @else
                            <button type="button" class="btn btn-xs btn-outline-success" onclick="createUser({{ $employee->id }}, @json($employee->first_name.' '.$employee->last_name))" title="{{ __('Create an access user for this employee') }}"><i class="fas fa-user-plus"></i> {{ __('Create user') }}</button>
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
                        <a href="{{ route('employees.enroll', $employee) }}" class="btn btn-sm btn-success" title="{{ __('Enroll face') }}"><i class="fas fa-camera"></i></a>
                        <a href="{{ route('employees.edit', $employee) }}" class="btn btn-sm btn-info" title="{{ __('Edit') }}"><i class="fas fa-pencil-alt"></i></a>
                        <form method="POST" action="{{ route('employees.destroy', $employee) }}" class="d-inline delete-form">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger" title="{{ __('Delete') }}"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
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
</script>
@endpush
