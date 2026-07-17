<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('cutoff_day')->nullable()->after('timezone')
                ->comment('Payroll cut-off day (e.g. 19 = period runs from the 20th to the 19th). Null = calendar month.');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('cutoff_day');
        });
    }
};
