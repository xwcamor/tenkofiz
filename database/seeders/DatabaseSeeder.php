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

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Profile::create([
            'name' => 'Administrator',
            'description' => 'Full access to the system',
            'permissions' => array_keys(Profile::MODULES),
        ]);

        Profile::create([
            'name' => 'Supervisor',
            'description' => 'Manages attendance and approves requests',
            'permissions' => ['employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage'],
        ]);

        Profile::create([
            'name' => 'Employee',
            'description' => 'Views their attendance and requests vacations',
            'permissions' => [],
        ]);

        User::create([
            'name' => 'Carlos Alberto Morales Larrañaga',
            'email' => 'admin@sistema.test',
            'password' => Hash::make('admin123'),
            'profile_id' => $admin->id,
        ]);

        // Working days Monday-Saturday; Sunday off
        $morning = Schedule::create(['name' => 'Morning Shift', 'tolerance_minutes' => 10]);
        $evening = Schedule::create(['name' => 'Evening Shift', 'tolerance_minutes' => 10]);
        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $morning->days()->create(['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '17:00:00']);
            $evening->days()->create(['weekday' => $weekday, 'start_time' => '14:00:00', 'end_time' => '22:00:00']);
        }

        foreach (['Administration', 'Information Technology', 'Human Resources', 'Accounting', 'Operations'] as $area) {
            Area::create(['name' => $area]);
        }

        foreach (['Instructor', 'Administrative Assistant', 'Analyst', 'Coordinator', 'Support Technician'] as $position) {
            Position::create(['name' => $position]);
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
            Holiday::create(['date' => $date, 'name' => $name]);
        }

        Setting::create([
            'company_name' => 'MI EMPRESA S.A.C.',
            'tax_id' => '20000000001',
            'address' => 'Av. Principal 123, Lima',
            'phone' => '(01) 000-0000',
            'timezone' => 'America/Lima',
        ]);
    }
}
