<?php

namespace Database\Seeders;

use App\Models\Area;
use App\Models\Company;
use App\Models\Holiday;
use App\Models\Position;
use App\Models\Profile;
use App\Models\Schedule;
use App\Models\Scopes\CompanyScope;
use App\Models\Setting;
use App\Models\Site;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/**
 * Idempotent seeder: safe to run again on an already-populated database
 * (e.g. `php artisan migrate --seed` after an update). Existing rows are
 * left untouched; only missing base records are created.
 *
 * A fresh install ships with a SINGLE demo workspace. The academic-institute
 * scenario lives in its OWN command (`php artisan demo:academic`) so it never
 * clutters the default install — run it only when you want that demo.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Super-admin: owns every workspace, belongs to none (company_id = null)
        User::firstOrCreate(['email' => 'super@test.com'], [
            'name' => 'Super Admin',
            'password' => Hash::make('123456'),
            'is_super_admin' => true,
            'company_id' => null,
        ]);

        // ---- The one and only default demo workspace ----
        // The companies migration created a default company; reuse the first one
        // (whatever it was called before) and normalize it. Never duplicates.
        $company = Company::where('tax_id', '20123456789')
            ->orWhereIn('name', ['Empresa 1', 'Empresa Demo', 'Mi Empresa S.A.C.'])
            ->orderBy('id')
            ->first()
            ?? Company::orderBy('id')->first()
            ?? Company::create(['name' => 'Mi Empresa S.A.C.', 'tax_id' => '20123456789', 'is_active' => true]);
        $company->update(['name' => 'Mi Empresa S.A.C.', 'tax_id' => '20123456789', 'is_active' => true]);

        CompanyScope::actingAs($company->id, function () use ($company) {
            [$admin, $supervisor, $employeeProfile] = $this->seedBaseProfiles();

            // Test users — all inside the workspace so every login works out of the box
            User::firstOrCreate(['email' => 'admin@test.com'], ['name' => 'Administrador', 'password' => Hash::make('123456'), 'profile_id' => $admin->id]);
            User::firstOrCreate(['email' => 'aprobador@test.com'], ['name' => 'Aprobador', 'password' => Hash::make('123456'), 'profile_id' => $supervisor->id]);
            User::firstOrCreate(['email' => 'empleado@test.com'], ['name' => 'Empleado', 'password' => Hash::make('123456'), 'profile_id' => $employeeProfile->id]);

            $this->seedStarterSchedule();

            // Give the auto-created default site ("General") a friendly name/address.
            // The demo workforce (below) reuses it and adds one branch, so a fresh
            // install has clean, non-duplicated sites. Rename or edit in the Sites screen.
            $defaultSite = Site::orderBy('id')->first();
            if ($defaultSite && $defaultSite->name === 'General') {
                $defaultSite->update(['name' => 'Sede Central', 'address' => 'Av. Principal 100, Lima']);
            } else {
                Site::firstOrCreate(['name' => 'Sede Central'], ['address' => 'Av. Principal 100, Lima']);
            }

            foreach (['Administración', 'Tecnología de la Información', 'Recursos Humanos', 'Contabilidad', 'Operaciones'] as $area) {
                Area::firstOrCreate(['name' => $area]);
            }
            foreach (['Asistente Administrativo', 'Analista', 'Coordinador', 'Técnico de Soporte', 'Operario'] as $position) {
                Position::firstOrCreate(['name' => $position]);
            }

            $this->seedHolidays($company->id);

            Setting::firstOrCreate(['company_id' => $company->id], [
                'company_name' => 'Mi Empresa S.A.C.',
                'tax_id' => '20123456789',
                'address' => 'Av. Principal 100, Lima',
                'timezone' => 'America/Lima',
                'country' => 'PE',
            ]);
        });

        // ---- Demo workforce (4 employees + attendance) for a real install ----
        // Skipped under tests: the suite seeds this class on every test and must
        // stay fast and predictable (no bulk employees/attendance injected).
        if (!app()->environment('testing')) {
            \Illuminate\Support\Facades\Artisan::call('demo:workforce', [], $this->command?->getOutput());
        }
    }

    /**
     * The three base roles for the CURRENT company (call inside a company scope).
     * Profiles are per company, so each workspace gets its own protected trio.
     * Returns [Administrator, Supervisor, Employee].
     */
    private function seedBaseProfiles(): array
    {
        $admin = Profile::firstOrCreate(['name' => 'Administrator'], [
            'description' => 'Full access to the system',
            'permissions' => array_keys(Profile::MODULES),
            'is_system' => true,
        ]);
        $supervisor = Profile::firstOrCreate(['name' => 'Supervisor'], [
            'description' => 'Manages attendance and approves requests',
            'permissions' => ['employees', 'attendances', 'reports', 'vacations_manage', 'justifications_manage', 'kiosk'],
            'is_system' => true,
        ]);
        $employee = Profile::firstOrCreate(['name' => 'Employee'], [
            'description' => 'Views their attendance and requests vacations',
            'permissions' => [],
            'is_system' => true,
        ]);

        return [$admin, $supervisor, $employee];
    }

    /** Base schedule so the workspace can register employees from day one */
    private function seedStarterSchedule(): void
    {
        $schedule = Schedule::firstOrCreate(['name' => 'Horario General'], ['tolerance_minutes' => 5]);
        foreach ([1, 2, 3, 4, 5, 6] as $weekday) {
            $schedule->days()->firstOrCreate(['weekday' => $weekday], ['start_time' => '08:00:00', 'end_time' => '17:00:00']);
        }
    }

    /** Seeds this company's holiday templates (PE + CL) and this year's PE holidays */
    private function seedHolidays(int $companyId): void
    {
        foreach (array_keys(\App\Models\HolidayTemplate::COUNTRIES) as $country) {
            foreach (\App\Models\HolidayTemplate::presets($country) as [$month, $day, $offset, $name]) {
                \App\Models\HolidayTemplate::firstOrCreate(
                    ['company_id' => $companyId, 'country' => $country, 'month' => $month, 'day' => $day, 'easter_offset' => $offset, 'name' => $name]
                );
            }
        }

        $year = now()->year;
        foreach (\App\Models\HolidayTemplate::where('country', 'PE')->get() as $template) {
            if ($date = $template->dateForYear($year)) {
                Holiday::firstOrCreate(['date' => $date], ['name' => $template->name]);
            }
        }
    }
}
