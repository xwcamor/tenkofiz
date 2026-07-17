<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Asistencia extends Model
{
    protected $fillable = [
        'empleado_id', 'fecha', 'hora_entrada', 'hora_salida',
        'estado', 'metodo', 'similitud', 'observacion',
    ];

    protected $casts = ['fecha' => 'date'];

    public function empleado()
    {
        return $this->belongsTo(Empleado::class);
    }

    /** Días no laborables por defecto (0 = domingo). Editar según la realidad de la empresa. */
    public const DIAS_NO_LABORABLES = [0];

    /**
     * Marca FALTA a todos los empleados activos con horario que no registraron
     * asistencia en la fecha dada. Excluye: feriados, días no laborables,
     * vacaciones aprobadas y días ya justificados. Devuelve cuántas faltas creó.
     */
    public static function marcarFaltas(string $fecha): int
    {
        $dia = \Carbon\Carbon::parse($fecha);

        if (in_array($dia->dayOfWeek, self::DIAS_NO_LABORABLES, true)) {
            return 0;
        }

        if (Feriado::esFeriado($fecha)) {
            return 0;
        }

        $creadas = 0;

        Empleado::where('activo', true)
            ->whereNotNull('horario_id')
            ->whereDoesntHave('asistencias', fn ($q) => $q->whereDate('fecha', $fecha))
            ->each(function (Empleado $e) use ($fecha, &$creadas) {
                if ($e->deVacaciones($fecha)) {
                    return;
                }

                static::create([
                    'empleado_id' => $e->id,
                    'fecha' => $fecha,
                    'estado' => 'FALTA',
                    'metodo' => 'MANUAL',
                    'observacion' => 'Falta generada automáticamente (sin marcado)',
                ]);
                $creadas++;
            });

        return $creadas;
    }
}
