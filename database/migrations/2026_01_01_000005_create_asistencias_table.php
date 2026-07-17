<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('asistencias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empleado_id')->constrained('empleados')->cascadeOnDelete();
            $table->date('fecha');
            $table->time('hora_entrada')->nullable();
            $table->time('hora_salida')->nullable();
            $table->enum('estado', ['PUNTUAL', 'TARDANZA', 'FALTA', 'JUSTIFICADO'])->default('PUNTUAL');
            $table->enum('metodo', ['FACIAL', 'MANUAL'])->default('FACIAL');
            $table->decimal('similitud', 5, 4)->nullable()->comment('Distancia euclidiana del match facial');
            $table->string('observacion', 200)->nullable();
            $table->timestamps();
            $table->unique(['empleado_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('asistencias');
    }
};
