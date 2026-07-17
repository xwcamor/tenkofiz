<?php

namespace App\Console\Commands;

use App\Models\Asistencia;
use Illuminate\Console\Command;

class MarcarFaltas extends Command
{
    protected $signature = 'asistencias:marcar-faltas {fecha? : Fecha a procesar (por defecto, hoy)}';

    protected $description = 'Marca FALTA a los empleados que no registraron asistencia (excluye feriados, domingos, vacaciones)';

    public function handle(): int
    {
        $fecha = $this->argument('fecha') ?? now()->toDateString();
        $creadas = Asistencia::marcarFaltas($fecha);
        $this->info("Fecha {$fecha}: se generaron {$creadas} falta(s).");

        return self::SUCCESS;
    }
}
