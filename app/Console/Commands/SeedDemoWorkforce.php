<?php

namespace App\Console\Commands;

use App\Models\Area;
use App\Models\Company;
use App\Models\Employee;
use App\Models\Position;
use App\Models\Schedule;
use App\Models\Scopes\CompanyScope;
use App\Models\Setting;
use App\Models\Site;
use Illuminate\Console\Command;

/**
 * Spins up a small, realistic demo workforce for evaluating compliance reports:
 * 4 employees across 2 sites, one of them on a FLEXIBLE schedule, with breaks
 * enabled — then generates attendance for a date range (default May–June of the
 * current year). Idempotent: safe to re-run.
 *
 *   php artisan demo:workforce
 *   php artisan demo:workforce --company=1 --from=2026-05-01 --to=2026-06-30
 */
class SeedDemoWorkforce extends Command
{
    protected $signature = 'demo:workforce
        {--company= : Target company id; default = the first company}
        {--from= : Start date YYYY-MM-DD; default = May 1st of the current year}
        {--to= : End date YYYY-MM-DD; default = June 30th of the current year}';

    protected $description = 'Create 4 demo employees (2 sites, 1 flexible schedule, breaks on) and seed their attendance for a period';

    public function handle(): int
    {
        $company = $this->option('company')
            ? Company::withTrashed()->find($this->option('company'))
            : Company::orderBy('id')->first();

        if (!$company) {
            $this->error('No company found. Create a workspace first.');

            return self::FAILURE;
        }

        $year = (int) date('Y');
        $from = $this->option('from') ?: "$year-05-01";
        $to = $this->option('to') ?: "$year-06-30";

        // Run everything scoped to the target workspace so helpers (app_setting,
        // current_period) and the CompanyScope resolve to this company.
        CompanyScope::actingAs($company->id, function () use ($company, $from, $to, $year) {
            // Breaks must be on for the break data (and the analysis report) to exist
            Setting::forCompany($company->id)->update(['kiosk_breaks_enabled' => true, 'break_limit_minutes' => 60]);

            // Reuse the workspace's default site (renamed to "Sede Central" by the
            // seeder) so the demo does not create duplicate sites; add one branch.
            $siteA = Site::where('company_id', $company->id)->orderBy('id')->first()
                ?? Site::firstOrCreate(['company_id' => $company->id, 'name' => 'Sede Central'], ['address' => 'Av. Principal 100, Lima', 'is_active' => true]);
            $siteB = Site::firstOrCreate(['company_id' => $company->id, 'name' => 'Sucursal Sur'], ['address' => 'Av. El Sol 300, Arequipa', 'is_active' => true]);

            $area = Area::firstOrCreate(['company_id' => $company->id, 'name' => 'Operaciones'], ['is_active' => true]);
            $posOperator = Position::firstOrCreate(['company_id' => $company->id, 'name' => 'Operario'], ['is_active' => true]);
            $posAnalyst = Position::firstOrCreate(['company_id' => $company->id, 'name' => 'Analista'], ['is_active' => true]);

            // Fixed schedule 08:00–17:00, Mon–Fri (reuse the first fixed one if present)
            $fixed = Schedule::where('type', Schedule::TYPE_FIXED)->orderBy('id')->first();
            if (!$fixed) {
                $fixed = Schedule::create(['company_id' => $company->id, 'name' => 'Turno Fijo 08–17', 'type' => Schedule::TYPE_FIXED, 'tolerance_minutes' => 10, 'is_active' => true]);
                foreach ([1, 2, 3, 4, 5] as $weekday) {
                    $fixed->days()->create(['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '17:00:00']);
                }
            }

            // Flexible schedule: 8h/day target, no tardiness, Mon–Fri
            $flexible = Schedule::where('type', Schedule::TYPE_FLEXIBLE)->where('name', 'Turno Flexible 8h/día')->first();
            if (!$flexible) {
                $flexible = Schedule::create(['company_id' => $company->id, 'name' => 'Turno Flexible 8h/día', 'type' => Schedule::TYPE_FLEXIBLE, 'target_minutes' => 480, 'is_active' => true]);
                foreach ([1, 2, 3, 4, 5] as $weekday) {
                    $flexible->days()->create(['weekday' => $weekday, 'start_time' => '08:00:00', 'end_time' => '18:00:00']);
                }
            }

            // A throwaway 128-value descriptor so they read as "enrolled" in the UI
            $descriptor = json_encode([array_fill(0, 128, 0.1)]);

            $people = [
                ['FLORES RAMÍREZ', 'DIEGO ARMANDO', '45010001', $siteA->id, $posOperator->id, $fixed->id],
                ['VÁSQUEZ LEÓN', 'CARMEN ROSA', '45010002', $siteA->id, $posOperator->id, $fixed->id],
                ['CHÁVEZ ORTIZ', 'MIGUEL ÁNGEL', '45010003', $siteB->id, $posOperator->id, $fixed->id],
                ['RÍOS SALAZAR', 'PATRICIA NOEMÍ', '45010004', $siteB->id, $posAnalyst->id, $flexible->id],
            ];

            foreach ($people as [$lastName, $firstName, $doc, $siteId, $positionId, $scheduleId]) {
                Employee::updateOrCreate(
                    ['company_id' => $company->id, 'document_number' => $doc],
                    [
                        'first_name' => $firstName,
                        'last_name' => $lastName,
                        'document_type' => 'DNI',
                        'schedule_id' => $scheduleId,
                        'area_id' => $area->id,
                        'position_id' => $positionId,
                        'site_id' => $siteId,
                        'contract_type' => 'full_time',
                        'hire_date' => "$year-01-06",
                        'face_descriptor' => $descriptor,
                        'is_active' => true,
                    ]
                );
            }

            $this->info("Demo workforce ready in «{$company->name}»: 4 employees, 2 sites, 1 flexible schedule, breaks ON.");

            // Generate attendance for each of the four, over the requested window
            foreach (['45010001', '45010002', '45010003', '45010004'] as $doc) {
                $this->call('attendances:seed-demo', ['--document' => $doc, '--from' => $from, '--to' => $to]);
            }
        });

        $this->info("Attendance seeded from {$from} to {$to}. Open Reports → Breaks to analyse.");

        return self::SUCCESS;
    }
}
