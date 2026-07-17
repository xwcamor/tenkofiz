@extends('layouts.app')
@section('title', __('Worked hours and days report'))
@section('content')
<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm mr-3">
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Generate') }}</button>
        </form>
    </div>
    <div class="card-body">
        <table class="table table-bordered table-hover report-table">
            <thead>
                <tr>
                    <th>{{ __('Employee') }}</th><th>{{ __('Document') }}</th><th>{{ __('Area') }}</th><th>{{ __('Position') }}</th>
                    <th>{{ __('Worked days') }}</th><th>{{ __('On time') }}</th><th>{{ __('Late') }}</th><th>{{ __('Absences') }}</th><th>{{ __('Excused') }}</th>
                    <th>{{ __('Worked hours') }}</th><th>{{ __('Vacation days') }}</th><th>{{ __('Sheet') }}</th>
                </tr>
            </thead>
            <tbody>
            @foreach($rows as $row)
                <tr>
                    <td>{{ $row['employee'] }}</td>
                    <td>{{ $row['document_number'] }}</td>
                    <td>{{ $row['area'] }}</td>
                    <td>{{ $row['position'] }}</td>
                    <td class="text-center">{{ $row['worked_days'] }}</td>
                    <td class="text-center text-success font-weight-bold">{{ $row['on_time'] }}</td>
                    <td class="text-center text-warning font-weight-bold">{{ $row['late'] }}</td>
                    <td class="text-center text-danger font-weight-bold">{{ $row['absent'] }}</td>
                    <td class="text-center">{{ $row['excused'] }}</td>
                    <td class="text-center">{{ $row['worked_hours'] }}</td>
                    <td class="text-center">{{ $row['vacation_days'] }}</td>
                    <td class="text-center">
                        <a href="{{ route('reports.sheet', $row['id']) }}?from={{ $from->toDateString() }}&to={{ $to->toDateString() }}" target="_blank" class="btn btn-sm btn-outline-danger" title="{{ __('Printable formal sheet') }}"><i class="fas fa-file-pdf"></i></a>
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
        <p class="text-muted mt-2"><i class="fas fa-info-circle"></i> {{ __('Worked hours are the sum of (check-out − check-in) for each day with a complete record. Use the buttons to export to Excel or print.') }}</p>
    </div>
</div>
@endsection
