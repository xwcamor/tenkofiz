@extends('layouts.app')
@section('title', __('Employees'))
@section('header-button')
    <a href="{{ route('employees.create') }}" class="btn btn-primary"><i class="fas fa-plus"></i> {{ __('New employee') }}</a>
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
                    <td>{{ $employee->document_number }}</td>
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
@endsection
