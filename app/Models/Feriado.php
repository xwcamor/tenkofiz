<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Feriado extends Model
{
    protected $fillable = ['fecha', 'nombre'];

    protected $casts = ['fecha' => 'date'];

    public static function esFeriado(string $fecha): ?self
    {
        return static::whereDate('fecha', $fecha)->first();
    }
}
