<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes (with reason) for attendances, so a wrong mark can be removed
 * and later restored by an administrator. The DB-level unique on
 * (employee_id, date) is dropped — otherwise a soft-deleted row would keep
 * blocking a new mark for the same day; uniqueness among LIVE rows is still
 * guaranteed in code (firstOrNew / updateOrCreate respect the soft-delete scope).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('delete_reason', 300)->nullable();
            $table->dropUnique(['employee_id', 'date']);
            $table->index(['employee_id', 'date']);
        });
    }

    public function down(): void
    {
        Schema::table('attendances', function (Blueprint $table) {
            $table->dropIndex(['employee_id', 'date']);
            $table->unique(['employee_id', 'date']);
            $table->dropColumn(['deleted_at', 'delete_reason']);
        });
    }
};
