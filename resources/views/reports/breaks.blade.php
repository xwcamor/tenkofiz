@extends('layouts.app')
@section('title', __('Break analysis'))
@section('content')
@php
    $fmt = fn ($m) => sprintf('%d:%02d', intdiv((int) $m, 60), (int) $m % 60);
@endphp

<div class="card card-primary card-outline">
    <div class="card-header">
        <form class="form-inline">
            <label class="mr-2">{{ __('From') }}</label>
            <input type="date" name="from" value="{{ $from->toDateString() }}" class="form-control form-control-sm mr-3">
            <label class="mr-2">{{ __('To') }}</label>
            <input type="date" name="to" value="{{ $to->toDateString() }}" class="form-control form-control-sm mr-3">
            @if($sites->count() > 1)
                <select name="site_id" class="form-control form-control-sm mr-3">
                    <option value="">{{ __('All sites') }}</option>
                    @foreach($sites as $site)
                        <option value="{{ $site->id }}" @selected($siteId == $site->id)>{{ $site->name }}</option>
                    @endforeach
                </select>
            @endif
            <select name="employee_id" class="employee-select mr-3" data-url="{{ route('employees.search') }}"
                    data-placeholder="{{ __('All employees') }}" data-width="220px"
                    @if($selectedEmployee) data-selected-id="{{ $selectedEmployee->id }}" data-selected-text="{{ $selectedEmployee->full_name }}" @endif></select>
            <button class="btn btn-sm btn-primary"><i class="fas fa-filter"></i> {{ __('Generate') }}</button>
            <a href="{{ route('reports.breaksExport', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId, 'employee_id' => $selectedEmployee?->id])) }}" class="btn btn-sm btn-success ml-2"><i class="fas fa-file-excel"></i> {{ __('Excel') }}</a>
            <a href="{{ route('reports.index', array_filter(['from' => $from->toDateString(), 'to' => $to->toDateString(), 'site_id' => $siteId])) }}" class="btn btn-sm btn-outline-secondary ml-2"><i class="fas fa-arrow-left"></i> {{ __('Hours report') }}</a>
        </form>
    </div>
    <div class="card-body">
        <p class="text-muted"><i class="fas fa-info-circle"></i> {{ __('This is an analysis view only: break time is subtracted from worked hours, but going over the limit never penalizes anyone — it is only flagged for review. Limit configured in Settings: :limit min.', ['limit' => $limit ?: '∞']) }}</p>

        {{-- Headline KPIs --}}
        <div class="row">
            <div class="col-6 col-md-3 mb-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-info"><i class="fas fa-mug-hot"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Breaks taken') }}</span>
                        <span class="info-box-number">{{ $kpis['break_days'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-primary"><i class="fas fa-hourglass-half"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Average break') }}</span>
                        <span class="info-box-number">{{ $kpis['avg_min'] }} {{ __('min') }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-warning"><i class="fas fa-exclamation-triangle"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Days over the limit') }}</span>
                        <span class="info-box-number">{{ $kpis['exceeded_days'] }}</span>
                    </div>
                </div>
            </div>
            <div class="col-6 col-md-3 mb-3">
                <div class="info-box shadow-sm">
                    <span class="info-box-icon bg-danger"><i class="fas fa-stopwatch"></i></span>
                    <div class="info-box-content">
                        <span class="info-box-text">{{ __('Total excess time') }}</span>
                        <span class="info-box-number">{{ $fmt($kpis['exceeded_min']) }} h</span>
                    </div>
                </div>
            </div>
        </div>

        {{-- Per-employee summary: the analysis dashboard, no need to expand anything --}}
        <h5 class="mt-2 mb-2"><i class="fas fa-users"></i> {{ __('By employee') }}</h5>
        <div class="table-responsive">
            <table class="table table-bordered table-hover">
                <thead class="thead-light">
                    <tr>
                        <th>{{ __('Employee') }}</th><th>{{ __('Site') }}</th>
                        <th class="text-center">{{ __('Breaks') }}</th><th class="text-center">{{ __('Total') }}</th>
                        <th class="text-center">{{ __('Average') }}</th><th class="text-center">{{ __('Longest') }}</th>
                        <th class="text-center">{{ __('Days over limit') }}</th><th class="text-center">{{ __('Excess time') }}</th>
                    </tr>
                </thead>
                <tbody>
                @forelse($summary as $s)
                    <tr @class(['table-warning' => $s['exceeded_days'] > 0])>
                        <td>{{ $s['employee'] }}</td>
                        <td>{{ $s['site'] }}</td>
                        <td class="text-center">{{ $s['days'] }}</td>
                        <td class="text-center">{{ $fmt($s['total_min']) }}</td>
                        <td class="text-center">{{ $s['avg_min'] }} {{ __('min') }}</td>
                        <td class="text-center">{{ $s['max_min'] }} {{ __('min') }}</td>
                        <td class="text-center @if($s['exceeded_days'] > 0) text-danger font-weight-bold @endif">{{ $s['exceeded_days'] }}</td>
                        <td class="text-center @if($s['exceeded_min'] > 0) text-danger font-weight-bold @endif">{{ $s['exceeded_min'] ? $fmt($s['exceeded_min']) : '—' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="text-center text-muted py-4">{{ __('No breaks recorded in this period.') }}</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        {{-- Full per-day detail (start / end / duration) --}}
        @if($detail->isNotEmpty())
            <h5 class="mt-4 mb-2"><i class="fas fa-list"></i> {{ __('Detail by day') }}</h5>
            <div class="table-responsive">
                <table class="table table-sm table-bordered table-hover">
                    <thead class="thead-light">
                        <tr>
                            <th>{{ __('Employee') }}</th><th>{{ __('Site') }}</th><th>{{ __('Date') }}</th>
                            <th class="text-center">{{ __('Break start') }}</th><th class="text-center">{{ __('Break end') }}</th>
                            <th class="text-center">{{ __('Duration') }}</th><th class="text-center">{{ __('Status') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                    @foreach($detail->sortByDesc('date') as $row)
                        <tr>
                            <td>{{ $row['employee'] }}</td>
                            <td>{{ $row['site'] }}</td>
                            <td>{{ $row['date']->format('d/m/Y') }}</td>
                            <td class="text-center">{{ $row['break_out'] }}</td>
                            <td class="text-center">{{ $row['break_in'] }}</td>
                            <td class="text-center font-weight-bold">{{ $row['minutes'] }} {{ __('min') }}</td>
                            <td class="text-center">
                                @if($row['over'])
                                    <span class="badge badge-danger">{{ __('Time exceeded') }} (+{{ $row['exceeded'] }})</span>
                                @else
                                    <span class="badge badge-success">{{ __('Within limit') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</div>
@endsection
