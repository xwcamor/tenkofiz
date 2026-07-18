<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AreaController;
use App\Http\Controllers\AttendanceController;
use App\Http\Controllers\AuditLogController;
use App\Http\Controllers\CalendarController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\EmployeeController;
use App\Http\Controllers\HolidayController;
use App\Http\Controllers\JustificationController;
use App\Http\Controllers\KioskController;
use App\Http\Controllers\LoginController;
use App\Http\Controllers\PositionController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ReportController;
use App\Http\Controllers\ScheduleController;
use App\Http\Controllers\SettingController;
use App\Http\Controllers\UserController;
use App\Http\Controllers\VacationController;
use Illuminate\Support\Facades\Route;

// ---------- Authentication ----------
Route::get('/login', [LoginController::class, 'showLogin'])->name('login')->middleware('guest');
Route::post('/login', [LoginController::class, 'login'])->middleware('guest');
Route::post('/logout', [LoginController::class, 'logout'])->name('logout')->middleware('auth');
Route::post('/locale', [LoginController::class, 'switchLocale'])->name('locale.switch');

// ---------- Password recovery ----------
Route::middleware('guest')->group(function () {
    Route::get('/forgot-password', [AccountController::class, 'showForgot'])->name('password.request');
    Route::post('/forgot-password', [AccountController::class, 'sendResetLink'])->name('password.email');
    Route::get('/reset-password/{token}', [AccountController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [AccountController::class, 'reset'])->name('password.update');
});

// ---------- Facial marking kiosk (public screen on an authorized device) ----------
Route::middleware('kiosk.token')->group(function () {
    Route::get('/kiosk', [KioskController::class, 'index'])->name('kiosk');
    Route::get('/kiosk/descriptors', [KioskController::class, 'descriptors'])->name('kiosk.descriptors');
    Route::get('/kiosk/version', [KioskController::class, 'version'])->name('kiosk.version');
    Route::post('/kiosk/mark', [KioskController::class, 'mark'])->name('kiosk.mark');
    Route::post('/kiosk/mark-dni', [KioskController::class, 'markByDni'])->name('kiosk.markDni');
    Route::post('/kiosk/enroll/unlock', [KioskController::class, 'enrollUnlock'])->name('kiosk.enroll.unlock');
    Route::post('/kiosk/enroll/lookup', [KioskController::class, 'enrollLookup'])->name('kiosk.enroll.lookup');
    Route::post('/kiosk/enroll/descriptor', [KioskController::class, 'enrollDescriptor'])->name('kiosk.enroll.descriptor');
});

// ---------- Internal modules ----------
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Access is granted per module according to the profile's permissions
    Route::middleware('module:users')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('module:profiles')->group(function () {
        Route::resource('profiles', ProfileController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('module:schedules')->group(function () {
        Route::resource('schedules', ScheduleController::class)->only(['index', 'store', 'update', 'destroy']);
    });

    Route::middleware('module:holidays')->group(function () {
        Route::resource('holidays', HolidayController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('holidays-generate', [HolidayController::class, 'generate'])->name('holidays.generate');
    });

    Route::middleware('module:audit_logs')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    });

    Route::middleware('module:settings')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        Route::post('settings/kiosk-token', [SettingController::class, 'regenerateKioskToken'])->name('settings.kioskToken');
        Route::delete('settings/kiosk-token', [SettingController::class, 'clearKioskToken'])->name('settings.kioskToken.clear');
    });

    Route::middleware('module:employees')->group(function () {
        Route::get('dni-lookup/{dni}', [\App\Http\Controllers\DniLookupController::class, 'show'])->name('dni.lookup');
        Route::get('employees-import/template', [\App\Http\Controllers\EmployeeImportController::class, 'template'])->name('employees.import.template');
        Route::post('employees-import', [\App\Http\Controllers\EmployeeImportController::class, 'store'])->name('employees.import');
        Route::resource('employees', EmployeeController::class)->except(['show']);
        Route::get('employees/{employee}/enroll', [EmployeeController::class, 'enroll'])->name('employees.enroll');
        Route::post('employees/{employee}/descriptor', [EmployeeController::class, 'storeDescriptor'])->name('employees.descriptor');
        Route::post('employees/{employee}/create-user', [EmployeeController::class, 'createUser'])->name('employees.createUser');
        Route::post('employees/{employee}/link-user', [EmployeeController::class, 'linkUser'])->name('employees.linkUser');
        Route::post('employees/{employee}/unlink-user', [EmployeeController::class, 'unlinkUser'])->name('employees.unlinkUser');
        Route::post('areas', [AreaController::class, 'store'])->name('areas.store');
        Route::post('positions', [PositionController::class, 'store'])->name('positions.store');
    });

    Route::middleware('module:attendances')->group(function () {
        Route::get('attendances', [AttendanceController::class, 'index'])->name('attendances.index');
        Route::post('attendances', [AttendanceController::class, 'store'])->name('attendances.store');
        Route::put('attendances/{attendance}', [AttendanceController::class, 'update'])->name('attendances.update');
        Route::post('attendances-mark-absences', [AttendanceController::class, 'markAbsences'])->name('attendances.markAbsences');
    });

    Route::middleware('module:reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
    });

    Route::middleware('module:vacations_manage')->group(function () {
        Route::patch('vacations/{vacation}/status', [VacationController::class, 'changeStatus'])->name('vacations.status');
    });

    Route::middleware('module:justifications_manage')->group(function () {
        Route::patch('justifications/{justification}/status', [JustificationController::class, 'changeStatus'])->name('justifications.status');
        Route::delete('justifications/{justification}', [JustificationController::class, 'destroy'])->name('justifications.destroy');
    });

    // All authenticated users
    // Employee autocomplete for the selectors (managers only; checked in the controller)
    Route::get('lookup/employees', [EmployeeController::class, 'search'])->name('employees.search');
    Route::get('vacations', [VacationController::class, 'index'])->name('vacations.index');
    Route::post('vacations', [VacationController::class, 'store'])->name('vacations.store');
    Route::get('vacations/{vacation}/print', [VacationController::class, 'print'])->name('vacations.print');
    Route::get('justifications/{justification}/print', [JustificationController::class, 'print'])->name('justifications.print');
    Route::get('my-attendances', [AttendanceController::class, 'mine'])->name('attendances.mine');
    Route::get('justifications', [JustificationController::class, 'index'])->name('justifications.index');
    Route::post('justifications', [JustificationController::class, 'store'])->name('justifications.store');
    Route::get('calendar', [CalendarController::class, 'index'])->name('calendar.index');
    Route::get('my-sheet', [ReportController::class, 'mySheet'])->name('reports.mySheet');
    Route::get('account', [AccountController::class, 'edit'])->name('account.edit');
    Route::put('account/password', [AccountController::class, 'updatePassword'])->name('account.password.update');
    Route::put('account/preferences', [AccountController::class, 'updatePreferences'])->name('account.preferences.update');
    Route::get('reports/sheet/{employee}', [ReportController::class, 'sheet'])->name('reports.sheet');
});
