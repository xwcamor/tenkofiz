<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            // Default language of the workspace: applies to everyone in the company
            // who has not picked a personal language, and to its kiosks.
            $table->string('locale', 5)->default('es')->after('country');
        });
    }

    public function down(): void
    {
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('locale');
        });
    }
};
