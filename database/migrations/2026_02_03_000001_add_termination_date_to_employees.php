<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Fecha de cese: the day an employee stopped working here. Lets reports derive
 * absences ONLY within the employment window (hire → termination), so a worker
 * who left doesn't keep accruing "faltas" forever. Null = still employed.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->date('termination_date')->nullable()->after('hire_date');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropColumn('termination_date');
        });
    }
};
