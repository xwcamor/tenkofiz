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

        foreach (['Administration', 'Information Technology', 'Human Resources', 'Accounting', 'Operations'] as $area) {
            Area::firstOrCreate(['name' => $area]);
        }

        foreach (['Instructor', 'Administrative Assistant', 'Analyst', 'Coordinator', 'Support Technician'] as $position) {
            Position::firstOrCreate(['name' => $position]);
        }

        // Fixed-date national holidays (Peru)
        $year = now()->year;
        $holidays = [
            ["$year-01-01", 'Año Nuevo'],
            ["$year-05-01", 'Día del Trabajo'],
            ["$year-06-29", 'San Pedro y San Pablo'],
            ["$year-07-28", 'Fiestas Patrias'],
            ["$year-07-29", 'Fiestas Patrias'],
            ["$year-08-30", 'Santa Rosa de Lima'],
            ["$year-10-08", 'Combate de Angamos'],
            ["$year-11-01", 'Todos los Santos'],
            ["$year-12-08", 'Inmaculada Concepción'],
            ["$year-12-09", 'Batalla de Ayacucho'],
            ["$year-12-25", 'Navidad'],
        ];
        foreach ($holidays as [$date, $name]) {
            Holiday::firstOrCreate(['date' => $date], ['name' => $name]);
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
