<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Holiday;
use App\Models\Position;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent seeder: safe to run again on an already-populated database
 * (e.g. `php artisan migrate --seed` after an update). Existing rows are
 * left untouched; only missing base records are created.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Profile::firstOrCreate(['name' => 'Administrator'], [
            'description' => 'Full access to the system',
            'permissions' => array_keys(Profile::MODULES),
        ]);

        $supervisor = Profile::firstOrCreate(['name' => 'Supervisor'], [
            'description' => 'Manages attendance and approves requests',
            'permissions' => ['employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage', 'kiosk'],
        ]);

        $employeeProfile = Profile::firstOrCreate(['name' => 'Employee'], [
            'description' => 'Views their attendance and requests vacations',
            'permissions' => [],
        ]);

        // Default test users (one per role). Change these passwords in production.
        User::firstOrCreate(['email' => 'admin@test.com'], [
            'name' => 'Administrador',
            'password' => Hash::make('123456'),
            'profile_id' => $admin->id,
        ]);

        User::firstOrCreate(['email' => 'aprobador@test.com'], [
            'name' => 'Aprobador',
            'password' => Hash::make('123456'),
            'profile_id' => $supervisor->id,
        ]);

        User::firstOrCreate(['email' => 'empleado@test.com'], [
            'name' => 'Empleado',
            'password' => Hash::make('123456'),
            'profile_id' => $employeeProfile->id,
        ]);

        // Working days Monday-Saturday; Sunday off
        $morning = Schedule::firstOrCreate(['name' => 'Morning Shift'], ['tolerance_minutes' => 10]);
        $evening = Schedule::firstOrCreate(['name' => 'Evening Shift'], ['tolerance_minutes' => 10]);
        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $morning->days()->firstOrCreate(['weekday' => $weekday], ['start_time' => '08:00:00', 'end_time' => '17:00:00']);
            $evening->days()->firstOrCreate(['weekday' => $weekday], ['start_time' => '14:00:00', 'end_time' => '22:00:00']);
        }

        \App\Models\Site::firstOrCreate(['name' => 'Sede Principal'], ['address' => 'Av. Principal 123, Lima']);

        foreach (['Administration', 'Information Technology', 'Human Resources', 'Accounting', 'Operations'] as $area) {
            Area::firstOrCreate(['name' => $area]);
        }

        foreach (['Instructor', 'Administrative Assistant', 'Analyst', 'Coordinator', 'Support Technician'] as $position) {
            Position::firstOrCreate(['name' => $position]);
        }

        // Recurring holiday templates per country (PE + CL presets). "Generate year"
        // turns these into concrete dates, so companies customize their own country.
        foreach (array_keys(\App\Models\HolidayTemplate::COUNTRIES) as $country) {
            foreach (\App\Models\HolidayTemplate::presets($country) as [$month, $day, $offset, $name]) {
                \App\Models\HolidayTemplate::firstOrCreate(
                    ['country' => $country, 'month' => $month, 'day' => $day, 'easter_offset' => $offset, 'name' => $name]
                );
            }
        }

        // Generate the CURRENT year's holidays from the default country's templates,
        // so the module opens with a complete, consistent set (no half-filled list).
        $year = now()->year;
        foreach (\App\Models\HolidayTemplate::where('country', 'PE')->get() as $template) {
            if ($date = $template->dateForYear($year)) {
                Holiday::firstOrCreate(['date' => $date], ['name' => $template->name]);
            }
        }

        if (!Setting::query()->exists()) {
            Setting::create([
                'company_name' => 'MI EMPRESA S.A.C.',
                'tax_id' => '20000000001',
                'address' => 'Av. Principal 123, Lima',
                'phone' => '(01) 000-0000',
                'timezone' => 'America/Lima',
            ]);
        }
    }
}
