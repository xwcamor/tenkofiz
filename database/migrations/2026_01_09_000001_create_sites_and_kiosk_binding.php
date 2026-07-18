<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Multi-site support: each site (sede) is a physical location with its own
 * kiosk link, and employees belong to a site. Also adds the kiosk device
 * binding columns to settings so the kiosk can be locked to one device
 * (one-time pairing code + a long-lived device cookie).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sites', function (Blueprint $table) {
            $table->id();
            $table->string('name', 100)->unique();
            $table->string('address', 200)->nullable();
            $table->string('timezone', 64)->nullable()->comment('Optional per-site timezone override');
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::table('employees', function (Blueprint $table) {
            $table->foreignId('site_id')->nullable()->after('position_id')->constrained('sites')->nullOnDelete();
            $table->index('site_id');
        });

        Schema::table('settings', function (Blueprint $table) {
            $table->string('kiosk_device_hash', 255)->nullable()->comment('Hash of the paired device secret; when set, only that device can open the kiosk');
            $table->string('kiosk_pair_code', 16)->nullable()->comment('One-time pairing code');
            $table->timestamp('kiosk_pair_expires_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('employees', function (Blueprint $table) {
            $table->dropConstrainedForeignId('site_id');
        });
        Schema::table('settings', function (Blueprint $table) {
            $table->dropColumn(['kiosk_device_hash', 'kiosk_pair_code', 'kiosk_pair_expires_at']);
        });
        Schema::dropIfExists('sites');
    }
};
