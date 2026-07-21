<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Profiles become PER COMPANY (they were global/shared, which let one workspace
 * see or edit another's roles). Each company gets its own three base roles
 * (Administrator / Supervisor / Employee), users are repointed to their company's
 * copy, and any custom role is moved to its owner company (cloned if it was used
 * by more than one). Uniqueness of the name is now per company.
 */
return new class extends Migration
{
    private const BASE = [
        'Administrator' => 'ALL',
        'Supervisor' => ['employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage', 'kiosk'],
        'Employee' => [],
    ];

    private const MODULES = [
        'employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage',
        'users', 'profiles', 'schedules', 'holidays', 'audit_logs', 'settings', 'kiosk',
    ];

    private const DESC = [
        'Administrator' => 'Full access to the system',
        'Supervisor' => 'Manages attendance and approves requests',
        'Employee' => 'Views their attendance and requests vacations',
    ];

    public function up(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->foreignId('company_id')->nullable()->after('id')->constrained()->cascadeOnDelete();
        });

        // name was globally unique; make it unique PER company instead
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique('profiles_name_unique');
            $table->unique(['company_id', 'name']);
        });

        $now = now();

        // 1) Every company gets its own three base roles
        foreach (DB::table('companies')->pluck('id') as $cid) {
            foreach (self::BASE as $name => $perms) {
                $exists = DB::table('profiles')->where('company_id', $cid)->where('name', $name)->exists();
                if (!$exists) {
                    DB::table('profiles')->insert([
                        'company_id' => $cid,
                        'name' => $name,
                        'description' => self::DESC[$name],
                        'permissions' => json_encode($perms === 'ALL' ? self::MODULES : $perms),
                        'is_active' => true,
                        'is_system' => true,
                        'created_at' => $now,
                        'updated_at' => $now,
                    ]);
                }
            }
        }

        // 2) Repoint users on a BASE role to their own company's copy
        foreach (DB::table('users')->whereNotNull('company_id')->get() as $user) {
            $current = DB::table('profiles')->find($user->profile_id);
            if ($current && array_key_exists($current->name, self::BASE)) {
                $target = DB::table('profiles')->where('company_id', $user->company_id)->where('name', $current->name)->first();
                if ($target && $target->id != $user->profile_id) {
                    DB::table('users')->where('id', $user->id)->update(['profile_id' => $target->id]);
                }
            }
        }

        // 3) Custom roles (not base, still company-less) → move to their owner company (clone if shared)
        foreach (DB::table('profiles')->whereNull('company_id')->get() as $p) {
            if (array_key_exists($p->name, self::BASE)) {
                continue; // base handled below
            }
            $companies = DB::table('users')->where('profile_id', $p->id)->whereNotNull('company_id')->distinct()->pluck('company_id');
            if ($companies->isEmpty()) {
                continue; // orphan custom role: left company-less (invisible under scope)
            }
            $first = $companies->shift();
            DB::table('profiles')->where('id', $p->id)->update(['company_id' => $first]);
            foreach ($companies as $cid) {
                $cloneId = DB::table('profiles')->insertGetId([
                    'company_id' => $cid, 'name' => $p->name, 'description' => $p->description,
                    'permissions' => $p->permissions, 'is_active' => $p->is_active, 'is_system' => $p->is_system,
                    'created_at' => $now, 'updated_at' => $now,
                ]);
                DB::table('users')->where('profile_id', $p->id)->where('company_id', $cid)->update(['profile_id' => $cloneId]);
            }
        }

        // 4) Drop the old global base roles now that everyone points at a per-company copy
        DB::table('profiles')->whereNull('company_id')
            ->whereIn('name', array_keys(self::BASE))
            ->whereNotIn('id', DB::table('users')->whereNotNull('profile_id')->pluck('profile_id'))
            ->delete();
    }

    public function down(): void
    {
        Schema::table('profiles', function (Blueprint $table) {
            $table->dropUnique(['company_id', 'name']);
            $table->dropConstrainedForeignId('company_id');
            $table->unique('name');
        });
    }
};
