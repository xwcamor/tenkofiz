<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Justification;
use App\Models\Vacation;
use Illuminate\Http\Request;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = $request->user();

        // Managers see the global dashboard; everyone else sees their own info
        if ($user->isManager()) {
            return $this->managerDashboard();
        }

        return $this->employeeDashboard($user);
    }

    /** Global dashboard (managers) */
    private function managerDashboard()
    {
        $today = company_now()->toDateString();
        $weekStart = company_now()->subDays(6)->toDateString();

        $totalEmployees = Employee::where('is_active', true)->count();
        $withoutFace = Employee::where('is_active', true)->whereNull('face_descriptor')->count();
        $pendingVacations = Vacation::pending()->count();
        $pendingJustifications = Justification::pending()->count();

        // Today's marks split by status (KPIs + doughnut)
        $todayByStatus = Attendance::whereDate('date', $today)
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status');

        $attendancesToday = (int) $todayByStatus->sum();
        $lateToday = (int) ($todayByStatus['LATE'] ?? 0);
        $presentToday = (int) (($todayByStatus['ON_TIME'] ?? 0) + ($todayByStatus['LATE'] ?? 0));
        $attendanceRate = $totalEmployees > 0 ? (int) round($presentToday * 100 / $totalEmployees) : 0;

        // Last 7 days, one stacked series per status (single grouped query)
        $weekRaw = Attendance::whereBetween('date', [$weekStart, $today])
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
            ->whereDate('date', $today)
            ->latest('updated_at')
            ->take(8)
            ->get();

        // Small work queue: the most recent items waiting for a decision
        $pendingVacationList = Vacation::pending()->with('employee')->latest()->take(4)->get();
        $pendingJustificationList = Justification::pending()->with('employee')->latest()->take(4)->get();

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
