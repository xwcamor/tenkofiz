@extends('layouts.app')
@section('title', __('Holidays'))
@section('header-button')
    <button class="btn btn-primary btn-sm" onclick="openHolidayModal()"><i class="fas fa-plus"></i> {{ __('New holiday') }}</button>
@endsection
@section('content')
<div class="alert alert-info"><i class="fas fa-info-circle"></i> {!! __('On days registered as holidays, the kiosk <strong>will not allow attendance marking</strong>.') !!}</div>

{{-- Generate a year's holidays from the country's recurring templates --}}
<div class="card card-success card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-magic"></i> {{ __('Generate a year from templates') }}</h3></div>
    <div class="card-body">
        <form method="POST" action="{{ route('holidays.generate') }}" class="form-inline">
            @csrf
            <label class="mr-2">{{ __('Country') }}</label>
            <select name="country" class="form-control form-control-sm mr-2">
                @foreach($countries as $code => $label)
                    <option value="{{ $code }}" @selected($country === $code)>{{ $label }}</option>
                @endforeach
            </select>
            <label class="mr-2">{{ __('Year') }}</label>
            <input type="number" name="year" value="{{ now()->addYear()->year }}" min="2020" max="2100" class="form-control form-control-sm mr-2" style="width:100px">
            <button class="btn btn-success btn-sm"><i class="fas fa-magic"></i> {{ __('Generate year') }}</button>
        </form>
        <small class="text-muted d-block mt-2">{{ __('Uses the recurring templates below for the chosen country. Existing dates are never duplicated. Edit the templates to match your own country.') }}</small>
    </div>
</div>

{{-- Recurring templates per country (customizable) --}}
<div class="card card-outline card-secondary">
    <div class="card-header d-flex justify-content-between align-items-center flex-wrap">
        <h3 class="card-title"><i class="fas fa-repeat"></i> {{ __('Recurring holidays (template)') }}</h3>
        <div class="d-flex align-items-center">
            <form method="GET" action="{{ route('holidays.index') }}" class="form-inline mr-2">
                <label class="mr-1 small">{{ __('Country') }}</label>
                <select name="country" class="form-control form-control-sm" onchange="this.form.submit()">
                    @foreach($countries as $code => $label)
                        <option value="{{ $code }}" @selected($country === $code)>{{ $label }}</option>
                    @endforeach
                </select>
            </form>
            <button class="btn btn-outline-secondary btn-sm mr-1" onclick="openTemplateModal()"><i class="fas fa-plus"></i> {{ __('Add') }}</button>
            <form method="POST" action="{{ route('holidays.templates.restore') }}" class="d-inline">
                @csrf
                <input type="hidden" name="country" value="{{ $country }}">
                <button class="btn btn-outline-info btn-sm" title="{{ __('Adds the built-in defaults for this country') }}"><i class="fas fa-undo"></i> {{ __('Restore defaults') }}</button>
            </form>
        </div>
    </div>
    <div class="card-body">
        <table class="table table-sm table-bordered mb-0">
            <thead><tr><th style="width:120px">{{ __('Rule') }}</th><th>{{ __('Holiday name') }}</th><th style="width:110px">{{ __('Actions') }}</th></tr></thead>
            <tbody>
            @forelse($templates as $template)
                <tr>
                    <td><span class="badge badge-light border">{{ $template->ruleLabel() }}</span></td>
                    <td>{{ $template->name }}</td>
                    <td>
                        @php
                            $tpl = json_encode([
                                'action' => route('holidays.templates.update', $template),
                                'country' => $template->country,
                                'kind' => $template->easter_offset !== null ? 'easter' : 'fixed',
                                'month' => $template->month,
                                'day' => $template->day,
                                'easter_offset' => $template->easter_offset,
                                'name' => $template->name,
                            ]);
                        @endphp
                        <button class="btn btn-sm btn-info" data-tpl="{{ $tpl }}" onclick="openTemplateModal(JSON.parse(this.dataset.tpl))"><i class="fas fa-pencil-alt"></i></button>
                        <form method="POST" action="{{ route('holidays.templates.destroy', $template) }}" class="d-inline delete-form">
                            @csrf @method('DELETE')
                            <button class="btn btn-sm btn-danger"><i class="fas fa-trash"></i></button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td colspan="3" class="text-center text-muted py-3">{{ __('No recurring holidays for this country. Use "Restore defaults" or add your own.') }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- Concrete holidays already generated --}}
