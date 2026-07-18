<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            // Kiosk security is now per-site: each site (tablet) has its own access
            // token and its own paired device. One global token no longer works once
            // there are several sites — each kiosk must scope to and lock to its site.
            $table->string('kiosk_token', 64)->nullable()->after('is_active');
            $table->string('kiosk_device_hash', 64)->nullable()->after('kiosk_token');
            $table->string('kiosk_pair_code', 16)->nullable()->after('kiosk_device_hash');
            $table->timestamp('kiosk_pair_expires_at')->nullable()->after('kiosk_pair_code');
        });

        // Preserve the currently authorized tablet: move the existing global kiosk
        // token / paired device into the first site, so nothing stops working.
        $setting = DB::table('settings')->first();
        $firstSite = DB::table('sites')->orderBy('id')->first();

        if ($setting && $firstSite) {
            DB::table('sites')->where('id', $firstSite->id)->update([
                'kiosk_token' => $setting->kiosk_token ?? null,
                'kiosk_device_hash' => $setting->kiosk_device_hash ?? null,
                'kiosk_pair_code' => $setting->kiosk_pair_code ?? null,
                'kiosk_pair_expires_at' => $setting->kiosk_pair_expires_at ?? null,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('sites', function (Blueprint $table) {
            $table->dropColumn(['kiosk_token', 'kiosk_device_hash', 'kiosk_pair_code', 'kiosk_pair_expires_at']);
        });
    }
};
