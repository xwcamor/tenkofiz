<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Auditoria extends Model
{
    protected $table = 'auditorias';

    protected $fillable = ['user_id', 'accion', 'modulo', 'descripcion', 'datos', 'ip'];

    protected $casts = ['datos' => 'array'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /** Registra un evento de auditoría. Uso: Auditoria::registrar('ELIMINAR', 'Empleados', 'Se eliminó...', $modelo->toArray()) */
    public static function registrar(string $accion, string $modulo, string $descripcion, ?array $datos = null): void
    {
        static::create([
            'user_id' => auth()->id(),
            'accion' => $accion,
            'modulo' => $modulo,
            'descripcion' => $descripcion,
            'datos' => $datos,
            'ip' => request()->ip(),
        ]);
    }
}
