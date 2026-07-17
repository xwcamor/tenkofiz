<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
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

        $totalEmployees = Employee::where('is_active', true)->count();
        $attendancesToday = Attendance::whereDate('date', $today)->count();
        $lateToday = Attendance::whereDate('date', $today)->where('status', 'LATE')->count();
        $pendingVacations = Vacation::pending()->count();
        $withoutFace = Employee::where('is_active', true)->whereNull('face_descriptor')->count();

        $latest = Attendance::with('employee')
            ->whereDate('date', $today)
            ->latest('updated_at')
            ->take(8)
            ->get();

        $labels = [];
        $attendanceSeries = [];
        $lateSeries = [];
        for ($i = 6; $i >= 0; $i--) {
            $day = company_now()->subDays($i)->toDateString();
            $labels[] = company_now()->subDays($i)->format('d/m');
            $attendanceSeries[] = Attendance::whereDate('date', $day)->count();
            $lateSeries[] = Attendance::whereDate('date', $day)->where('status', 'LATE')->count();
        }

        return view('dashboard', [
            'isManager' => true,
            'totalEmployees' => $totalEmployees,
            'attendancesToday' => $attendancesToday,
            'lateToday' => $lateToday,
            'pendingVacations' => $pendingVacations,
            'withoutFace' => $withoutFace,
            'latest' => $latest,
            'labels' => $labels,
            'attendanceSeries' => $attendanceSeries,
            'lateSeries' => $lateSeries,
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
        $myVacations = collect();
        $recent = collect();

        if ($employee) {
            $todayAttendance = $employee->attendances()->whereDate('date', $today)->first();

            $thisMonth = $employee->attendances()->whereBetween('date', [$monthStart, $today])->get();
            $daysThisMonth = $thisMonth->whereIn('status', ['ON_TIME', 'LATE'])->count();
            $lateThisMonth = $thisMonth->where('status', 'LATE')->count();

            $myVacations = $employee->vacations()->latest()->take(3)->get();
            $recent = $employee->attendances()->orderByDesc('date')->take(7)->get();
        }

        return view('dashboard', [
            'isManager' => false,
            'employee' => $employee,
            'todayAttendance' => $todayAttendance,
            'daysThisMonth' => $daysThisMonth,
            'lateThisMonth' => $lateThisMonth,
            'myVacations' => $myVacations,
            'recent' => $recent,
        ]);
    }
}
