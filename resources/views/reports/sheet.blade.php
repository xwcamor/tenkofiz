<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Attendance Sheet') }} — {{ $employee->full_name }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        body { margin: 30px 40px; color: #222; font-size: 12px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #1f4e79; padding-bottom: 12px; }
        .header img { max-height: 70px; }
        .company h2 { margin: 0; color: #1f4e79; font-size: 18px; }
        .company p { margin: 2px 0; color: #555; font-size: 11px; }
        h3.title { text-align: center; margin: 18px 0 4px; text-transform: uppercase; color: #1f4e79; }
        p.range { text-align: center; margin: 0 0 14px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #999; padding: 5px 7px; }
        th { background: #1f4e79; color: #fff; font-size: 11px; }
        .section { background: #e8eef5; font-weight: bold; color: #1f4e79; padding: 6px 8px; border-left: 4px solid #1f4e79; margin: 16px 0 8px; }
        .summary td { text-align: center; font-weight: bold; font-size: 13px; }
        .signatures { display: flex; justify-content: space-around; margin-top: 70px; text-align: center; }
        .signatures div { border-top: 1px solid #333; width: 220px; padding-top: 5px; font-size: 11px; }
        .no-print { text-align: center; margin-bottom: 18px; }
        .no-print button { background: #1f4e79; color: #fff; border: 0; padding: 10px 22px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        @media print { .no-print { display: none; } body { margin: 10px 15px; } }
        .badge { padding: 2px 6px; border-radius: 4px; color: #fff; font-size: 10px; }
    </style>
</head>
<body>
<div class="no-print">
    <button onclick="window.print()">🖨 {{ __('Print / Save as PDF') }}</button>
</div>

<div class="header">
    <div class="company">
        <h2>{{ $setting->company_name }}</h2>
        @if($setting->tax_id)<p>{{ __('Tax ID') }}: {{ $setting->tax_id }}</p>@endif
        @if($setting->address)<p>{{ $setting->address }} @if($setting->phone) — {{ __('Phone') }}: {{ $setting->phone }} @endif</p>@endif
    </div>
    @if($setting->logo)<img src="{{ asset($setting->logo) }}" alt="logo">@endif
</div>

<h3 class="title">{{ __('Employee Attendance Sheet') }}</h3>
<p class="range">{{ __('Period: from :from to :to — Issued: :issued', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i')]) }}</p>

<div class="section">I. {{ __('Employee Data') }}</div>
<table>
    <tr>
        <th style="width:35%">{{ __('Last and first names') }}</th><td>{{ $employee->full_name }}</td>
        <th style="width:12%">{{ __('Document') }}</th><td>{{ $employee->document_number }}</td>
    </tr>
    <tr>
        <th>{{ __('Area / Position') }}</th><td>{{ $employee->area?->name ?? '—' }} / {{ $employee->position?->name ?? '—' }}</td>
        <th>{{ __('Schedule') }}</th><td>{{ $employee->schedule?->name ?? '—' }} ({{ $employee->schedule ? substr($employee->schedule->start_time, 0, 5).' - '.substr($employee->schedule->end_time, 0, 5) : '' }})</td>
    </tr>
    <tr>
        <th>{{ __('Hire date') }}</th><td>{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</td>
        <th>{{ __('Status') }}</th><td>{{ $employee->is_active ? __('ACTIVE') : __('INACTIVE') }}</td>
    </tr>
</table>

<div class="section">II. {{ __('Period Summary') }}</div>
<table class="summary">
    <tr><th>{{ __('Worked days') }}</th><th>{{ __('On time') }}</th><th>{{ __('Late') }}</th><th>{{ __('Absences') }}</th><th>{{ __('Excused') }}</th><th>{{ __('Worked hours') }}</th></tr>
    <tr>
        <td>{{ $summary['days'] }}</td>
        <td style="color:#28a745">{{ $summary['on_time'] }}</td>
        <td style="color:#d39e00">{{ $summary['late'] }}</td>
        <td style="color:#dc3545">{{ $summary['absent'] }}</td>
        <td style="color:#17a2b8">{{ $summary['excused'] }}</td>
        <td>{{ $summary['hours'] }} hrs</td>
    </tr>
</table>

<div class="section">III. {{ __('Attendance Detail') }}</div>
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th><th>{{ __('Method') }}</th><th>{{ __('Note') }}</th></tr></thead>
    <tbody>
    @forelse($attendances as $attendance)
        <tr>
            <td>{{ $attendance->date->format('d/m/Y') }}</td>
            <td style="text-align:center">{{ $attendance->check_in ?? '—' }}</td>
            <td style="text-align:center">{{ $attendance->check_out ?? '—' }}</td>
            <td style="text-align:center">{{ __($attendance->status) }}</td>
            <td style="text-align:center">{{ __($attendance->method) }}</td>
            <td>{{ $attendance->note }}</td>
        </tr>
    @empty
        <tr><td colspan="6" style="text-align:center;color:#888">{{ __('No records in the period') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="section">IV. {{ __('Approved Vacations in the Period') }}</div>
<table>
    <thead><tr><th>{{ __('Start') }}</th><th>{{ __('End') }}</th><th>{{ __('Days') }}</th><th>{{ __('Reason') }}</th></tr></thead>
    <tbody>
    @forelse($vacations as $vacation)
        <tr>
            <td>{{ $vacation->start_date->format('d/m/Y') }}</td>
            <td>{{ $vacation->end_date->format('d/m/Y') }}</td>
            <td style="text-align:center">{{ $vacation->days }}</td>
            <td>{{ $vacation->reason }}</td>
        </tr>
    @empty
        <tr><td colspan="4" style="text-align:center;color:#888">{{ __('No vacations in the period') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="section">V. {{ __('Justifications in the Period') }}</div>
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Reason') }}</th><th>{{ __('Status') }}</th><th>{{ __('Reviewed by') }}</th></tr></thead>
    <tbody>
    @forelse($justifications as $justification)
        <tr>
            <td>{{ $justification->date->format('d/m/Y') }}</td>
            <td>{{ $justification->reason }}</td>
            <td style="text-align:center">{{ __($justification->status) }}</td>
            <td>{{ $justification->reviewer?->name ?? '—' }}</td>
        </tr>
    @empty
        <tr><td colspan="4" style="text-align:center;color:#888">{{ __('No justifications in the period') }}</td></tr>
    @endforelse
    </tbody>
</table>

<div class="signatures">
    <div>{{ $employee->full_name }}<br>{{ __('EMPLOYEE') }}</div>
    <div>{{ $setting->company_name }}<br>{{ __('EMPLOYER / HR') }}</div>
</div>
</body>
</html>
