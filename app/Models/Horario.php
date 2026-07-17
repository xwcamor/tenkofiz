<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Horario extends Model
{
    protected $fillable = ['nombre', 'hora_entrada', 'hora_salida', 'tolerancia_min', 'activo'];

    protected $casts = ['activo' => 'boolean'];

    public function empleados()
    {
        return $this->hasMany(Empleado::class);
    }
}
