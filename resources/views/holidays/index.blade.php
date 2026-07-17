@extends('layouts.app')
@section('title', __('Holidays'))
@section('header-button')
    <div>
        <form method="POST" action="{{ route('holidays.generate') }}" class="d-inline-flex align-items-center mr-2">
            @csrf
            <input type="number" name="year" value="{{ now()->addYear()->year }}" min="2020" max="2100" class="form-control form-control-sm mr-1" style="width:90px">
            <button class="btn btn-success btn-sm" title="{{ __('Automatically generates the national holidays of Peru, including Easter week') }}"><i class="fas fa-magic"></i> {{ __('Generate year') }}</button>
        </form>
        <button class="btn btn-primary btn-sm" onclick="openHolidayModal()"><i class="fas fa-plus"></i> {{ __('New holiday') }}</button>
    </div>
@endsection
@section('content')
<div class="alert alert-info"><i class="fas fa-info-circle"></i> {!! __('On days registered as holidays, the kiosk <strong>will not allow attendance marking</strong>.') !!}</div>
<div class="card card-primary card-outline">
    <div class="card-body">
        <table class="table table-bordered table-hover data-table">
            <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Day') }}</th><th>{{ __('Holiday name') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @foreach($holidays as $holiday)
                <tr>
                    <td data-order="{{ $holiday->date->toDateString() }}">{{ $holiday->date->format('d/m/Y') }}</td>
                    <td>{{ ucfirst($holiday->date->locale(app()->getLocale())->dayName) }}</td>
                    <td>{{ $holiday->name }}</td>
                    <td>
                        @php
                            $payload = json_encode([
                                'action' => route('holidays.update', $holiday),
                                'date' => $holiday->date->toDateString(),
                                'name' => $holiday->name,
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-payload="{{ $payload }}" onclick="openHolidayModal(JSON.parse(this.dataset.payload))"><i class="fas fa-pencil-alt"></i></button>
                        <form method="POST" action="{{ route('holidays.destroy', $holiday) }}" class="d-inline delete-form">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

{{-- Create / edit modal --}}
<div class="modal fade" id="holidayModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ old('_form_action', route('holidays.store')) }}" class="modal-content" id="holidayForm">
            @csrf
            <input type="hidden" name="_method" value="{{ old('_method', 'POST') }}" id="holidayMethod">
            <input type="hidden" name="_form_action" value="{{ old('_form_action', route('holidays.store')) }}" id="holidayFormAction">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-calendar-times"></i> {{ __('Holiday') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Date') }}</label>
                    <input type="date" name="date" id="holidayDate" value="{{ old('date') }}" class="form-control @error('date') is-invalid @enderror" required>
                    @error('date')<span class="invalid-feedback">{{ $message }}</span>@enderror
                </div>
                <div class="form-group">
                    <label>{{ __('Holiday name') }}</label>
                    <input name="name" id="holidayName" value="{{ old('name') }}" class="form-control @error('name') is-invalid @enderror" required placeholder="{{ __('E.g.: Independence Day') }}">
                    @error('name')<span class="invalid-feedback">{{ $message }}</span>@enderror
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
const HOLIDAY_STORE_URL = @json(route('holidays.store'));

function openHolidayModal(data = null) {
    const form = document.getElementById('holidayForm');
    form.action = data ? data.action : HOLIDAY_STORE_URL;
    document.getElementById('holidayFormAction').value = form.action;
    document.getElementById('holidayMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('holidayDate').value = data ? data.date : '';
    document.getElementById('holidayName').value = data ? data.name : '';
    $('#holidayModal').modal('show');
}

@if($errors->any())
    $('#holidayModal').modal('show');
@endif
</script>
@endpush
