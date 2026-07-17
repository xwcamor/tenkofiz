<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Per-weekday working hours. A missing row = non-working day for that schedule.
        // end_time earlier than start_time means the shift crosses midnight.
        Schema::create('schedule_days', function (Blueprint $table) {
            $table->id();
            $table->foreignId('schedule_id')->constrained('schedules')->cascadeOnDelete();
            $table->unsignedTinyInteger('weekday')->comment('0=Sunday ... 6=Saturday');
            $table->time('start_time');
            $table->time('end_time');
            $table->unique(['schedule_id', 'weekday']);
        });

        // Legacy single start/end become the template for Monday-Saturday
        // (the old behavior treated only Sunday as non-working).
        foreach (DB::table('schedules')->get() as $schedule) {
            if (!$schedule->start_time || !$schedule->end_time) {
                continue;
            }
            foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
                DB::table('schedule_days')->insert([
                    'schedule_id' => $schedule->id,
                    'weekday' => $weekday,
                    'start_time' => $schedule->start_time,
                    'end_time' => $schedule->end_time,
                ]);
            }
        }

        // The per-day rows are now the source of truth
        Schema::table('schedules', function (Blueprint $table) {
            $table->time('start_time')->nullable()->change();
            $table->time('end_time')->nullable()->change();
        });

        // Annual vacation allowance (Peru: 30 calendar days per year by default)
        Schema::table('employees', function (Blueprint $table) {
            $table->unsignedTinyInteger('vacation_days_per_year')->default(30)->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('schedule_days');
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('vacation_days_per_year');
        });
    }
};
