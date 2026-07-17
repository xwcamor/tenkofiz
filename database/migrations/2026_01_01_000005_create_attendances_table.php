<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('attendances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('employee_id')->constrained('employees')->cascadeOnDelete();
            $table->date('date')->comment('Business date in the company timezone');
            $table->time('check_in')->nullable()->comment('Wall-clock time in the company timezone');
            $table->time('check_out')->nullable()->comment('Wall-clock time in the company timezone');
            $table->enum('status', ['ON_TIME', 'LATE', 'ABSENT', 'EXCUSED'])->default('ON_TIME');
            $table->enum('method', ['FACIAL', 'MANUAL'])->default('FACIAL');
            $table->decimal('similarity', 5, 4)->nullable()->comment('Euclidean distance of the facial match');
            $table->string('note', 200)->nullable();
            $table->string('ip', 45)->nullable()->comment('Device IP that recorded the mark (kiosk audit)');
            $table->string('user_agent', 255)->nullable()->comment('Device user agent that recorded the mark (kiosk audit)');
            $table->timestamps();
            $table->unique(['employee_id', 'date']);
            $table->index(['date', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('attendances');
    }
};
