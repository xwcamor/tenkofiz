<?php

namespace Database\Seeders;

use App\Models\Ajuste;
use App\Models\Area;
use App\Models\Cargo;
use App\Models\Feriado;
use App\Models\Horario;
use App\Models\Perfil;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = Perfil::create(['nombre' => 'Administrador', 'descripcion' => 'Acceso total al sistema']);
        Perfil::create(['nombre' => 'Supervisor', 'descripcion' => 'Gestiona asistencias y aprueba vacaciones']);
        Perfil::create(['nombre' => 'Empleado', 'descripcion' => 'Consulta sus asistencias y solicita vacaciones']);

        User::create([
            'name' => 'Carlos Alberto Morales Larrañaga',
            'email' => 'admin@sistema.test',
            'password' => Hash::make('admin123'),
            'perfil_id' => $admin->id,
        ]);

        Horario::create(['nombre' => 'Turno Mañana', 'hora_entrada' => '08:00:00', 'hora_salida' => '17:00:00', 'tolerancia_min' => 10]);
        Horario::create(['nombre' => 'Turno Tarde', 'hora_entrada' => '14:00:00', 'hora_salida' => '22:00:00', 'tolerancia_min' => 10]);

        foreach (['Administración', 'Tecnologías de la Información', 'Recursos Humanos', 'Contabilidad', 'Operaciones'] as $a) {
            Area::create(['nombre' => $a]);
        }

        foreach (['Instructor', 'Asistente Administrativo', 'Analista', 'Coordinador', 'Técnico de Soporte'] as $c) {
            Cargo::create(['nombre' => $c]);
        }

        // Feriados nacionales de fecha fija (Perú)
        $anio = now()->year;
        $feriados = [
            ["$anio-01-01", 'Año Nuevo'],
            ["$anio-05-01", 'Día del Trabajo'],
            ["$anio-06-29", 'San Pedro y San Pablo'],
            ["$anio-07-28", 'Fiestas Patrias'],
            ["$anio-07-29", 'Fiestas Patrias'],
            ["$anio-08-30", 'Santa Rosa de Lima'],
            ["$anio-10-08", 'Combate de Angamos'],
            ["$anio-11-01", 'Todos los Santos'],
            ["$anio-12-08", 'Inmaculada Concepción'],
            ["$anio-12-09", 'Batalla de Ayacucho'],
            ["$anio-12-25", 'Navidad'],
        ];
        foreach ($feriados as [$f, $n]) {
            Feriado::create(['fecha' => $f, 'nombre' => $n]);
        }

        Ajuste::create([
            'empresa' => 'MI EMPRESA S.A.C.',
            'ruc' => '20000000001',
            'direccion' => 'Av. Principal 123, Lima',
            'telefono' => '(01) 000-0000',
        ]);
    }
}
