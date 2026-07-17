<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('company_name', 150)->default('MY COMPANY INC.');
            $table->string('tax_id', 11)->nullable();
            $table->string('address', 200)->nullable();
            $table->string('phone', 20)->nullable();
            $table->string('logo', 255)->nullable();
            $table->string('timezone', 64)->default('America/Lima')->comment('Company operational timezone; the server stores everything in UTC');
            $table->string('kiosk_token', 64)->nullable()->comment('Access token required to open the kiosk on authorized devices');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
