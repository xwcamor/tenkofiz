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
// Device pairing (no device cookie yet, so it lives OUTSIDE the kiosk.token gate;
// the one-time code is the secret that authorizes the pairing)
Route::get('/kiosk/pair', [KioskController::class, 'showPair'])->name('kiosk.pair');
Route::post('/kiosk/pair', [KioskController::class, 'pair'])->name('kiosk.pair.submit');

Route::middleware('kiosk.token')->group(function () {
    Route::get('/kiosk', [KioskController::class, 'index'])->name('kiosk');
    Route::get('/kiosk/descriptors', [KioskController::class, 'descriptors'])->name('kiosk.descriptors');
    Route::get('/kiosk/version', [KioskController::class, 'version'])->name('kiosk.version');
    Route::get('/kiosk/face/{document}', [KioskController::class, 'personFace'])->name('kiosk.face');
    Route::post('/kiosk/mark', [KioskController::class, 'mark'])->name('kiosk.mark');
    Route::post('/kiosk/mark-dni', [KioskController::class, 'markByDni'])->name('kiosk.markDni');
    Route::post('/kiosk/enroll/unlock', [KioskController::class, 'enrollUnlock'])->name('kiosk.enroll.unlock');
    Route::post('/kiosk/enroll/lookup', [KioskController::class, 'enrollLookup'])->name('kiosk.enroll.lookup');
    Route::post('/kiosk/enroll/descriptor', [KioskController::class, 'enrollDescriptor'])->name('kiosk.enroll.descriptor');
});

// ---------- Internal modules ----------
Route::middleware('auth')->group(function () {
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Terms and conditions: mandatory acceptance before using the system
    Route::get('terms', [\App\Http\Controllers\TermsController::class, 'show'])->name('terms.show');
    Route::post('terms', [\App\Http\Controllers\TermsController::class, 'accept'])->name('terms.accept');

    // Super-admin: workspace (company) management, above all tenants
    Route::middleware('super_admin')->prefix('admin')->group(function () {
        Route::get('companies', [\App\Http\Controllers\CompanyController::class, 'index'])->name('admin.companies.index');
        Route::post('companies', [\App\Http\Controllers\CompanyController::class, 'store'])->name('admin.companies.store');
        Route::put('companies/{company}', [\App\Http\Controllers\CompanyController::class, 'update'])->name('admin.companies.update');
        Route::post('companies/{company}/enter', [\App\Http\Controllers\CompanyController::class, 'enter'])->name('admin.companies.enter');
        Route::post('companies/leave', [\App\Http\Controllers\CompanyController::class, 'leave'])->name('admin.companies.leave');
    });

    // Access is granted per module according to the profile's permissions
    Route::middleware('module:users')->group(function () {
        Route::resource('users', UserController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('users/{user}/restore', [UserController::class, 'restore'])->name('users.restore')->withTrashed();
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
        // Recurring holiday templates per country (drive "Generate year")
        Route::post('holiday-templates', [HolidayController::class, 'storeTemplate'])->name('holidays.templates.store');
        Route::put('holiday-templates/{template}', [HolidayController::class, 'updateTemplate'])->name('holidays.templates.update');
        Route::delete('holiday-templates/{template}', [HolidayController::class, 'destroyTemplate'])->name('holidays.templates.destroy');
        Route::post('holiday-templates-restore', [HolidayController::class, 'restoreTemplates'])->name('holidays.templates.restore');
    });

    Route::middleware('module:audit_logs')->group(function () {
        Route::get('audit-logs', [AuditLogController::class, 'index'])->name('audit.index');
    });

    Route::middleware('module:settings')->group(function () {
        Route::get('settings', [SettingController::class, 'edit'])->name('settings.edit');
        Route::put('settings', [SettingController::class, 'update'])->name('settings.update');
        // Sites (sedes) + per-site kiosk security (token + device binding)
        Route::resource('sites', \App\Http\Controllers\SiteController::class)->only(['index', 'store', 'update', 'destroy']);
        Route::post('sites/{site}/kiosk-token', [\App\Http\Controllers\SiteController::class, 'regenerateToken'])->name('sites.kioskToken');
        Route::delete('sites/{site}/kiosk-token', [\App\Http\Controllers\SiteController::class, 'clearToken'])->name('sites.kioskToken.clear');
        Route::post('sites/{site}/kiosk-pair-code', [\App\Http\Controllers\SiteController::class, 'generatePairCode'])->name('sites.kioskPair');
        Route::delete('sites/{site}/kiosk-device', [\App\Http\Controllers\SiteController::class, 'unpairDevice'])->name('sites.kioskUnpair');
    });

    Route::middleware('module:employees')->group(function () {
        Route::get('dni-lookup/{dni}', [\App\Http\Controllers\DniLookupController::class, 'show'])->name('dni.lookup');
        Route::get('employees-import/template', [\App\Http\Controllers\EmployeeImportController::class, 'template'])->name('employees.import.template');
        Route::post('employees-import', [\App\Http\Controllers\EmployeeImportController::class, 'store'])->name('employees.import');
        Route::resource('employees', EmployeeController::class)->except(['show']);
        Route::post('employees/{employee}/restore', [EmployeeController::class, 'restore'])->name('employees.restore')->withTrashed();
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
        Route::delete('attendances/{attendance}', [AttendanceController::class, 'destroy'])->name('attendances.destroy');
        Route::post('attendances/{attendance}/restore', [AttendanceController::class, 'restore'])->name('attendances.restore')->withTrashed();
        Route::post('attendances-mark-absences', [AttendanceController::class, 'markAbsences'])->name('attendances.markAbsences');
    });

    Route::middleware('module:reports')->group(function () {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::get('reports/export', [ReportController::class, 'export'])->name('reports.export');
        Route::get('reports/export-detail', [ReportController::class, 'exportDetail'])->name('reports.exportDetail');
    });

    Route::middleware('module:vacations_manage')->group(function () {
        Route::patch('vacations/{vacation}/status', [VacationController::class, 'changeStatus'])->name('vacations.status');
    });

    Route::middleware('module:justifications_manage')->group(function () {
        Route::patch('justifications/{justification}/status', [JustificationController::class, 'changeStatus'])->name('justifications.status');
        Route::delete('justifications/{justification}', [JustificationController::class, 'destroy'])->name('justifications.destroy');
        Route::post('justifications/{justification}/restore', [JustificationController::class, 'restore'])->name('justifications.restore')->withTrashed();
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
