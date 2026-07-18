<?php

namespace App\Console\Commands;

use App\Models\Attendance;
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
        $roll = mt_rand(1, 100);
        $tolerance = $employee->schedule->tolerance_minutes ?: 10;

        $start = Carbon::parse($date->toDateString().' '.$shift->start_time);
        $end = Carbon::parse($date->toDateString().' '.$shift->end_time);
        if ($end->lessThanOrEqualTo($start)) {
            $end->addDay(); // overnight shift
        }

        if ($roll <= 7) {
            $data = ['status' => 'ABSENT', 'check_in' => null, 'check_out' => null, 'method' => 'MANUAL'];
        } elseif ($roll <= 12) {
            $data = ['status' => 'EXCUSED', 'check_in' => null, 'check_out' => null, 'method' => 'MANUAL'];
        } else {
            $late = mt_rand(1, 100) <= 18;
            $checkIn = $late
                ? $start->copy()->addMinutes($tolerance + mt_rand(1, 45))
                : $start->copy()->addMinutes(mt_rand(-8, $tolerance));
            $checkOut = $end->copy()->addMinutes(mt_rand(-10, 25));

            $data = [
                'status' => $late ? 'LATE' : 'ON_TIME',
                'check_in' => $checkIn->format('H:i:s'),
                'check_out' => $checkOut->format('H:i:s'),
                'method' => 'FACIAL',
            ];
        }

        Attendance::updateOrCreate(
            ['employee_id' => $employee->id, 'date' => $date->toDateString()],
            $data
        );

        return true;
    }
}
