<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Justification;
use App\Models\Position;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\User;
use App\Models\Vacation;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Demo data: 8 employees with 30 days of simulated attendance.
 * Run with: php artisan db:seed --class=DemoSeeder
 * (Nothing is deleted: it only adds on top of existing data)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $schedule = Schedule::first() ?? Schedule::create(['name' => 'Morning Shift', 'start_time' => '08:00:00', 'end_time' => '17:00:00', 'tolerance_minutes' => 10]);
        $areas = Area::pluck('id')->all() ?: [Area::create(['name' => 'Operations'])->id];
        $positions = Position::pluck('id')->all() ?: [Position::create(['name' => 'Assistant'])->id];

        $people = [
            ['GARCÍA TORRES', 'MARÍA ELENA', '40111222'],
            ['QUISPE HUAMÁN', 'JOSÉ LUIS', '40222333'],
            ['RODRÍGUEZ VEGA', 'ANA PAULA', '40333444'],
            ['FERNÁNDEZ ROJAS', 'CARLOS DANIEL', '40444555'],
            ['MAMANI CONDORI', 'ROSA ISABEL', '40555666'],
            ['LÓPEZ CASTILLO', 'JORGE ANTONIO', '40666777'],
            ['SÁNCHEZ PAREDES', 'LUCÍA FERNANDA', '40777888'],
            ['TORRES MENDOZA', 'PEDRO PABLO', '40888999'],
        ];

        $holidays = Holiday::pluck('date')->map(fn ($date) => $date->toDateString())->all();
        $employees = [];

        foreach ($people as [$lastName, $firstName, $documentNumber]) {
            $employees[] = Employee::firstOrCreate(
                ['document_number' => $documentNumber],
                [
                    'first_name' => $firstName,
                    'last_name' => $lastName,
                    'schedule_id' => $schedule->id,
                    'area_id' => $areas[array_rand($areas)],
                    'position_id' => $positions[array_rand($positions)],
                    'hire_date' => now()->subMonths(rand(6, 36))->toDateString(),
                ]
            );
        }

        // 30 days of simulated attendance (skipping Sundays and holidays)
        foreach ($employees as $employee) {
            for ($i = 30; $i >= 1; $i--) {
                $day = now()->subDays($i);
                if ($day->dayOfWeek === 0 || in_array($day->toDateString(), $holidays, true)) {
                    continue;
                }

                $roll = rand(1, 100);
                if ($roll <= 78) {          // on time
                    $checkIn = sprintf('07:%02d:%02d', rand(45, 59), rand(0, 59));
                    $status = 'ON_TIME';
                } elseif ($roll <= 93) {    // late
                    $checkIn = sprintf('08:%02d:%02d', rand(11, 45), rand(0, 59));
                    $status = 'LATE';
                } else {                    // absent
                    Attendance::firstOrCreate(
                        ['employee_id' => $employee->id, 'date' => $day->toDateString()],
                        ['status' => 'ABSENT', 'method' => 'MANUAL', 'note' => 'Absence generated automatically (demo)']
                    );
                    continue;
                }

                Attendance::firstOrCreate(
                    ['employee_id' => $employee->id, 'date' => $day->toDateString()],
                    [
                        'check_in' => $checkIn,
                        'check_out' => sprintf('17:%02d:%02d', rand(0, 35), rand(0, 59)),
                        'status' => $status,
                        'method' => 'FACIAL',
                        'similarity' => rand(28, 48) / 100,
                    ]
                );
            }
        }

        // Sample vacation requests and a justification
        Vacation::firstOrCreate(
            ['employee_id' => $employees[0]->id, 'start_date' => now()->addDays(10)->toDateString()],
            ['end_date' => now()->addDays(16)->toDateString(), 'days' => 7, 'status' => 'PENDING', 'reason' => 'Family vacation']
        );
        Vacation::firstOrCreate(
            ['employee_id' => $employees[1]->id, 'start_date' => now()->addDays(20)->toDateString()],
            ['end_date' => now()->addDays(27)->toDateString(), 'days' => 8, 'status' => 'APPROVED', 'reason' => 'Annual leave', 'approved_by' => User::first()?->id]
        );
        Justification::firstOrCreate(
            ['employee_id' => $employees[2]->id, 'date' => now()->subDays(3)->toDateString()],
            ['reason' => 'Medical appointment — certificate attached', 'status' => 'PENDING']
        );

        // Demo user with the Employee profile (linked to the first employee)
        $employeeProfile = Profile::firstOrCreate(
            ['name' => 'Employee'],
            ['description' => 'Views their attendance and requests vacations', 'permissions' => []]
        );
        $demoUser = User::firstOrCreate(
            ['email' => 'empleado@demo.test'],
            ['name' => 'María Elena García Torres', 'password' => Hash::make('demo1234'), 'profile_id' => $employeeProfile->id]
        );
        $employees[0]->update(['user_id' => $demoUser->id]);

        $this->command?->info('Demo loaded: 8 employees, ~1 month of attendance, sample vacations and a justification.');
        $this->command?->info('Demo user (Employee profile): empleado@demo.test / demo1234');
    }
}
