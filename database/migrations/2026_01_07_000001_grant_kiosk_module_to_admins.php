<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * 'kiosk' became its own profile module (its sidebar link used to be tied to
 * the 'settings' module). Preserve current behaviour: any profile that could
 * already see the kiosk (had 'settings') keeps the link by gaining 'kiosk'.
 * Administrators can now grant/revoke it per profile from the Profiles screen.
 */
return new class extends Migration
{
    public function up(): void
    {
        foreach (DB::table('profiles')->get() as $profile) {
            $permissions = json_decode($profile->permissions ?? '[]', true) ?: [];
            if (in_array('settings', $permissions, true) && !in_array('kiosk', $permissions, true)) {
                $permissions[] = 'kiosk';
                DB::table('profiles')->where('id', $profile->id)
                    ->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }

    public function down(): void
    {
        foreach (DB::table('profiles')->get() as $profile) {
            $permissions = json_decode($profile->permissions ?? '[]', true) ?: [];
            if (($key = array_search('kiosk', $permissions, true)) !== false) {
                unset($permissions[$key]);
                DB::table('profiles')->where('id', $profile->id)
                    ->update(['permissions' => json_encode(array_values($permissions))]);
            }
        }
    }
};
