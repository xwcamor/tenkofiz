<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * SaaS Phase 2 + kiosk flow settings.
 *  - companies: suspension reason, soft delete with reason, plan modules and limits
 *    (modules = null means "all modules"; limits = null means unlimited).
 *  - audit_logs: company_id so each workspace only sees ITS audit trail (and the
 *    super-admin, with no acting workspace, sees the global security log).
 *  - settings: kiosk_verify_seconds — how long the camera tries to confirm the face.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->string('suspended_reason', 200)->nullable()->after('is_active');
            $table->json('modules')->nullable()->after('suspended_reason');
            $table->unsignedInteger('max_employees')->nullable()->after('modules');
            $table->unsignedInteger('max_sites')->nullable()->after('max_employees');
            $table->string('delete_reason', 300)->nullable()->after('max_sites');
            $table->softDeletes();
        });

        Schema::table('audit_logs', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained('companies')->nullOnDelete();
        });
        // Existing audit rows: attribute them to the row's author when possible,
        // else to the first (default) company.
        $firstCompanyId = DB::table('companies')->orderBy('id')->value('id');
        DB::table('audit_logs')->whereNull('company_id')->update([
            'company_id' => DB::raw('(select company_id from users where users.id = audit_logs.user_id)'),
        ]);
        if ($firstCompanyId) {
            DB::table('audit_logs')->whereNull('company_id')->update(['company_id' => $firstCompanyId]);
        }

        Schema::table('settings', function (Blueprint $table) {
            $table->unsignedTinyInteger('kiosk_verify_seconds')->default(15)->after('kiosk_face_threshold');
        });
    }

    public function down(): void
    {
        Schema::table('companies', function (Blueprint $table) {
            $table->dropColumn(['suspended_reason', 'modules', 'max_employees', 'max_sites', 'delete_reason', 'deleted_at']);
        });
        Schema::table('audit_logs', function (Blueprint $table) {
            $table->dropConstrainedForeignId('company_id');
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn('kiosk_verify_seconds');
        });
    }
};
