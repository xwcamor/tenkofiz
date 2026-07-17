<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Indexes that keep the frequent queries fast once the tables hold
 * tens/hundreds of thousands of rows (lists, filters, balances, audits).
 * SQLite does not index foreign keys automatically, so the FK columns
 * used in joins/filters get explicit indexes too.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->index(['last_name', 'first_name']);  // default ordering + name search
            $table->index('is_active');                  // active-only lists (kiosk, filters)
            $table->index('area_id');
            $table->index('schedule_id');
            $table->index('user_id');
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->index(['employee_id', 'status']);    // per-employee summaries by status
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->index('created_at');                 // latest-first listing
            $table->index(['module', 'action']);         // audit filters
            $table->index('user_id');
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->index(['employee_id', 'status', 'start_date']); // balance per year
        });

        Schema::table('justifications', function (Blueprint $table) {
            $table->index(['employee_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->index('name');                       // default ordering + search
            $table->index('profile_id');
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['last_name', 'first_name']);
            $table->dropIndex(['is_active']);
            $table->dropIndex(['area_id']);
            $table->dropIndex(['schedule_id']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'status']);
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['module', 'action']);
            $table->dropIndex(['user_id']);
        });

        Schema::table('vacations', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'status', 'start_date']);
        });

        Schema::table('justifications', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'date']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['name']);
            $table->dropIndex(['profile_id']);
        });
    }
};
