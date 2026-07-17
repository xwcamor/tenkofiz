<?php

use Illuminate\Support\Facades\Schedule;

// Genera las faltas del día automáticamente al cierre de la jornada.
// Requiere el planificador corriendo: `php artisan schedule:work` (o un cron en producción).
Schedule::command('asistencias:marcar-faltas')->dailyAt('23:50');
