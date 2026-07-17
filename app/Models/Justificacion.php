<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Justificacion extends Model
{
    protected $table = 'justificaciones';

    protected $fillable = ['empleado_id', 'fecha', 'motivo', 'documento', 'estado', 'revisado_por'];

    protected $casts = ['fecha' => 'date'];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    public function revisor()
    {
        return $this->belongsTo(User::class, 'revisado_por');
    }
}
