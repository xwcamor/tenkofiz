<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    use \App\Http\Controllers\Concerns\Sortable;

    /** Report of worked hours and days per employee within a date range */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $rows = $this->buildRows($from, $to, $siteId);
        $sites = $this->visibleSites($request);

        [$rows, $sort, $dir] = $this->sortCollection($rows, $request, [
            'employee' => 'employee', 'document' => 'document_number', 'site' => 'site',
            'area' => 'area', 'position' => 'position', 'worked_days' => 'worked_days',
            'on_time' => 'on_time', 'late' => 'late', 'late_minutes' => 'late_minutes',
            'absent' => 'absent', 'excused' => 'excused', 'expected' => 'expected_minutes',
            'worked' => 'worked_minutes', 'debt' => 'debt_minutes', 'vacation' => 'vacation_days',
        ], 'employee');

        // Chart 1: status distribution across the whole period
        $statusTotals = [
            'ON_TIME' => (int) $rows->sum('on_time'),
            'LATE' => (int) $rows->sum('late'),
            'ABSENT' => (int) $rows->sum('absent'),
            'EXCUSED' => (int) $rows->sum('excused'),
        ];

        // Chart 2: worked hours per employee (top 10 by hours)
        $topHours = $rows->sortByDesc('worked_minutes')->take(10)->values();
        $hoursLabels = $topHours->pluck('employee');
        $hoursData = $topHours->map(fn ($r) => round($r['worked_minutes'] / 60, 1));

        return view('reports.index', compact('rows', 'from', 'to', 'statusTotals', 'hoursLabels', 'hoursData', 'sites', 'siteId', 'sort', 'dir'));
    }

    /** Same report as a server-generated Excel file (full data, not just the visible page) */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $rows = $this->buildRows($from, $to, $siteId);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Report'));

        $headers = [
            __('Employee'), __('Document'), __('Contract type'), __('Site'), __('Address'), __('Area'), __('Position'),
            __('Worked days'), __('On time'), __('Late'), __('Late minutes'),
            __('Absences'), __('Excused'), __('Expected hours'), __('Worked hours'), __('Owed'), __('Met quota?'), __('Vacation days'),
        ];
        // Title on row 1, column headers on row 2 directly above the data (no
        // stray line between the headers and the rows).
        $sheet->setCellValue('A1', __('Attendance report').' · '.__('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A1:R1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }
        $sheet->getStyle('A2:R2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
            'alignment' => ['vertical' => 'center', 'wrapText' => true],
        ]);
        $sheet->freezePane('A3'); // keep the header visible while scrolling

        $rowIndex = 3;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['employee'], $row['document'], $row['contract_type'], $row['site'], $row['site_address'], $row['area'], $row['position'],
                $row['worked_days'], $row['on_time'], $row['late'], $row['late_minutes'],
                $row['absent'], $row['excused'], $row['expected_hours'], $row['worked_hours'], $row['debt_hours'], $row['complied'] ? __('Yes') : __('No'), $row['vacation_days'],
            ], null, 'A'.$rowIndex++);
        }
        foreach (range('A', 'R') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        // Enable Excel's column filters/sort on the header row + data
        $sheet->setAutoFilter('A2:R'.max(2, $rowIndex - 1));

        $file = tempnam(sys_get_temp_dir(), 'report');
        (new Xlsx($spreadsheet))->save($file);

        return response()->download($file, 'attendance_report_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * Detailed Excel: one row per employee per day, with check-in, check-out,
     * worked hours and status — plus a worked-hours total per employee.
     */
    public function exportDetail(Request $request)
    {
        [$from, $to] = $this->range($request);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;

        $employees = Employee::with([
            'area', 'position', 'schedule.days',
            'attendances' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date'),
        ])->where('is_active', true)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->orderBy('last_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Detail'));

        // Per day: worked hours COUNT toward the quota (capped, no overtime), the
        // day's Expected quota, and what is still Owed. Worked + Owed = Expected on
        // short days. The break is not shown here — it lives in the break analysis.
        $headers = [__('Employee'), __('Document'), __('Date'), __('Check-in'), __('Check-out'), __('Worked hours'), __('Expected hours'), __('Owed'), __('Status')];
        $lastCol = 'I';

        $sheet->setCellValue('A1', __('Attendance detail').' · '.__('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A1:'.$lastCol.'1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }
        $sheet->getStyle('A2:'.$lastCol.'2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
        ]);
        $sheet->freezePane('A3');

        $clamp = (bool) app_setting()->clamp_worked_hours;
        $hm = fn ($m) => sprintf('%d:%02d', intdiv((int) $m, 60), (int) $m % 60);

        $rowIndex = 3;
        foreach ($employees as $employee) {
            $totalComplied = 0;
            $totalOwed = 0;

            foreach ($employee->attendances as $attendance) {
                $shift = $clamp ? $attendance->clampShift($employee->schedule) : null;
                $full = $attendance->check_in && $attendance->check_out;
                $expDay = $full ? ($attendance->expected_minutes ?? ($employee->schedule?->expectedMinutesFor($attendance->date->dayOfWeek) ?? 0)) : 0;
                $complied = $full ? $attendance->compliedMinutes($expDay, $shift) : 0;
                $owed = max(0, $expDay - $complied);
                $totalComplied += $complied;
                $totalOwed += $owed;

                $sheet->fromArray([
                    $employee->full_name,
                    $employee->document_number,
                    $attendance->date->format('d/m/Y'),
                    $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—',
                    $attendance->check_out ? substr($attendance->check_out, 0, 5) : '—',
                    $full ? $hm($complied) : '—',
                    $full ? $hm($expDay) : '—',
                    $owed > 0 ? $hm($owed) : '—',
                    __($attendance->status),
                ], null, 'A'.$rowIndex++);
            }

            // Subtotal per employee: hours that count, and total still owed
            if ($employee->attendances->isNotEmpty()) {
                $sheet->setCellValue('E'.$rowIndex, __('Total'));
                $sheet->setCellValue('F'.$rowIndex, $hm($totalComplied));
                $sheet->setCellValue('H'.$rowIndex, $totalOwed > 0 ? $hm($totalOwed) : '—');
                $sheet->getStyle('A'.$rowIndex.':'.$lastCol.$rowIndex)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2F8']],
                ]);
                $rowIndex++;
            }
        }

        foreach (range('A', $lastCol) as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->setAutoFilter('A2:'.$lastCol.max(2, $rowIndex - 1));

        $file = tempnam(sys_get_temp_dir(), 'report_detail');
        (new Xlsx($spreadsheet))->save($file);

        return response()->download($file, 'attendance_detail_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * Break-time analysis (managers/HR): who took how long on break, and who went
     * over the company limit. Read-only, for analysis — it never penalizes hours.
     */
    public function breaks(Request $request)
    {
        abort_unless(app_setting()->kiosk_breaks_enabled, 404);

        [$from, $to] = $this->range($request);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $employeeId = request_employee_id($request); // obfuscated id → raw key

        $data = $this->buildBreakData($from, $to, $siteId, $employeeId);
        $sites = $this->visibleSites($request);
        $selectedEmployee = $employeeId ? Employee::find($employeeId) : null;

        return view('reports.breaks', $data + compact('from', 'to', 'sites', 'siteId', 'selectedEmployee'));
    }

    /** The same break analysis as an Excel file (with filters enabled) */
    public function breaksExport(Request $request)
    {
        abort_unless(app_setting()->kiosk_breaks_enabled, 404);

        [$from, $to] = $this->range($request);
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $employeeId = request_employee_id($request); // obfuscated id → raw key

        $data = $this->buildBreakData($from, $to, $siteId, $employeeId);
        $limit = $data['limit'];

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Breaks'));

        $headers = [__('Employee'), __('Document'), __('Site'), __('Date'), __('Break start'), __('Break end'), __('Duration (min)'), __('Limit (min)'), __('Over limit (min)'), __('Status')];

        $sheet->setCellValue('A1', __('Break analysis').' · '.__('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A1:J1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }
        $sheet->getStyle('A2:J2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
            'alignment' => ['vertical' => 'center', 'wrapText' => true],
        ]);
        $sheet->freezePane('A3');

        $rowIndex = 3;
        foreach ($data['detail'] as $row) {
            $sheet->fromArray([
                $row['employee'], $row['document'], $row['site'], $row['date']->format('d/m/Y'),
                $row['break_out'], $row['break_in'], $row['minutes'], $limit ?: '—',
                $row['exceeded'] ?: '', $row['over'] ? __('Time exceeded') : __('Within limit'),
            ], null, 'A'.$rowIndex++);
        }
        foreach (range('A', 'J') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }
        $sheet->setAutoFilter('A2:J'.max(2, $rowIndex - 1));

        $file = tempnam(sys_get_temp_dir(), 'report_breaks');
        (new Xlsx($spreadsheet))->save($file);

        return response()->download($file, 'break_analysis_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /**
     * Shared break dataset: a per-day detail list and a per-employee summary
     * (days with a break, total/avg/longest minutes, days and minutes over the
     * limit). Only days that actually have a break are included.
     */
    private function buildBreakData($from, $to, ?int $siteId = null, ?int $employeeId = null): array
    {
        $limit = (int) (app_setting()->break_limit_minutes ?? 60);

        $attendances = Attendance::with('employee.site')
            ->inCurrentSite()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->whereNotNull('break_out')
            ->whereNotNull('break_in')
            ->whereHas('employee', fn ($q) => $q->where('is_active', true)
                ->when($siteId, fn ($e) => $e->where('site_id', $siteId))
                ->when($employeeId, fn ($e) => $e->whereKey($employeeId)))
            ->orderBy('date')
            ->get();

        $detail = $attendances->map(function ($att) use ($limit) {
            $minutes = $att->breakMinutes();
            $exceeded = $att->breakExceededMinutes($limit);

            return [
                'employee' => $att->employee->full_name,
                'employee_id' => $att->employee_id,
                'document' => $att->employee->document_number,
                'site' => $att->employee->site?->name ?? '—',
                'date' => $att->date,
                'break_out' => substr($att->break_out, 0, 5),
                'break_in' => substr($att->break_in, 0, 5),
                'minutes' => $minutes,
                'exceeded' => $exceeded,
                'over' => $exceeded > 0,
            ];
        });

        $summary = $detail->groupBy('employee_id')->map(function ($rows) {
            $minutes = $rows->pluck('minutes');

            return [
                'employee' => $rows->first()['employee'],
                'site' => $rows->first()['site'],
                'days' => $rows->count(),
                'total_min' => (int) $minutes->sum(),
                'avg_min' => (int) round($minutes->avg()),
                'max_min' => (int) $minutes->max(),
                'exceeded_days' => $rows->where('over', true)->count(),
                'exceeded_min' => (int) $rows->sum('exceeded'),
            ];
        })->sortByDesc('exceeded_min')->values();

        // Headline KPIs across the whole (filtered) set
        $kpis = [
            'break_days' => $detail->count(),
            'avg_min' => $detail->isEmpty() ? 0 : (int) round($detail->avg('minutes')),
            'exceeded_days' => $detail->where('over', true)->count(),
            'exceeded_min' => (int) $detail->sum('exceeded'),
        ];

        return compact('detail', 'summary', 'kpis', 'limit');
    }

    /** Default range = current payroll cut-off period (configured in Settings) */
    private function range(Request $request): array
    {
        // Reports default to the LAST CLOSED payroll period (the finished one you
        // review/pay), not the current open period — e.g. cut-off 19 viewed on
        // Jul 21 defaults to Jun 20 – Jul 19. The from/to inputs override it.
        [$periodStart, $periodEnd] = last_closed_period();

        return [
            $request->date('from') ?? $periodStart,
            $request->date('to') ?? $periodEnd->min(company_now()),
        ];
    }

    private function buildRows($from, $to, ?int $siteId = null)
    {
        $clamp = (bool) app_setting()->clamp_worked_hours;

        $employees = Employee::with([
            'area', 'position', 'site', 'schedule.days',
            'attendances' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
            'vacations' => fn ($q) => $q->where('status', 'APPROVED'),
        ])->where('is_active', true)
            ->when($siteId, fn ($q) => $q->where('site_id', $siteId))
            ->orderBy('last_name')->get();

        return $employees->map(function ($employee) use ($clamp) {
            $attendances = $employee->attendances;

            $compliedMinutes = 0;  // hours that count: min(present, due) per day, no overtime credit
            $expectedMinutes = 0;  // the "jornada" due on worked days
            $deficitMinutes = 0;   // how much they still owe (expected − complied), never negative
            $lateMinutes = 0;
            foreach ($attendances as $attendance) {
                $weekday = $attendance->date->dayOfWeek;
                $shift = $clamp ? $attendance->clampShift($employee->schedule) : null;

                // Only full days (check-in + check-out) carry a quota, so a short day
                // (late in / early out) shows as a deficit while absences stay separate.
                // Prefer the quota frozen at check-in; fall back to a live compute.
                if ($attendance->check_in && $attendance->check_out) {
                    $expDay = $attendance->expected_minutes ?? ($employee->schedule?->expectedMinutesFor($weekday) ?? 0);
                    $complied = $attendance->compliedMinutes($expDay, $shift);
                    $expectedMinutes += $expDay;
                    $compliedMinutes += $complied;
                    $deficitMinutes += max(0, $expDay - $complied); // staying late never offsets a short day
                }

                // Late minutes: how far past the scheduled start the check-in was
                if ($attendance->status === 'LATE' && $attendance->check_in) {
                    $shift = $employee->schedule?->worksOn($weekday);
                    if ($shift) {
                        $scheduled = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$shift->start_time);
                        $actual = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                        $lateMinutes += max(0, (int) $scheduled->diffInMinutes($actual, false));
                    }
                }
            }

            return [
                'id' => $employee->id,
                'sheet_key' => $employee->getRouteKey(), // obfuscated id for the sheet URL
                'employee' => $employee->full_name,
                'document' => $employee->document_type.' '.$employee->document_number,
                'document_number' => $employee->document_number,
                'contract_type' => $employee->contractTypeLabel(),
                'area' => $employee->area?->name ?? '—',
                'position' => $employee->position?->name ?? '—',
                'site' => $employee->site?->name ?? '—',
                'site_address' => $employee->site?->address ?? '',
                'worked_days' => $attendances->whereNotNull('check_in')->whereIn('status', ['ON_TIME', 'LATE'])->count(),
                'on_time' => $attendances->where('status', 'ON_TIME')->count(),
                'late' => $attendances->where('status', 'LATE')->count(),
                'late_minutes' => $lateMinutes,
                'absent' => $attendances->where('status', 'ABSENT')->count(),
                'excused' => $attendances->where('status', 'EXCUSED')->count(),
                'worked_hours' => sprintf('%d:%02d', intdiv($compliedMinutes, 60), $compliedMinutes % 60),
                'worked_minutes' => $compliedMinutes,
                'expected_hours' => sprintf('%d:%02d', intdiv($expectedMinutes, 60), $expectedMinutes % 60),
                'expected_minutes' => $expectedMinutes,
                'debt_minutes' => $deficitMinutes,
                'debt_hours' => sprintf('%d:%02d', intdiv($deficitMinutes, 60), $deficitMinutes % 60),
                'complied' => $deficitMinutes === 0,
                'vacation_days' => $employee->vacations->sum('days'),
            ];
        });
    }

    /** Redirects the logged-in employee to their own report sheet */
    public function mySheet(Request $request)
    {
        $employee = Employee::where('user_id', $request->user()->id)->first();

        if (!$employee) {
            return redirect()->route('dashboard')->with('error', __('Your user is not linked to an employee.'));
        }

        return redirect()->route('reports.sheet', ['employee' => $employee] + $request->only(['from', 'to', 'month']));
    }

    /** Printable formal sheet (PDF via browser): managers see anyone, employees only their own */
    public function sheet(Request $request, Employee $employee)
    {
        $user = $request->user();
        if (!$user->hasModule('reports') && $employee->user_id !== $user->id) {
            abort(403, __('You can only view your own sheet.'));
        }

        // A month picker (YYYY-MM) takes priority; otherwise use from/to or the cut-off period
        if ($request->filled('month')) {
            $month = \Carbon\Carbon::parse($request->input('month').'-01');
            $from = $month->copy()->startOfMonth();
            $to = $month->copy()->endOfMonth()->min(company_now());
        } else {
            [$from, $to] = $this->range($request);
        }
        $selectedMonth = $from->format('Y-m');

        $setting = app_setting();

        $attendances = $employee->attendances()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->orderBy('date')
            ->get();

        $employee->load('schedule.days', 'site', 'area', 'position');
        $clamp = (bool) $setting->clamp_worked_hours;

        $compliedMinutes = 0;
        $expectedMinutes = 0;
        $deficitMinutes = 0;
        $lateMinutes = 0;
        foreach ($attendances as $attendance) {
            $weekday = $attendance->date->dayOfWeek;
            $shift = $clamp ? $attendance->clampShift($employee->schedule) : null;

            if ($attendance->check_in && $attendance->check_out) {
                $expDay = $attendance->expected_minutes ?? ($employee->schedule?->expectedMinutesFor($weekday) ?? 0);
                $complied = $attendance->compliedMinutes($expDay, $shift);
                $expectedMinutes += $expDay;
                $compliedMinutes += $complied;
                $deficitMinutes += max(0, $expDay - $complied);
            }

            if ($attendance->status === 'LATE' && $attendance->check_in) {
                $shift = $employee->schedule?->worksOn($weekday);
                if ($shift) {
                    $scheduled = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$shift->start_time);
                    $actual = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                    $lateMinutes += max(0, (int) $scheduled->diffInMinutes($actual, false));
                }
            }
        }

        $summary = [
            'days' => $attendances->whereIn('status', ['ON_TIME', 'LATE'])->count(),
            'on_time' => $attendances->where('status', 'ON_TIME')->count(),
            'late' => $attendances->where('status', 'LATE')->count(),
            'late_minutes' => $lateMinutes,
            'absent' => $attendances->where('status', 'ABSENT')->count(),
            'excused' => $attendances->where('status', 'EXCUSED')->count(),
            'hours' => sprintf('%d:%02d', intdiv($compliedMinutes, 60), $compliedMinutes % 60),
            'expected_hours' => sprintf('%d:%02d', intdiv($expectedMinutes, 60), $expectedMinutes % 60),
            'debt' => sprintf('%d:%02d', intdiv($deficitMinutes, 60), $deficitMinutes % 60),
            'debt_minutes' => $deficitMinutes,
            'complied' => $deficitMinutes === 0,
        ];

        $vacations = $employee->vacations()
            ->where('status', 'APPROVED')
            ->where(fn ($q) => $q->whereBetween('start_date', [$from, $to])->orWhereBetween('end_date', [$from, $to]))
            ->get();

        $justifications = $employee->justifications()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        $data = compact('employee', 'setting', 'attendances', 'summary', 'vacations', 'justifications', 'from', 'to', 'selectedMonth');

        // Server-side PDF (identical to the printable view, no browser needed)
        if ($request->input('format') === 'pdf') {
            return \Barryvdh\DomPDF\Facade\Pdf::loadView('reports.sheet', $data + ['pdf' => true])
                ->setPaper('a4')
                ->download('ficha_'.$employee->document_number.'_'.$from->format('Ymd').'-'.$to->format('Ymd').'.pdf');
        }

        return view('reports.sheet', $data);
    }
}
