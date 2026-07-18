<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Recurring holiday rules that "Generate year" turns into concrete dates.
        // A rule is either FIXED (month + day) or MOVABLE relative to Easter Sunday
        // (easter_offset, e.g. -3 = Maundy Thursday, -2 = Good Friday).
        Schema::create('holiday_templates', function (Blueprint $table) {
            $table->id();
            $table->string('country', 2)->index();       // 'PE', 'CL', ...
            $table->unsignedTinyInteger('month')->nullable();
            $table->unsignedTinyInteger('day')->nullable();
            $table->smallInteger('easter_offset')->nullable();
            $table->string('name', 150);
            $table->timestamps();
        });

        // The company's country drives which template set "Generate year" uses.
        Schema::table('settings', function (Blueprint $table) {
            $table->string('country', 2)->default('PE')->after('timezone');
        });

        // Populate the built-in presets so existing installs get the templates from
        // `migrate` alone (no need to re-seed). Idempotent via firstOrCreate.
        foreach (array_keys(\App\Models\HolidayTemplate::COUNTRIES) as $country) {
            foreach (\App\Models\HolidayTemplate::presets($country) as [$month, $day, $offset, $name]) {
                \App\Models\HolidayTemplate::firstOrCreate(
                    ['country' => $country, 'month' => $month, 'day' => $day, 'easter_offset' => $offset, 'name' => $name]
                );
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('holiday_templates');
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('country');
        });
    }
};
