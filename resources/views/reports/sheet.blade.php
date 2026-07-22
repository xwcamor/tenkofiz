<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Attendance Sheet') }} — {{ $employee->full_name }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        body { margin: 30px 40px; color: #222; font-size: 12px; }
        .header { display: table; width: 100%; border-bottom: 3px solid #1f4e79; padding-bottom: 12px; }
        .header td { border: 0; padding: 0; vertical-align: middle; }
        .header .company { width: 100%; }
        .header .logo-cell { text-align: right; white-space: nowrap; }
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
        .signatures { display: table; width: 100%; margin-top: 70px; text-align: center; }
        .signatures td { border: 0; vertical-align: top; text-align: center; width: 50%; padding: 0; }
        .signatures .line { display: inline-block; border-top: 1px solid #333; width: 220px; padding-top: 5px; font-size: 11px; }
        .no-print { display: flex; gap: 10px; justify-content: center; align-items: center; flex-wrap: wrap; margin-bottom: 18px; }
        .no-print button { background: #1f4e79; color: #fff; border: 0; padding: 10px 22px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        .no-print .picker { display: flex; gap: 8px; align-items: center; background: #f1f4f9; padding: 8px 12px; border-radius: 8px; }
        .no-print .picker input { padding: 7px 9px; border: 1px solid #c4cfdd; border-radius: 6px; font-size: 14px; }
        .no-print .picker button { background: #2e75b6; padding: 8px 16px; }
        @media print { .no-print { display: none; } body { margin: 10px 15px; } }
        .badge { padding: 2px 6px; border-radius: 4px; color: #fff; font-size: 10px; }
    </style>
</head>
<body>
@unless($pdf ?? false)
<div class="no-print">
    <form method="GET" action="{{ route('reports.sheet', $employee) }}" class="picker">
        <label style="font-weight:bold; color:#1f4e79">{{ __('Month') }}:</label>
        <input type="month" name="month" value="{{ $selectedMonth }}" max="{{ company_now()->format('Y-m') }}">
        <button type="submit">{{ __('View') }}</button>
    </form>
    <a href="{{ route('reports.sheet', $employee) }}?month={{ $selectedMonth }}&format=pdf" style="text-decoration:none">
        <button type="button">⬇ {{ __('Download PDF') }}</button>
    </a>
    <button onclick="window.print()">🖨 {{ __('Print') }}</button>
</div>
@endunless

<table class="header"><tr>
    <td class="company">
        <h2>{{ $setting->company_name }}</h2>
        @if($setting->tax_id)<p>{{ __('Tax ID') }}: {{ $setting->tax_id }}</p>@endif
        @if($setting->address)<p>{{ $setting->address }} @if($setting->phone) — {{ __('Phone') }}: {{ $setting->phone }} @endif</p>@endif
    </td>
    <td class="logo-cell">@if($setting->logo)<img src="{{ ($pdf ?? false) ? public_path($setting->logo) : asset($setting->logo) }}" alt="logo">@endif</td>
</tr></table>

<h3 class="title">{{ __('Employee Attendance Sheet') }}</h3>
<p class="range">{{ __('Period: from :from to :to — Issued: :issued', ['from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i')]) }}</p>

<div class="section">I. {{ __('Employee Data') }}</div>
<table>
    <tr>
        <th style="width:35%">{{ __('Last and first names') }}</th><td>{{ $employee->full_name }}</td>
        <th style="width:12%">{{ __('Document') }}</th><td>{{ $employee->document_type }} {{ $employee->document_number }}</td>
    </tr>
    <tr>
        <th>{{ __('Area / Position') }}</th><td>{{ $employee->area?->name ?? '—' }} / {{ $employee->position?->name ?? '—' }}</td>
        <th>{{ __('Schedule') }}</th><td>{{ $employee->schedule?->name ?? '—' }}{{ $employee->schedule ? ' ('.$employee->schedule->daysSummary().')' : '' }}</td>
    </tr>
    <tr>
        <th>{{ __('Site') }}</th><td>{{ $employee->site?->name ?? '—' }}</td>
        <th>{{ __('Site address') }}</th><td>{{ $employee->site?->address ?? '—' }}</td>
    </tr>
    <tr>
        <th>{{ __('Hire date') }}</th><td>{{ $employee->hire_date?->format('d/m/Y') ?? '—' }}</td>
        <th>{{ __('Status') }}</th><td>{{ $employee->is_active ? __('ACTIVE') : __('INACTIVE') }}</td>
    </tr>
</table>

<div class="section">II. {{ __('Period Summary') }}</div>
<table class="summary">
    <tr><th>{{ __('Worked days') }}</th><th>{{ __('On time') }}</th><th>{{ __('Late') }}</th><th>{{ __('Late minutes') }}</th><th>{{ __('Absences') }}</th><th>{{ __('Excused') }}</th><th>{{ __('Expected hours') }}</th><th>{{ __('Worked hours') }}</th><th>{{ __('Owed') }}</th><th>{{ __('Met quota?') }}</th></tr>
    <tr>
        <td>{{ $summary['days'] }}</td>
        <td style="color:#28a745">{{ $summary['on_time'] }}</td>
        <td style="color:#d39e00">{{ $summary['late'] }}</td>
        <td style="color:#d39e00">{{ $summary['late_minutes'] }}</td>
        <td style="color:#dc3545">{{ $summary['absent'] }}</td>
        <td style="color:#17a2b8">{{ $summary['excused'] }}</td>
        <td>{{ $summary['expected_hours'] }} hrs</td>
        <td>{{ $summary['hours'] }} hrs</td>
        <td style="color:{{ $summary['debt_minutes'] > 0 ? '#dc3545' : '#6c757d' }};font-weight:bold">{{ $summary['debt_minutes'] > 0 ? $summary['debt'] : '—' }}</td>
        <td style="font-weight:bold;color:{{ $summary['complied'] ? '#28a745' : '#dc3545' }}">{{ $summary['complied'] ? __('Yes') : __('No') }}</td>
    </tr>
</table>
@if($summary['async_enabled'] ?? false)
    <p style="font-size:11px;color:#555;margin:4px 0 0"><i class="fas fa-laptop-house"></i> {{ __('Worked/expected hours include :h of asynchronous/credited hours (done remotely, not marked at the kiosk).', ['h' => $summary['async_hours']]) }}</p>
@endif

<div class="section">III. {{ __('Attendance Detail') }}</div>
<table>
    <thead><tr><th>{{ __('Date') }}</th><th>{{ __('Check-in') }}</th><th>{{ __('Check-out') }}</th><th>{{ __('Status') }}</th><th>{{ __('Method') }}</th><th>{{ __('Note') }}</th></tr></thead>
    <tbody>
    @php $statusInk = ['ON_TIME' => '#28a745', 'LATE' => '#fab219', 'ABSENT' => '#dc3545', 'EXCUSED' => '#2a78d6', 'VACATION' => '#17a2b8']; @endphp
    @forelse($breakdown as $day)
        @php $att = $day['attendance']; $st = $day['status']; @endphp
        <tr>
            <td>{{ $day['date']->format('d/m/Y') }}</td>
            <td style="text-align:center">{{ $att->check_in ?? '—' }}</td>
            <td style="text-align:center">{{ $att->check_out ?? '—' }}</td>
            <td style="text-align:center;color:{{ $statusInk[$st] ?? '#333' }};font-weight:bold">{{ $st === 'VACATION' ? __('Vacation') : __($st) }}</td>
            <td style="text-align:center">{{ $att ? __($att->method) : ($day['virtual'] ? '—' : '') }}</td>
            <td>{{ $att->note ?? ($day['virtual'] && $st === 'ABSENT' ? __('No check-in (derived)') : '') }}</td>
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

<table class="signatures"><tr>
    <td><span class="line">{{ $employee->full_name }}<br>{{ __('EMPLOYEE') }}</span></td>
    <td><span class="line">{{ $setting->company_name }}<br>{{ __('EMPLOYER / HR') }}</span></td>
</tr></table>
</body>
</html>
