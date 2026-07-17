<?php

use App\Http\Controllers\AjusteController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AuditoriaController;
use App\Http\Controllers\CalendarioController;
use App\Http\Controllers\CuentaController;
use App\Http\Controllers\JustificacionController;
use App\Http\Controllers\AsistenciaController;
use App\Http\Controllers\CargoController;
use App\Http\Controllers\FeriadoController;
use App\Http\Controllers\ReporteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmpleadoController;
use App\Http\Controllers\HorarioController;
use App\Http\Controllers\KioscoController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PerfilController;
use App\Http\Controllers\UsuarioController;
use App\Http\Controllers\VacacionController;
use Illuminate\Support\Facades\Route;

// ---------- Autenticación ----------
Route::get('/login', [LoginController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');

// ---------- Recuperación de contraseña ----------
Route::middleware('guest')->group(function () {
    Route::get('/olvide-password', [CuentaController::class, 'olvido'])->name('password.request');
    Route::post('/olvide-password', [CuentaController::class, 'enviarEnlace'])->name('password.email');
    Route::get('/reset-password/{token}', [CuentaController::class, 'formRestablecer'])->name('password.reset');
    Route::post('/reset-password', [CuentaController::class, 'restablecer'])->name('password.update');
});

// ---------- Kiosco de marcado facial (pantalla pública del local) ----------
Route::get('/kiosco', [KioscoController::class, 'index'])->name('kiosco');
Route::get('/kiosco/descriptores', [KioscoController::class, 'descriptores'])->name('kiosco.descriptores');
Route::post('/kiosco/marcar', [KioscoController::class, 'marcar'])->name('kiosco.marcar');

// ---------- Módulos internos ----------
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Solo Administrador
    Route::middleware('perfil:Administrador')->group(function () {
        Route::resource('usuarios', UsuarioController::class)->except(['show']);
        Route::resource('perfiles', PerfilController::class)->except(['show'])->parameters(['perfiles' => 'perfil']);
        Route::resource('horarios', HorarioController::class)->except(['show']);
        Route::resource('feriados', FeriadoController::class)->except(['show']);
        Route::post('feriados-generar', [FeriadoController::class, 'generar'])->name('feriados.generar');
        Route::get('auditorias', [AuditoriaController::class, 'index'])->name('auditorias.index');
        Route::get('ajustes', [AjusteController::class, 'edit'])->name('ajustes.edit');
        Route::put('ajustes', [AjusteController::class, 'update'])->name('ajustes.update');
        Route::post('empleados/{empleado}/crear-usuario', [EmpleadoController::class, 'crearUsuario'])->name('empleados.crearUsuario');
    });

    // Administrador y Supervisor
    Route::middleware('perfil:Administrador,Supervisor')->group(function () {
        Route::resource('empleados', EmpleadoController::class)->except(['show']);
        Route::get('empleados/{empleado}/enrolar', [EmpleadoController::class, 'enrolar'])->name('empleados.enrolar');
        Route::post('empleados/{empleado}/descriptor', [EmpleadoController::class, 'guardarDescriptor'])->name('empleados.descriptor');
        Route::get('asistencias', [AsistenciaController::class, 'index'])->name('asistencias.index');
        Route::post('asistencias', [AsistenciaController::class, 'store'])->name('asistencias.store');
        Route::patch('vacaciones/{vacacion}/estado', [VacacionController::class, 'cambiarEstado'])->name('vacaciones.estado');
        Route::get('reportes', [ReporteController::class, 'index'])->name('reportes.index');
        Route::get('asistencias/{asistencia}/edit', [AsistenciaController::class, 'edit'])->name('asistencias.edit');
        Route::put('asistencias/{asistencia}', [AsistenciaController::class, 'update'])->name('asistencias.update');
        Route::post('asistencias-marcar-faltas', [AsistenciaController::class, 'marcarFaltas'])->name('asistencias.marcarFaltas');
        Route::patch('justificaciones/{justificacion}/estado', [JustificacionController::class, 'cambiarEstado'])->name('justificaciones.estado');
        Route::delete('justificaciones/{justificacion}', [JustificacionController::class, 'destroy'])->name('justificaciones.destroy');
        Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
        Route::post('cargos', [CargoController::class, 'store'])->name('cargos.store');
    });

    // Todos los autenticados
    Route::get('vacaciones', [VacacionController::class, 'index'])->name('vacaciones.index');
    Route::get('vacaciones/crear', [VacacionController::class, 'create'])->name('vacaciones.create');
    Route::post('vacaciones', [VacacionController::class, 'store'])->name('vacaciones.store');
    Route::get('mis-asistencias', [AsistenciaController::class, 'misAsistencias'])->name('asistencias.mias');
    Route::get('justificaciones', [JustificacionController::class, 'index'])->name('justificaciones.index');
    Route::get('justificaciones/crear', [JustificacionController::class, 'create'])->name('justificaciones.create');
    Route::post('justificaciones', [JustificacionController::class, 'store'])->name('justificaciones.store');
    Route::get('calendario', [CalendarioController::class, 'index'])->name('calendario.index');
    Route::get('mi-ficha', [ReporteController::class, 'miFicha'])->name('reportes.miFicha');
    Route::get('cambiar-password', [CuentaController::class, 'editarPassword'])->name('cuenta.password');
    Route::put('cambiar-password', [CuentaController::class, 'actualizarPassword'])->name('cuenta.password.update');
    Route::get('reportes/ficha/{empleado}', [ReporteController::class, 'ficha'])->name('reportes.ficha');
});
