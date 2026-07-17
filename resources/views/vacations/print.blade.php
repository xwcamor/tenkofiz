<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
<head>
    <meta charset="UTF-8">
    <title>{{ __('Vacation Request') }} N° {{ str_pad($vacation->id, 6, '0', STR_PAD_LEFT) }} — {{ $vacation->employee->full_name }}</title>
    <style>
        * { font-family: Arial, Helvetica, sans-serif; }
        body { margin: 30px 40px; color: #222; font-size: 12px; }
        .header { display: flex; justify-content: space-between; align-items: center; border-bottom: 3px solid #1f4e79; padding-bottom: 12px; }
        .header img { max-height: 70px; }
        .company h2 { margin: 0; color: #1f4e79; font-size: 18px; }
        .company p { margin: 2px 0; color: #555; font-size: 11px; }
        .doc-number { text-align: right; color: #1f4e79; font-weight: bold; font-size: 13px; }
        h3.title { text-align: center; margin: 22px 0 4px; text-transform: uppercase; color: #1f4e79; }
        p.issued { text-align: center; margin: 0 0 18px; color: #555; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 14px; }
        th, td { border: 1px solid #999; padding: 6px 8px; }
        th { background: #1f4e79; color: #fff; font-size: 11px; text-align: left; }
        .section { background: #e8eef5; font-weight: bold; color: #1f4e79; padding: 6px 8px; border-left: 4px solid #1f4e79; margin: 16px 0 8px; }
        .status { display: inline-block; padding: 4px 14px; border-radius: 5px; font-weight: bold; color: #fff; }
        .status.APPROVED { background: #28a745; }
        .status.REJECTED { background: #dc3545; }
        .status.PENDING { background: #d39e00; }
        .reason-box { border: 1px solid #999; min-height: 70px; padding: 8px; }
        .signatures { display: flex; justify-content: space-around; margin-top: 90px; text-align: center; }
        .signatures div { border-top: 1px solid #333; width: 220px; padding-top: 5px; font-size: 11px; }
        .no-print { text-align: center; margin-bottom: 18px; }
        .no-print button { background: #1f4e79; color: #fff; border: 0; padding: 10px 22px; border-radius: 6px; cursor: pointer; font-size: 14px; }
        @media print { .no-print { display: none; } body { margin: 10px 15px; } }
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

<p class="doc-number">N° {{ str_pad($vacation->id, 6, '0', STR_PAD_LEFT) }}</p>
<h3 class="title">{{ __('Vacation Request') }}</h3>
<p class="issued">{{ __('Submitted: :date — Printed: :printed', [
    'date' => to_user_tz($vacation->created_at)->format('d/m/Y H:i'),
    'printed' => company_now()->format('d/m/Y H:i'),
]) }}</p>

<div class="section">I. {{ __('Employee Data') }}</div>
<table>
    <tr>
        <th style="width:32%">{{ __('Last and first names') }}</th><td>{{ $vacation->employee->full_name }}</td>
        <th style="width:14%">{{ __('Document') }}</th><td>{{ $vacation->employee->document_number }}</td>
    </tr>
    <tr>
        <th>{{ __('Area / Position') }}</th><td>{{ $vacation->employee->area?->name ?? '—' }} / {{ $vacation->employee->position?->name ?? '—' }}</td>
        <th>{{ __('Hire date') }}</th><td>{{ $vacation->employee->hire_date?->format('d/m/Y') ?? '—' }}</td>
    </tr>
</table>

<div class="section">II. {{ __('Requested Period') }}</div>
<table>
    <tr>
        <th style="width:25%">{{ __('Start date') }}</th><td style="text-align:center">{{ $vacation->start_date->format('d/m/Y') }}</td>
        <th style="width:25%">{{ __('End date') }}</th><td style="text-align:center">{{ $vacation->end_date->format('d/m/Y') }}</td>
    </tr>
    <tr>
        <th>{{ __('Calendar days') }}</th><td colspan="3" style="text-align:center"><strong>{{ $vacation->days }}</strong></td>
    </tr>
</table>

<div class="section">III. {{ __('Reason') }}</div>
<div class="reason-box">{{ $vacation->reason }}</div>

<div class="section">IV. {{ __('Decision') }}</div>
<table>
    <tr>
        <th style="width:25%">{{ __('Status') }}</th>
        <td><span class="status {{ $vacation->status }}">{{ __($vacation->status) }}</span></td>
        <th style="width:25%">{{ __('Decided by') }}</th>
        <td>{{ $vacation->approver?->name ?? '—' }}
            @if($vacation->approver)
                <span style="color:#777">({{ to_user_tz($vacation->updated_at)->format('d/m/Y H:i') }})</span>
            @endif
        </td>
    </tr>
</table>

<div class="signatures">
    <div>{{ $vacation->employee->full_name }}<br>{{ __('EMPLOYEE') }}</div>
    <div>{{ $vacation->approver?->name ?? $setting->company_name }}<br>{{ __('EMPLOYER / HR') }}</div>
</div>
</body>
</html>
