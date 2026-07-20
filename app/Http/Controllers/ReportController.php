<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use Illuminate\Http\Request;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ReportController extends Controller
{
    /** Report of worked hours and days per employee within a date range */
    public function index(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = $this->buildRows($from, $to);

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

        return view('reports.index', compact('rows', 'from', 'to', 'statusTotals', 'hoursLabels', 'hoursData'));
    }

    /** Same report as a server-generated Excel file (full data, not just the visible page) */
    public function export(Request $request)
    {
        [$from, $to] = $this->range($request);
        $rows = $this->buildRows($from, $to);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Report'));

        $headers = [
            __('Employee'), __('Document'), __('Contract type'), __('Site'), __('Address'), __('Area'), __('Position'),
            __('Worked days'), __('On time'), __('Late'), __('Late minutes'),
            __('Absences'), __('Excused'), __('Expected hours'), __('Worked hours'), __('Balance'), __('Vacation days'),
        ];
        // Title on row 1, column headers on row 2 directly above the data (no
        // stray line between the headers and the rows).
        $sheet->setCellValue('A1', __('Attendance report').' · '.__('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A1:Q1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }
        $sheet->getStyle('A2:Q2')->applyFromArray([
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
                $row['absent'], $row['excused'], $row['expected_hours'], $row['worked_hours'], $row['balance_hours'], $row['vacation_days'],
            ], null, 'A'.$rowIndex++);
        }
        foreach (range('A', 'Q') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

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

        $employees = Employee::with([
            'area', 'position', 'schedule.days',
            'attendances' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()])->orderBy('date'),
        ])->where('is_active', true)->orderBy('last_name')->get();

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle(__('Detail'));

        $headers = [__('Employee'), __('Document'), __('Date'), __('Check-in'), __('Check-out'), __('Worked hours'), __('Status')];

        $sheet->setCellValue('A1', __('Attendance detail').' · '.__('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A1:G1');
        $sheet->getStyle('A1')->applyFromArray(['font' => ['bold' => true, 'size' => 12]]);

        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 2], $header);
        }
        $sheet->getStyle('A2:G2')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
        ]);
        $sheet->freezePane('A3');

        $clamp = (bool) app_setting()->clamp_worked_hours;

        $rowIndex = 3;
        foreach ($employees as $employee) {
            $employeeMinutes = 0;

            foreach ($employee->attendances as $attendance) {
                $shift = ($clamp && $employee->schedule?->isFixed()) ? $employee->schedule->worksOn($attendance->date->dayOfWeek) : null;
                $dayMinutes = $attendance->workedMinutes($shift);
                $employeeMinutes += $dayMinutes;

                $sheet->fromArray([
                    $employee->full_name,
                    $employee->document_number,
                    $attendance->date->format('d/m/Y'),
                    $attendance->check_in ? substr($attendance->check_in, 0, 5) : '—',
                    $attendance->check_out ? substr($attendance->check_out, 0, 5) : '—',
                    $dayMinutes ? sprintf('%d:%02d', intdiv($dayMinutes, 60), $dayMinutes % 60) : '—',
                    __($attendance->status),
                ], null, 'A'.$rowIndex++);
            }

            // Worked-hours subtotal for the employee
            if ($employee->attendances->isNotEmpty()) {
                $sheet->setCellValue('E'.$rowIndex, __('Total'));
                $sheet->setCellValue('F'.$rowIndex, sprintf('%d:%02d', intdiv($employeeMinutes, 60), $employeeMinutes % 60));
                $sheet->getStyle('A'.$rowIndex.':G'.$rowIndex)->applyFromArray([
                    'font' => ['bold' => true],
                    'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => 'EEF2F8']],
                ]);
                $rowIndex++;
            }
        }

        foreach (range('A', 'G') as $column) {
            $sheet->getColumnDimension($column)->setAutoSize(true);
        }

        $file = tempnam(sys_get_temp_dir(), 'report_detail');
        (new Xlsx($spreadsheet))->save($file);

        return response()->download($file, 'attendance_detail_'.$from->format('Ymd').'_'.$to->format('Ymd').'.xlsx')
            ->deleteFileAfterSend(true);
    }

    /** Default range = current payroll cut-off period (configured in Settings) */
    private function range(Request $request): array
    {
        [$periodStart, $periodEnd] = current_period();

        return [
            $request->date('from') ?? $periodStart,
            $request->date('to') ?? $periodEnd->min(company_now()),
        ];
    }

    private function buildRows($from, $to)
    {
        $clamp = (bool) app_setting()->clamp_worked_hours;

        $employees = Employee::with([
            'area', 'position', 'site', 'schedule.days',
            'attendances' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
            'vacations' => fn ($q) => $q->where('status', 'APPROVED'),
        ])->where('is_active', true)->orderBy('last_name')->get();

        return $employees->map(function ($employee) use ($clamp) {
            $attendances = $employee->attendances;

            $minutes = 0;
            $expectedMinutes = 0;
            $lateMinutes = 0;
            foreach ($attendances as $attendance) {
                $weekday = $attendance->date->dayOfWeek;
                $shift = ($clamp && $employee->schedule?->isFixed()) ? $employee->schedule->worksOn($weekday) : null;
                $minutes += $attendance->workedMinutes($shift);

                // Expected minutes (the "jornada") only on days actually worked, so a
                // short day (late in / early out) shows as a deficit vs what was due.
                // Prefer the value frozen at check-in; fall back to a live compute for
                // older rows without a snapshot.
                if ($attendance->check_in && $attendance->check_out) {
                    $expectedMinutes += $attendance->expected_minutes ?? ($employee->schedule?->expectedMinutesFor($weekday) ?? 0);
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
            $balanceMinutes = $minutes - $expectedMinutes;

            return [
                'id' => $employee->id,
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
                'worked_hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
                'worked_minutes' => $minutes,
                'expected_hours' => sprintf('%d:%02d', intdiv($expectedMinutes, 60), $expectedMinutes % 60),
                'expected_minutes' => $expectedMinutes,
                'balance_minutes' => $balanceMinutes,
                'balance_hours' => sprintf('%s%d:%02d', $balanceMinutes < 0 ? '-' : '+', intdiv(abs($balanceMinutes), 60), abs($balanceMinutes) % 60),
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

        return redirect()->route('reports.sheet', ['employee' => $employee->id] + $request->only(['from', 'to', 'month']));
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

        $minutes = 0;
        $expectedMinutes = 0;
        $lateMinutes = 0;
        foreach ($attendances as $attendance) {
            $weekday = $attendance->date->dayOfWeek;
            $shift = ($clamp && $employee->schedule?->isFixed()) ? $employee->schedule->worksOn($weekday) : null;
            $minutes += $attendance->workedMinutes($shift);

            if ($attendance->check_in && $attendance->check_out) {
                $expectedMinutes += $attendance->expected_minutes ?? ($employee->schedule?->expectedMinutesFor($weekday) ?? 0);
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
        $balanceMinutes = $minutes - $expectedMinutes;

        $summary = [
            'days' => $attendances->whereIn('status', ['ON_TIME', 'LATE'])->count(),
            'on_time' => $attendances->where('status', 'ON_TIME')->count(),
            'late' => $attendances->where('status', 'LATE')->count(),
            'late_minutes' => $lateMinutes,
            'absent' => $attendances->where('status', 'ABSENT')->count(),
            'excused' => $attendances->where('status', 'EXCUSED')->count(),
            'hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
            'expected_hours' => sprintf('%d:%02d', intdiv($expectedMinutes, 60), $expectedMinutes % 60),
            'balance' => sprintf('%s%d:%02d', $balanceMinutes < 0 ? '-' : '+', intdiv(abs($balanceMinutes), 60), abs($balanceMinutes) % 60),
            'balance_minutes' => $balanceMinutes,
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
