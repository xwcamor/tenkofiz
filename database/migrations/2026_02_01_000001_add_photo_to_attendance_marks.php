<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Evidence photo per punch (not just one per day). Only document/DNI marks carry
 * one; facial marks store none. This lets an admin verify each mark individually
 * — entry, break out, break in, check-out — and spot which one used a bad photo.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->string('photo')->nullable()->after('method');
        });
    }

    public function down(): void
    {
        Schema::table('attendance_marks', function (Blueprint $table) {
            $table->dropColumn('photo');
        });
    }
};
