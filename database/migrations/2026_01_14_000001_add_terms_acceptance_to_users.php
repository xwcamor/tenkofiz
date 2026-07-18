<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Legal acceptance record: when, from which IP and which version of the
            // terms was accepted. If the terms version changes, users re-accept.
            $table->timestamp('terms_accepted_at')->nullable()->after('is_super_admin');
            $table->string('terms_version', 20)->nullable()->after('terms_accepted_at');
            $table->string('terms_ip', 45)->nullable()->after('terms_version');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['terms_accepted_at', 'terms_version', 'terms_ip']);
        });
    }
};
