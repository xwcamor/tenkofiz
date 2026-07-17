<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ajuste extends Model
{
    protected $fillable = ['empresa', 'ruc', 'direccion', 'telefono', 'logo'];

    /** Devuelve la fila única de ajustes (la crea si no existe) */
    public static function obtener(): self
    {
        return static::firstOrCreate(['id' => 1]);
    }
}