<div class="card card-primary card-outline">
    <div class="card-header"><h3 class="card-title"><i class="fas fa-calendar-times"></i> {{ __('Registered holidays') }}</h3></div>
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

{{-- Create / edit holiday modal --}}
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

{{-- Create / edit template modal --}}
<div class="modal fade" id="templateModal" tabindex="-1">
    <div class="modal-dialog">
        <form method="POST" action="{{ route('holidays.templates.store') }}" class="modal-content" id="templateForm">
            @csrf
            <input type="hidden" name="_method" value="POST" id="templateMethod">
            <input type="hidden" name="country" id="templateCountry" value="{{ $country }}">
            <div class="modal-header">
                <h5 class="modal-title"><i class="fas fa-repeat"></i> {{ __('Recurring holiday') }}</h5>
                <button type="button" class="close" data-dismiss="modal">&times;</button>
            </div>
            <div class="modal-body">
                <div class="form-group">
                    <label>{{ __('Holiday name') }}</label>
                    <input name="name" id="templateName" class="form-control" required placeholder="{{ __('E.g.: Independence Day') }}">
                </div>
                <div class="form-group">
                    <label>{{ __('Type') }}</label>
                    <select name="kind" id="templateKind" class="form-control" onchange="templateKindChanged()">
                        <option value="fixed">{{ __('Fixed date (same every year)') }}</option>
                        <option value="easter">{{ __('Relative to Easter (Holy Week)') }}</option>
                    </select>
                </div>
                <div class="form-row" id="templateFixedRow">
                    <div class="col form-group">
                        <label>{{ __('Month') }}</label>
                        <input type="number" name="month" id="templateMonth" min="1" max="12" class="form-control">
                    </div>
                    <div class="col form-group">
                        <label>{{ __('Day') }}</label>
                        <input type="number" name="day" id="templateDay" min="1" max="31" class="form-control">
                    </div>
                </div>
                <div class="form-group" id="templateEasterRow" style="display:none">
                    <label>{{ __('Days from Easter Sunday') }} <small class="text-muted">({{ __('e.g. -3 = Maundy Thursday, -2 = Good Friday') }})</small></label>
                    <input type="number" name="easter_offset" id="templateOffset" min="-60" max="60" class="form-control" placeholder="-2">
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
const TEMPLATE_STORE_URL = @json(route('holidays.templates.store'));
const CURRENT_COUNTRY = @json($country);

function openHolidayModal(data = null) {
    const form = document.getElementById('holidayForm');
    form.action = data ? data.action : HOLIDAY_STORE_URL;
    document.getElementById('holidayFormAction').value = form.action;
    document.getElementById('holidayMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('holidayDate').value = data ? data.date : '';
    document.getElementById('holidayName').value = data ? data.name : '';
    $('#holidayModal').modal('show');
}

function templateKindChanged() {
    const kind = document.getElementById('templateKind').value;
    document.getElementById('templateFixedRow').style.display = kind === 'fixed' ? '' : 'none';
    document.getElementById('templateEasterRow').style.display = kind === 'easter' ? '' : 'none';
}

function openTemplateModal(data = null) {
    const form = document.getElementById('templateForm');
    form.action = data ? data.action : TEMPLATE_STORE_URL;
    document.getElementById('templateMethod').value = data ? 'PUT' : 'POST';
    document.getElementById('templateCountry').value = data ? data.country : CURRENT_COUNTRY;
    document.getElementById('templateName').value = data ? data.name : '';
    document.getElementById('templateKind').value = data ? data.kind : 'fixed';
    document.getElementById('templateMonth').value = data && data.month ? data.month : '';
    document.getElementById('templateDay').value = data && data.day ? data.day : '';
    document.getElementById('templateOffset').value = data && data.easter_offset !== null && data.easter_offset !== undefined ? data.easter_offset : '';
    templateKindChanged();
    $('#templateModal').modal('show');
}

@if($errors->any())
    $('#holidayModal').modal('show');
@endif
</script>
@endpush
