<?php

namespace App\Http\Controllers;

use App\Models\Asistencia;
use App\Models\Empleado;
use App\Models\Vacacion;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Administrador y Supervisor ven el dashboard global; el resto, solo su información
        if ($user->tienePerfil('Administrador', 'Supervisor')) {
            return $this->dashboardGestor();
        }

        return $this->dashboardEmpleado($user);
    }

    /** Dashboard global (Administrador / Supervisor) */
    private function dashboardGestor()
    {
        $hoy = now()->toDateString();

        $totalEmpleados = Empleado::where('activo', true)->count();
        $asistenciasHoy = Asistencia::whereDate('fecha', $hoy)->count();
        $tardanzasHoy = Asistencia::whereDate('fecha', $hoy)->where('estado', 'TARDANZA')->count();
        $vacacionesPendientes = Vacacion::where('estado', 'PENDIENTE')->count();
        $sinRostro = Empleado::where('activo', true)->whereNull('descriptor_facial')->count();

        $ultimas = Asistencia::with('empleado')
            ->whereDate('fecha', $hoy)
            ->latest('updated_at')
            ->take(8)
            ->get();

        $labels = [];
        $serieAsistencias = [];
        $serieTardanzas = [];
        for ($i = 6; $i >= 0; $i--) {
            $dia = now()->subDays($i)->toDateString();
            $labels[] = now()->subDays($i)->format('d/m');
            $serieAsistencias[] = Asistencia::whereDate('fecha', $dia)->count();
            $serieTardanzas[] = Asistencia::whereDate('fecha', $dia)->where('estado', 'TARDANZA')->count();
        }

        return view('dashboard', [
            'esGestor' => true,
            'totalEmpleados' => $totalEmpleados,
            'asistenciasHoy' => $asistenciasHoy,
            'tardanzasHoy' => $tardanzasHoy,
            'vacacionesPendientes' => $vacacionesPendientes,
            'sinRostro' => $sinRostro,
            'ultimas' => $ultimas,
            'labels' => $labels,
            'serieAsistencias' => $serieAsistencias,
            'serieTardanzas' => $serieTardanzas,
        ]);
    }

    /** Dashboard personal (perfil Empleado): solo su propia información */
    private function dashboardEmpleado($user)
    {
        $empleado = Empleado::with('horario')->where('user_id', $user->id)->first();
        $hoy = now()->toDateString();
        $inicioMes = now()->startOfMonth()->toDateString();

        $asistenciaHoy = null;
        $diasMes = 0;
        $tardanzasMes = 0;
        $misVacaciones = collect();
        $recientes = collect();

        if ($empleado) {
            $asistenciaHoy = $empleado->asistencias()->whereDate('fecha', $hoy)->first();

            $delMes = $empleado->asistencias()->whereBetween('fecha', [$inicioMes, $hoy])->get();
            $diasMes = $delMes->whereIn('estado', ['PUNTUAL', 'TARDANZA'])->count();
            $tardanzasMes = $delMes->where('estado', 'TARDANZA')->count();

            $misVacaciones = $empleado->vacaciones()->latest()->take(3)->get();
            $recientes = $empleado->asistencias()->orderByDesc('fecha')->take(7)->get();
        }

        return view('dashboard', [
            'esGestor' => false,
            'empleado' => $empleado,
            'asistenciaHoy' => $asistenciaHoy,
            'diasMes' => $diasMes,
            'tardanzasMes' => $tardanzasMes,
            'misVacaciones' => $misVacaciones,
            'recientes' => $recientes,
        ]);
    }
}
