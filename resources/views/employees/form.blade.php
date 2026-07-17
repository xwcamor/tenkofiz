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
                            <label>{{ __('Document number') }}</label>
                            <input name="document_number" value="{{ old('document_number', $employee->document_number) }}" class="form-control @error('document_number') is-invalid @enderror" required maxlength="12" pattern="[0-9]{8,12}" title="{{ __('Digits only (8 to 12)') }}">
                            @error('document_number')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('First names') }}</label>
                            <input name="first_name" value="{{ old('first_name', $employee->first_name) }}" class="form-control" required>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('Last names') }}</label>
                            <input name="last_name" value="{{ old('last_name', $employee->last_name) }}" class="form-control" required>
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
                                    <button type="button" class="btn btn-outline-primary" onclick="addCatalogItem('{{ route('areas.store') }}', 'areaSelect', @json(__('area')))" title="{{ __('Add new area') }}"><i class="fas fa-plus"></i></button>
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
                                    <button type="button" class="btn btn-outline-primary" onclick="addCatalogItem('{{ route('positions.store') }}', 'positionSelect', @json(__('position')))" title="{{ __('Add new position') }}"><i class="fas fa-plus"></i></button>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-4 form-group">
                            <label>{{ __('Hire date') }}</label>
                            <input type="date" name="hire_date" value="{{ old('hire_date', $employee->hire_date?->toDateString()) }}" class="form-control">
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('Assigned schedule') }} <span class="text-danger">*</span></label>
                            <select name="schedule_id" class="form-control @error('schedule_id') is-invalid @enderror" required>
                                <option value="">— {{ __('Select a schedule') }} —</option>
                                @foreach($schedules as $schedule)
                                    <option value="{{ $schedule->id }}" @selected(old('schedule_id', $employee->schedule_id) == $schedule->id)>{{ $schedule->name }} ({{ substr($schedule->start_time, 0, 5) }}–{{ substr($schedule->end_time, 0, 5) }})</option>
                                @endforeach
                            </select>
                            @error('schedule_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                        <div class="col-md-6 form-group">
                            <label>{{ __('System user') }} <small class="text-muted">({{ __('so they can view their attendance') }})</small></label>
                            <select name="user_id" class="form-control @error('user_id') is-invalid @enderror">
                                <option value="">— {{ __('No user') }} —</option>
                                @foreach($users as $user)
                                    <option value="{{ $user->id }}" @selected(old('user_id', $employee->user_id) == $user->id)>{{ $user->name }} ({{ $user->email }})</option>
                                @endforeach
                            </select>
                            @error('user_id')<span class="invalid-feedback">{{ $message }}</span>@enderror
                        </div>
                    </div>
                    <div class="custom-control custom-switch">
                        <input type="checkbox" name="is_active" value="1" class="custom-control-input" id="employeeActive" @checked(old('is_active', $employee->is_active ?? true))>
                        <label class="custom-control-label" for="employeeActive">{{ __('Active') }}</label>
                    </div>
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
        Swal.fire({ toast: true, position: 'top-end', icon: 'success', title: @json(__('Added')), showConfirmButton: false, timer: 2500 });
    } else {
        const err = await res.json();
        Swal.fire(@json(__('Attention')), err.message || @json(__('Could not save (duplicate name?).')), 'warning');
    }
}
</script>
@endpush
