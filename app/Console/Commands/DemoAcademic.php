<?php

namespace App\Console\Commands;

use App\Models\Attendance;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\Scopes\CompanyScope;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Hash;

/**
 * Reproduces a real academic "distribución de carga horaria": one instructor,
 * TWO periods with DIFFERENT courses/dates (schedule vigencias), each with
 * presencial hours (marked) + asynchronous credited hours (not marked). Lets you
 * see how the system handles an academic-institute case end to end.
 */
class DemoAcademic extends Command
{
    protected $signature = 'demo:academic';

    protected $description = 'Seed an educational institute demo: 1 instructor, 2 periods (vigencias) with presencial + async hours';

    public function handle(): int
    {
        $company = Company::withoutGlobalScopes()->firstWhere('name', 'Instituto Demo')
            ?? Company::create(['name' => 'Instituto Demo', 'tax_id' => '20999999999', 'is_active' => true]);

        CompanyScope::actingAs($company->id, function () use ($company) {
            // Education flag ON so async hours are counted
            Setting::firstOrCreate(['company_id' => $company->id])->update([
                'timezone' => 'America/Lima', 'cutoff_day' => 19, 'async_hours_enabled' => true,
            ]);

            $admin = Profile::firstOrCreate(['name' => 'Administrator', 'company_id' => $company->id],
                ['permissions' => array_keys(Profile::MODULES), 'is_system' => true]);
            $admin->update(['permissions' => array_keys(Profile::MODULES)]); // ensure full access on re-run

            User::firstOrCreate(['email' => 'instituto@test.com'], [
                'name' => 'Admin Instituto', 'password' => Hash::make('123456'),
                'profile_id' => $admin->id, 'company_id' => $company->id,
            ]);

            $site = Site::firstOrCreate(['name' => 'Campus Central'], ['timezone' => 'America/Lima', 'is_active' => true]);

            // Base = a SHARED fallback schedule (catalog). The two course schedules are
            // PERSONALIZED (per-person, out of the catalog): presencial Mon/Tue/Wed
            // 09:00-13:00 (4h) + 60 async min/day → 5h/day × 3 = 15h/week (Total 15:00).
            $base = $this->makeSchedule('General Instituto (Lu-Vi)', shared: true, weekdays: [1, 2, 3, 4, 5], async: 0);
            $ia202 = $this->makeSchedule('IA 202 (2026-10)');
            $sw205 = $this->makeSchedule('SOFTWARE 205 (2025-20)');

            $emp = Employee::firstOrCreate(['document_number' => '44881122'], [
                'first_name' => 'LUIS ALBERTO', 'last_name' => 'RAMÍREZ SOTO',
                'site_id' => $site->id, 'schedule_id' => $base->id,
                'contract_type' => 'part_time', 'hire_date' => '2025-08-11',
                'face_descriptor' => json_encode([array_fill(0, 128, 0.1)]),
            ]);
            $emp->update(['schedule_id' => $base->id]);

            // Vigencias exactly as the two sheets
            $emp->scheduleAssignments()->delete();
            $emp->scheduleAssignments()->createMany([
                ['schedule_id' => $sw205->id, 'effective_from' => '2025-08-11', 'effective_to' => '2025-11-29'],
                ['schedule_id' => $ia202->id, 'effective_from' => '2026-02-16', 'effective_to' => '2026-06-07'],
            ]);

            // A few weeks of presencial marks (09:0x-13:0x) in the 2026-10 period
            $this->seedMarks($emp, Carbon::parse('2026-02-16'), Carbon::parse('2026-03-13'));

            $this->info("Instituto Demo listo. Login: instituto@test.com / 123456");
            $this->info("Instructor LUIS ALBERTO RAMÍREZ SOTO (DNI 44881122) · ficha 2026-10: /reports/sheet/{$emp->getRouteKey()}?from=2026-02-16&to=2026-06-07");
        });

        return self::SUCCESS;
    }

    private function makeSchedule(string $name, bool $shared = false, array $weekdays = [1, 2, 3], int $async = 60): Schedule
    {
        $s = Schedule::firstOrCreate(['name' => $name], [
            'is_shared' => $shared, // course schedules are personalized (out of the catalog)
            'type' => Schedule::TYPE_FIXED, 'tolerance_minutes' => 10, 'async_minutes_per_day' => $async,
        ]);
        $s->update(['is_shared' => $shared, 'async_minutes_per_day' => $async]);
        if ($s->days()->count() === 0) {
            foreach ($weekdays as $wd) {
                $s->days()->create(['weekday' => $wd, 'start_time' => '09:00', 'end_time' => '13:00']);
            }
        }

        return $s;
    }

    private function seedMarks(Employee $emp, Carbon $from, Carbon $to): void
    {
        for ($d = $from->copy(); $d->lte($to); $d->addDay()) {
            if (!in_array($d->dayOfWeek, [1, 2, 3], true)) {
                continue; // only Mon/Tue/Wed
            }
            $late = $d->dayOfWeek === 3; // make Wednesdays a bit late, for realism
            Attendance::updateOrCreate(
                ['employee_id' => $emp->id, 'date' => $d->toDateString()],
                [
                    'check_in' => $late ? '09:14' : '09:03',
                    'check_out' => '13:05',
                    'status' => $late ? 'LATE' : 'ON_TIME',
                    'method' => 'FACIAL',
                    'expected_minutes' => 240, // 4h presencial envelope
                ]
            );
        }
    }
}
