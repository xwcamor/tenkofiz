<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Two kinds of schedule so the catalog never explodes:
 *  - SHARED (is_shared = true): reusable templates in the catalog ("Morning shift"),
 *    assigned to many people by reference. Shown in the Schedules page and dropdowns.
 *  - PERSONALIZED (is_shared = false): unique to one person/period (a teacher's
 *    course schedule with its own async hours). Created inline from the employee
 *    form and HIDDEN from the catalog, so 500 teachers don't create 500 catalog rows.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('schedules', function (Blueprint $table) {
            $table->boolean('is_shared')->default(true)->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('schedules', fn (Blueprint $t) => $t->dropColumn('is_shared'));
    }
};
