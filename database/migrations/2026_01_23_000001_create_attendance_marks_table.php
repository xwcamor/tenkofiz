<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Raw punch log (ZKTeco-style): every successful kiosk mark is recorded here as
 * its own row, in addition to updating the day's Attendance (check-in/out). This
 * is purely additive — it does not change how attendance is computed today — but
 * it preserves the full sequence of punches per employee per day, which is the
 * foundation for later break detection and the "show the day's raw punches"
 * detail view.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendance_marks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('employee_id')->constrained()->cascadeOnDelete();
            $table->foreignId('attendance_id')->nullable()->constrained()->nullOnDelete();
            $table->timestamp('marked_at'); // moment of the punch (UTC; shown in company tz)
            $table->string('kind', 12);     // CHECK_IN | CHECK_OUT (room for BREAK_OUT/IN later)
            $table->string('method', 12);   // FACIAL | DNI
            $table->string('ip', 45)->nullable();
            $table->string('user_agent', 255)->nullable();
            $table->timestamps();

            $table->index(['employee_id', 'marked_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendance_marks');
    }
};
