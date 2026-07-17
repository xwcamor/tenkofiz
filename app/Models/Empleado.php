<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Empleado extends Model
{
    protected $fillable = [
        'user_id', 'horario_id', 'area_id', 'cargo_id', 'dni', 'nombres',
        'apellidos', 'fecha_ingreso', 'descriptor_facial', 'activo',
    ];

    protected $casts = [
        'fecha_ingreso' => 'date',
        'activo' => 'boolean',
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function horario()
    {
        return $this->belongsTo(Horario::class);
    }

    public function area()
    {
        return $this->belongsTo(Area::class);
    }

    public function cargo()
    {
        return $this->belongsTo(Cargo::class);
    }

    public function asistencias()
    {
        return $this->hasMany(Asistencia::class);
    }

    public function vacaciones()
    {
        return $this->hasMany(Vacacion::class);
    }

    public function justificaciones()
    {
        return $this->hasMany(Justificacion::class);
    }

    public function getNombreCompletoAttribute(): string
    {
        return "{$this->apellidos}, {$this->nombres}";
    }

    public function tieneRostro(): bool
    {
        return !empty($this->descriptor_facial);
    }

    /** ¿Tiene vacaciones aprobadas que cubren la fecha dada? */
    public function deVacaciones(string $fecha): bool
    {
        return $this->vacaciones()
            ->where('estado', 'APROBADO')
            ->whereDate('fecha_inicio', '<=', $fecha)
            ->whereDate('fecha_fin', '>=', $fecha)
            ->exists();
    }
}
