<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Repair: workspaces created before the "starter kit" were born with NO schedules,
 * which makes them unusable (a schedule is required to register employees). Give
 * every schedule-less company a base one (Mon-Sat 08:00-17:00, tolerance 10).
 */
return new class extends Migration
{
    public function up(): void
    {
        $companies = DB::table('companies')->whereNull('deleted_at')->pluck('id');

        foreach ($companies as $companyId) {
            if (DB::table('schedules')->where('company_id', $companyId)->exists()) {
                continue;
            }

            $scheduleId = DB::table('schedules')->insertGetId([
                'company_id' => $companyId,
                'name' => 'Horario General',
                'tolerance_minutes' => 5,
                'is_active' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            foreach ([1, 2, 3, 4, 5, 6] as $weekday) { // Monday..Saturday
                DB::table('schedule_days')->insert([
                    'schedule_id' => $scheduleId,
                    'weekday' => $weekday,
                    'start_time' => '08:00:00',
                    'end_time' => '17:00:00',
                ]);
            }
        }
    }

    public function down(): void
    {
        // Data repair: nothing sensible to undo (admins may have edited the schedule)
    }
};
