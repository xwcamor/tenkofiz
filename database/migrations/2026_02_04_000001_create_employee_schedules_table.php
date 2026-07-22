<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Schedule "vigencias": an employee's working schedule can change over time
 * (e.g. Jan–Jul one shift, Aug–Dec another; or one shift this cycle, another the
 * next). Each row assigns a schedule for a date range. The report/kiosk pick the
 * one effective on each date; with no rows, the employee keeps their base
 * schedule_id, so existing data is unaffected.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('employee_schedules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('schedule_id')->constrained();
            $table->date('effective_from');
            $table->date('effective_to')->nullable(); // null = open-ended
            $table->timestamps();

            $table->index(['employee_id', 'effective_from']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('employee_schedules');
    }
};
