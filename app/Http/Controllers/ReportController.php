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
            __('Employee'), __('Document'), __('Area'), __('Position'),
            __('Worked days'), __('On time'), __('Late'), __('Late minutes'),
            __('Absences'), __('Excused'), __('Worked hours'), __('Vacation days'),
        ];
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        $sheet->getStyle('A1:L1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
        ]);
        $sheet->setCellValue('A2', __('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A2:L2');

        $rowIndex = 3;
        foreach ($rows as $row) {
            $sheet->fromArray([
                $row['employee'], $row['document'], $row['area'], $row['position'],
                $row['worked_days'], $row['on_time'], $row['late'], $row['late_minutes'],
                $row['absent'], $row['excused'], $row['worked_hours'], $row['vacation_days'],
            ], null, 'A'.$rowIndex++);
        }
        foreach (range('A', 'L') as $column) {
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
        foreach ($headers as $index => $header) {
            $sheet->setCellValue([$index + 1, 1], $header);
        }
        $sheet->getStyle('A1:G1')->applyFromArray([
            'font' => ['bold' => true, 'color' => ['rgb' => 'FFFFFF']],
            'fill' => ['fillType' => Fill::FILL_SOLID, 'startColor' => ['rgb' => '0F1B2D']],
        ]);
        $sheet->setCellValue('A2', __('Period: from :from to :to — Issued: :issued', [
            'from' => $from->format('d/m/Y'), 'to' => $to->format('d/m/Y'), 'issued' => company_now()->format('d/m/Y H:i'),
        ]));
        $sheet->mergeCells('A2:G2');

        $rowIndex = 3;
        foreach ($employees as $employee) {
            $employeeMinutes = 0;

            foreach ($employee->attendances as $attendance) {
                $dayMinutes = 0;
                if ($attendance->check_in && $attendance->check_out) {
                    $start = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                    $end = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out);
                    if ($end->lessThan($start)) {
                        $end->addDay(); // overnight shift
                    }
                    $dayMinutes = (int) $start->diffInMinutes($end);
                    $employeeMinutes += $dayMinutes;
                }

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
        $employees = Employee::with([
            'area', 'position', 'schedule.days',
            'attendances' => fn ($q) => $q->whereBetween('date', [$from->toDateString(), $to->toDateString()]),
            'vacations' => fn ($q) => $q->where('status', 'APPROVED'),
        ])->where('is_active', true)->orderBy('last_name')->get();

        return $employees->map(function ($employee) {
            $attendances = $employee->attendances;

            $minutes = 0;
            $lateMinutes = 0;
            foreach ($attendances as $attendance) {
                if ($attendance->check_in && $attendance->check_out) {
                    $start = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                    $end = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out);
                    if ($end->lessThan($start)) {
                        $end->addDay(); // overnight shift
                    }
                    $minutes += $start->diffInMinutes($end);
                }

                // Late minutes: how far past the scheduled start the check-in was
                if ($attendance->status === 'LATE' && $attendance->check_in) {
                    $shift = $employee->schedule?->worksOn($attendance->date->dayOfWeek);
                    if ($shift) {
                        $scheduled = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$shift->start_time);
                        $actual = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                        $lateMinutes += max(0, (int) $scheduled->diffInMinutes($actual, false));
                    }
                }
            }

            return [
                'id' => $employee->id,
                'employee' => $employee->full_name,
                'document' => $employee->document_type.' '.$employee->document_number,
                'document_number' => $employee->document_number,
                'area' => $employee->area?->name ?? '—',
                'position' => $employee->position?->name ?? '—',
                'worked_days' => $attendances->whereNotNull('check_in')->whereIn('status', ['ON_TIME', 'LATE'])->count(),
                'on_time' => $attendances->where('status', 'ON_TIME')->count(),
                'late' => $attendances->where('status', 'LATE')->count(),
                'late_minutes' => $lateMinutes,
                'absent' => $attendances->where('status', 'ABSENT')->count(),
                'excused' => $attendances->where('status', 'EXCUSED')->count(),
                'worked_hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
                'worked_minutes' => $minutes,
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

        $employee->load('schedule.days');

        $minutes = 0;
        $lateMinutes = 0;
        foreach ($attendances as $attendance) {
            if ($attendance->check_in && $attendance->check_out) {
                $start = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_in);
                $end = \Carbon\Carbon::parse($attendance->date->toDateString().' '.$attendance->check_out);
                if ($end->lessThan($start)) {
                    $end->addDay(); // overnight shift
                }
                $minutes += $start->diffInMinutes($end);
            }
            if ($attendance->status === 'LATE' && $attendance->check_in) {
                $shift = $employee->schedule?->worksOn($attendance->date->dayOfWeek);
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
            'hours' => sprintf('%d:%02d', intdiv($minutes, 60), $minutes % 60),
        ];

        $vacations = $employee->vacations()
            ->where('status', 'APPROVED')
            ->where(fn ($q) => $q->whereBetween('start_date', [$from, $to])->orWhereBetween('end_date', [$from, $to]))
            ->get();

        $justifications = $employee->justifications()
            ->whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->get();

        return view('reports.sheet', compact('employee', 'setting', 'attendances', 'summary', 'vacations', 'justifications', 'from', 'to', 'selectedMonth'));
    }
}
