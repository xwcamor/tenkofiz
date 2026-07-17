<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Asistencia;
use App\Models\Cargo;
use App\Models\Empleado;
use App\Models\Feriado;
use App\Models\Horario;
use App\Models\Justificacion;
use App\Models\Perfil;
use App\Models\User;
use App\Models\Vacacion;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Datos de demostración: 8 empleados con 30 días de asistencias simuladas.
 * Ejecutar con: php artisan db:seed --class=DemoSeeder
 * (No borra nada: agrega sobre lo existente)
 */
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $horario = Horario::first() ?? Horario::create(['nombre' => 'Turno Mañana', 'hora_entrada' => '08:00:00', 'hora_salida' => '17:00:00', 'tolerancia_min' => 10]);
        $areas = Area::pluck('id')->all() ?: [Area::create(['nombre' => 'Operaciones'])->id];
        $cargos = Cargo::pluck('id')->all() ?: [Cargo::create(['nombre' => 'Asistente'])->id];

        $personas = [
            ['GARCÍA TORRES', 'MARÍA ELENA', '40111222'],
            ['QUISPE HUAMÁN', 'JOSÉ LUIS', '40222333'],
            ['RODRÍGUEZ VEGA', 'ANA PAULA', '40333444'],
            ['FERNÁNDEZ ROJAS', 'CARLOS DANIEL', '40444555'],
            ['MAMANI CONDORI', 'ROSA ISABEL', '40555666'],
            ['LÓPEZ CASTILLO', 'JORGE ANTONIO', '40666777'],
            ['SÁNCHEZ PAREDES', 'LUCÍA FERNANDA', '40777888'],
            ['TORRES MENDOZA', 'PEDRO PABLO', '40888999'],
        ];

        $feriados = Feriado::pluck('fecha')->map(fn ($f) => $f->toDateString())->all();
        $empleados = [];

        foreach ($personas as [$apellidos, $nombres, $dni]) {
            $empleados[] = Empleado::firstOrCreate(
                ['dni' => $dni],
                [
                    'nombres' => $nombres,
                    'apellidos' => $apellidos,
                    'horario_id' => $horario->id,
                    'area_id' => $areas[array_rand($areas)],
                    'cargo_id' => $cargos[array_rand($cargos)],
                    'fecha_ingreso' => now()->subMonths(rand(6, 36))->toDateString(),
                ]
            );
        }

        // 30 días de asistencias simuladas (sin domingos ni feriados)
        foreach ($empleados as $e) {
            for ($i = 30; $i >= 1; $i--) {
                $dia = now()->subDays($i);
                if ($dia->dayOfWeek === 0 || in_array($dia->toDateString(), $feriados, true)) {
                    continue;
                }

                $azar = rand(1, 100);
                if ($azar <= 78) {          // puntual
                    $entrada = sprintf('07:%02d:%02d', rand(45, 59), rand(0, 59));
                    $estado = 'PUNTUAL';
                } elseif ($azar <= 93) {    // tardanza
                    $entrada = sprintf('08:%02d:%02d', rand(11, 45), rand(0, 59));
                    $estado = 'TARDANZA';
                } else {                    // falta
                    Asistencia::firstOrCreate(
                        ['empleado_id' => $e->id, 'fecha' => $dia->toDateString()],
                        ['estado' => 'FALTA', 'metodo' => 'MANUAL', 'observacion' => 'Falta generada automáticamente (demo)']
                    );
                    continue;
                }

                Asistencia::firstOrCreate(
                    ['empleado_id' => $e->id, 'fecha' => $dia->toDateString()],
                    [
                        'hora_entrada' => $entrada,
                        'hora_salida' => sprintf('17:%02d:%02d', rand(0, 35), rand(0, 59)),
                        'estado' => $estado,
                        'metodo' => 'FACIAL',
                        'similitud' => rand(28, 48) / 100,
                    ]
                );
            }
        }

        // Vacaciones y justificación de muestra
        Vacacion::firstOrCreate(
            ['empleado_id' => $empleados[0]->id, 'fecha_inicio' => now()->addDays(10)->toDateString()],
            ['fecha_fin' => now()->addDays(16)->toDateString(), 'dias' => 7, 'estado' => 'PENDIENTE', 'motivo' => 'Vacaciones familiares']
        );
        Vacacion::firstOrCreate(
            ['empleado_id' => $empleados[1]->id, 'fecha_inicio' => now()->addDays(20)->toDateString()],
            ['fecha_fin' => now()->addDays(27)->toDateString(), 'dias' => 8, 'estado' => 'APROBADO', 'motivo' => 'Descanso anual', 'aprobado_por' => User::first()?->id]
        );
        Justificacion::firstOrCreate(
            ['empleado_id' => $empleados[2]->id, 'fecha' => now()->subDays(3)->toDateString()],
            ['motivo' => 'Cita médica en EsSalud — se adjuntó constancia', 'estado' => 'PENDIENTE']
        );

        // Usuario demo con perfil Empleado (vinculado al primer empleado)
        $perfilEmp = Perfil::firstOrCreate(['nombre' => 'Empleado'], ['descripcion' => 'Consulta sus asistencias']);
        $userDemo = User::firstOrCreate(
            ['email' => 'empleado@demo.test'],
            ['name' => 'María Elena García Torres', 'password' => Hash::make('demo1234'), 'perfil_id' => $perfilEmp->id]
        );
        $empleados[0]->update(['user_id' => $userDemo->id]);

        $this->command?->info('Demo cargada: 8 empleados, ~1 mes de asistencias, vacaciones y justificación de muestra.');
        $this->command?->info('Usuario demo (perfil Empleado): empleado@demo.test / demo1234');
    }
}
