<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Multiple paired kiosk devices (tablets) per site instead of a single one.
 * Each row is one bound tablet (named, revocable, with a "last seen"). The old
 * single sites.kiosk_device_hash is migrated into a row and left dormant.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kiosk_devices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('company_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('site_id')->constrained()->cascadeOnDelete();
            $table->string('name')->nullable();
            $table->string('device_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamps();
            $table->index('site_id');
        });

        // Carry the existing single paired device (if any) into the new table
        foreach (DB::table('sites')->whereNotNull('kiosk_device_hash')->get() as $site) {
            DB::table('kiosk_devices')->insert([
                'company_id' => $site->company_id,
                'site_id' => $site->id,
                'name' => 'Tablet 1',
                'device_hash' => $site->kiosk_device_hash,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('kiosk_devices');
    }
};
