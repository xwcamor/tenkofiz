<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Justification;
use App\Models\Site;
use App\Models\Vacation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // A super-admin who hasn't entered a workspace goes to the workspaces console
        if ($user->isSuperAdmin() && !session('acting_company_id')) {
            return redirect()->route('admin.companies.index');
        }

        // Managers see the global dashboard; everyone else sees their own info
        if ($user->isManager()) {
            return $this->managerDashboard($request);
        }

        return $this->employeeDashboard($user);
    }

    /** Global dashboard (managers) */
    private function managerDashboard(Request $request)
    {
        $today = company_now()->toDateString();
        $weekStart = company_now()->subDays(6)->toDateString();

        // Optional site scope: a site-bound manager is always locked to their site;
        // everyone else can pick a site to focus every KPI and chart below.
        $user = $request->user();
        $sites = Site::where('is_active', true)
            ->when($user->isSiteBound(), fn ($q) => $q->whereKey($user->site_id))
            ->orderBy('name')->get();
        $siteId = $request->filled('site_id') ? $request->integer('site_id') : null;
        $bySite = fn ($q) => $q->when($siteId, fn ($x) => $x->whereHas('employee', fn ($e) => $e->where('site_id', $siteId)));

        $totalEmployees = Employee::where('is_active', true)->when($siteId, fn ($q) => $q->where('site_id', $siteId))->count();
        $withoutFace = Employee::where('is_active', true)->whereNull('face_descriptor')->when($siteId, fn ($q) => $q->where('site_id', $siteId))->count();
        $pendingVacations = Vacation::inCurrentSite()->pending()->when($siteId, $bySite)->count();
        $pendingJustifications = Justification::inCurrentSite()->pending()->when($siteId, $bySite)->count();

        // Per-site breakdown: employees + who is present today, one row per site
        $siteBreakdown = $sites->map(function ($site) use ($today) {
            $employees = Employee::where('is_active', true)->where('site_id', $site->id)->count();
            $present = Attendance::whereDate('date', $today)->whereIn('status', ['ON_TIME', 'LATE'])
                ->whereHas('employee', fn ($e) => $e->where('site_id', $site->id))->count();

            return [
                'name' => $site->name,
                'employees' => $employees,
                'present' => $present,
                'rate' => $employees > 0 ? (int) round($present * 100 / $employees) : 0,
            ];
        });

        // Today's marks split by status (KPIs + doughnut)
        $todayByStatus = Attendance::inCurrentSite()->whereDate('date', $today)->when($siteId, $bySite)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $attendancesToday = (int) $todayByStatus->sum();
        $lateToday = (int) ($todayByStatus['LATE'] ?? 0);
        $presentToday = (int) (($todayByStatus['ON_TIME'] ?? 0) + ($todayByStatus['LATE'] ?? 0));
        $attendanceRate = $totalEmployees > 0 ? (int) round($presentToday * 100 / $totalEmployees) : 0;

        // Last 7 days, one stacked series per status (single grouped query)
        $weekRaw = Attendance::inCurrentSite()->whereBetween('date', [$weekStart, $today])->when($siteId, $bySite)
            ->selectRaw('date, status, count(*) as total')
            ->groupBy('date', 'status')
            ->get()
            ->groupBy(fn ($row) => $row->date->toDateString());

        $labels = [];
        $statusSeries = array_fill_keys(Attendance::STATUSES, []);
        for ($i = 6; $i >= 0; $i--) {
            $day = company_now()->subDays($i)->toDateString();
            $labels[] = company_now()->subDays($i)->format('d/m');
            $byStatus = $weekRaw->get($day)?->pluck('total', 'status') ?? collect();
            foreach (Attendance::STATUSES as $status) {
                $statusSeries[$status][] = (int) ($byStatus[$status] ?? 0);
            }
        }

        $latest = Attendance::with('employee')
            ->inCurrentSite()
            ->whereDate('date', $today)
            ->when($siteId, $bySite)
            ->latest('updated_at')
            ->take(8)
            ->get();

        // Small work queue: the most recent items waiting for a decision
        $pendingVacationList = Vacation::inCurrentSite()->pending()->when($siteId, $bySite)->with('employee')->latest()->take(4)->get();
        $pendingJustificationList = Justification::inCurrentSite()->pending()->when($siteId, $bySite)->with('employee')->latest()->take(4)->get();

        return view('dashboard', [
            'isManager' => true,
            'totalEmployees' => $totalEmployees,
            'withoutFace' => $withoutFace,
            'attendancesToday' => $attendancesToday,
            'lateToday' => $lateToday,
            'attendanceRate' => $attendanceRate,
            'pendingVacations' => $pendingVacations,
            'pendingJustifications' => $pendingJustifications,
            'todayByStatus' => $todayByStatus,
            'labels' => $labels,
            'statusSeries' => $statusSeries,
            'latest' => $latest,
            'pendingVacationList' => $pendingVacationList,
            'pendingJustificationList' => $pendingJustificationList,
            'sites' => $sites,
            'siteId' => $siteId,
            'siteBreakdown' => $siteBreakdown,
        ]);
    }

    /** Personal dashboard (employee profile): only their own information */
    private function employeeDashboard($user)
    {
        $employee = Employee::with('schedule')->where('user_id', $user->id)->first();
        $today = company_now()->toDateString();
        $monthStart = company_now()->startOfMonth()->toDateString();

        $todayAttendance = null;
        $daysThisMonth = 0;
        $lateThisMonth = 0;
        $remainingVacation = 0;
        $myVacations = collect();
        $recent = collect();
        $trendLabels = [];
        $trendWorked = [];

        if ($employee) {
            $todayAttendance = $employee->attendances()->whereDate('date', $today)->first();

            $thisMonth = $employee->attendances()->whereBetween('date', [$monthStart, $today])->get();
            $daysThisMonth = $thisMonth->whereIn('status', ['ON_TIME', 'LATE'])->count();
            $lateThisMonth = $thisMonth->where('status', 'LATE')->count();
            $remainingVacation = $employee->remainingVacationDays();

            $myVacations = $employee->vacations()->latest()->take(3)->get();
            $recent = $employee->attendances()->orderByDesc('date')->take(7)->get();

            // Worked-days trend, last 6 months (one grouped query)
            $sixMonthsAgo = company_now()->copy()->subMonths(5)->startOfMonth();
            $byMonth = $employee->attendances()
                ->where('date', '>=', $sixMonthsAgo->toDateString())
                ->whereIn('status', ['ON_TIME', 'LATE'])
                ->get()
                ->groupBy(fn ($a) => $a->date->format('Y-m'));
            for ($i = 5; $i >= 0; $i--) {
                $m = company_now()->copy()->subMonths($i);
                $trendLabels[] = ucfirst($m->locale(app()->getLocale())->translatedFormat('M'));
                $trendWorked[] = $byMonth->get($m->format('Y-m'))?->count() ?? 0;
            }
        }

        return view('dashboard', [
            'isManager' => false,
            'employee' => $employee,
            'todayAttendance' => $todayAttendance,
            'daysThisMonth' => $daysThisMonth,
            'lateThisMonth' => $lateThisMonth,
            'remainingVacation' => $remainingVacation,
            'myVacations' => $myVacations,
            'recent' => $recent,
            'trendLabels' => $trendLabels,
            'trendWorked' => $trendWorked,
        ]);
    }
}
