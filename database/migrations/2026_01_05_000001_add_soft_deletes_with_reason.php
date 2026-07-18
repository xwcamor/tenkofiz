<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Soft deletes for the records that carry history: deleting an employee,
 * user or justification now only hides it (with a mandatory reason), so an
 * administrator can review and restore it later. The DB-level unique
 * constraints on employees.document_number and users.email move to
 * validation (scoped to non-deleted rows), otherwise a deleted employee
 * would block re-registering the same document.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('delete_reason', 300)->nullable();
            $table->dropUnique(['document_number']);
            $table->index('document_number');
        });

        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('delete_reason', 300)->nullable();
            $table->dropUnique(['email']);
            $table->index('email');
        });

        Schema::table('justifications', function (Blueprint $table) {
            $table->softDeletes();
            $table->string('delete_reason', 300)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropIndex(['document_number']);
            $table->unique('document_number');
            $table->dropColumn(['deleted_at', 'delete_reason']);
        });

        Schema::table('users', function (Blueprint $table) {
            $table->dropIndex(['email']);
            $table->unique('email');
            $table->dropColumn(['deleted_at', 'delete_reason']);
        });

        Schema::table('justifications', function (Blueprint $table) {
            $table->dropColumn(['deleted_at', 'delete_reason']);
        });
    }
};
