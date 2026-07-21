<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\AttendanceMark;
use App\Models\Employee;
use App\Models\Holiday;
use Carbon\Carbon;
use Illuminate\Console\Command;

class SeedDemoAttendances extends Command
{
    /**
     * Examples:
     *   php artisan attendances:seed-demo                       (all active employees)
     *   php artisan attendances:seed-demo --document=47019237   (a single employee)
     *   php artisan attendances:seed-demo --from=2026-01-01 --to=2026-06-30
     */
    protected $signature = 'attendances:seed-demo
        {--document= : Only this employee (document number); default = all active employees}
        {--from= : Start date YYYY-MM-DD; default = January 1st of the current year}
        {--to= : End date YYYY-MM-DD; default = the end of the current cut-off period}';

    protected $description = 'Generate realistic demo attendance (present / late / absent / excused) respecting each schedule, holidays and vacations';

    public function handle(): int
    {
        $from = $this->option('from')
            ? Carbon::parse($this->option('from'))
            : Carbon::create((int) company_now()->year, 1, 1);

        // Default end = current cut-off period end, never in the future
        [, $periodEnd] = current_period();
        $to = $this->option('to') ? Carbon::parse($this->option('to')) : $periodEnd;
        $to = $to->min(company_now());

        if ($to->lessThan($from)) {
            $this->error('The end date is before the start date.');

            return self::FAILURE;
        }

        $employees = Employee::with('schedule.days')->where('is_active', true)
            ->when($this->option('document'), fn ($q) => $q->where('document_number', $this->option('document')))
            ->get();

        if ($employees->isEmpty()) {
            $this->error('No active employees matched (check the document number).');

            return self::FAILURE;
        }

        $this->info("Generating attendance from {$from->toDateString()} to {$to->toDateString()} for {$employees->count()} employee(s)...");

        // Cache holidays in the range for speed
        $holidays = Holiday::whereBetween('date', [$from->toDateString(), $to->toDateString()])
            ->pluck('date')->map(fn ($d) => Carbon::parse($d)->toDateString())->flip();

        $created = 0;
        $bar = $this->output->createProgressBar($employees->count());
        $bar->start();

        foreach ($employees as $employee) {
            if (!$employee->schedule) {
                $bar->advance();
                continue;
            }

            for ($date = $from->copy(); $date->lessThanOrEqualTo($to); $date->addDay()) {
                $day = $date->toDateString();

                // Skip holidays, non-working weekdays and approved vacation days
                if ($holidays->has($day)) {
                    continue;
                }
                $shift = $employee->schedule->worksOn($date->dayOfWeek);
                if (!$shift) {
                    continue;
                }
                if ($employee->onVacation($day)) {
                    continue;
                }

                $created += $this->makeDay($employee, $shift, $date) ? 1 : 0;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);
        $this->info("Done. {$created} attendance record(s) created/updated.");

        return self::SUCCESS;
    }

    /** One realistic day: ~88% present (of which ~18% late), ~7% absent, ~5% excused */
    private function makeDay(Employee $employee, $shift, Carbon $date): bool
    {
        $schedule = $employee->schedule;
        $flexible = $schedule->isFlexible();
        $roll = mt_rand(1, 100);
        $tolerance = $schedule->tolerance_minutes ?: 10;
        $day = $date->toDateString();

        $start = Carbon::parse($day.' '.$shift->start_time);
        $end = Carbon::parse($day.' '.$shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // overnight shift
        }

        // Frozen fields (§1.4 freeze): what this day was DUE, immune to later
        // schedule changes. Flexible schedules have no shift bounds to clamp to.
        $frozen = [
            'expected_minutes' => $schedule->expectedMinutesFor($date->dayOfWeek),
            'shift_start' => $flexible ? null : $shift->start_time,
            'shift_end' => $flexible ? null : $shift->end_time,
        ];

        if ($roll <= 7) {
            $data = ['status' => 'ABSENT', 'check_in' => null, 'check_out' => null, 'break_out' => null, 'break_in' => null, 'method' => 'MANUAL'] + $frozen;
        } elseif ($roll <= 12) {
            $data = ['status' => 'EXCUSED', 'check_in' => null, 'check_out' => null, 'break_out' => null, 'break_in' => null, 'method' => 'MANUAL'] + $frozen;
        } else {
            // A flexible schedule never marks tardiness (no fixed start to be late against)
            $late = !$flexible && mt_rand(1, 100) <= 18;
            $checkIn = $late
                ? $start->copy()->addMinutes($tolerance + mt_rand(1, 45))
                : $start->copy()->addMinutes(mt_rand(-8, $tolerance));
            $checkOut = $end->copy()->addMinutes(mt_rand(-10, 25));

            // Break (only when the workspace controls breaks): a midday pause of
            // ~30-60 min, and ~15% of days deliberately exceed the limit so the
            // analysis report has something to flag.
            $breakOut = $breakIn = null;
            $limit = (int) (app_setting()->break_limit_minutes ?? 60);
            if (app_setting()->kiosk_breaks_enabled && mt_rand(1, 100) <= 85) {
                $mid = $checkIn->copy()->addMinutes((int) ($checkIn->diffInMinutes($checkOut) / 2) - 20);
                $duration = mt_rand(1, 100) <= 15 ? ($limit ?: 60) + mt_rand(10, 40) : mt_rand(30, max(31, $limit ?: 60));
                $breakOut = $mid;
                $breakIn = $mid->copy()->addMinutes($duration);
            }

            $data = [
                'status' => $late ? 'LATE' : 'ON_TIME',
                'check_in' => $checkIn->format('H:i:s'),
                'check_out' => $checkOut->format('H:i:s'),
                'break_out' => $breakOut?->format('H:i:s'),
                'break_in' => $breakIn?->format('H:i:s'),
                'method' => 'FACIAL',
            ] + $frozen;
        }

        $attendance = Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $day],
            $data
        );

        $this->syncMarks($attendance, $employee, $date);

        return true;
    }

    /**
     * Rebuild the raw punch log (ZKTeco-style) for this day from the summary
     * times, so the break-analysis view and the expandable log have real data.
     * Idempotent: wipes and re-creates on every run.
     */
    private function syncMarks(Attendance $attendance, Employee $employee, Carbon $date): void
    {
        AttendanceMark::where('attendance_id', $attendance->id)->delete();

        if (!$attendance->check_in) {
            return; // absent / excused: no punches
        }

        $day = $date->toDateString();
        $punches = [['CHECK_IN', $attendance->check_in]];
        if ($attendance->break_out) {
            $punches[] = ['BREAK_OUT', $attendance->break_out];
        }
        if ($attendance->break_in) {
            $punches[] = ['BREAK_IN', $attendance->break_in];
        }
        if ($attendance->check_out) {
            $punches[] = ['CHECK_OUT', $attendance->check_out];
        }

        $prev = null;
        foreach ($punches as [$kind, $time]) {
            $at = Carbon::parse($day.' '.$time);
            if ($prev && $at->lessThan($prev)) {
                $at->addDay(); // overnight sequence
            }
            $prev = $at;

            AttendanceMark::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'marked_at' => $at,
                'kind' => $kind,
                'method' => 'FACIAL',
            ]);
        }
    }
}
