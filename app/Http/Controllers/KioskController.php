<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\Employee;
use App\Models\Holiday;
use Illuminate\Http\Request;

class KioskController extends Controller
{
    /** Euclidean distance threshold: lower = more similar (0.55 balances accuracy and light tolerance) */
    public const THRESHOLD = 0.55;

    /** Minimum minutes that must pass between check-in and check-out (avoids double marking) */
    public const MIN_MINUTES_BEFORE_CHECKOUT = 30;

    public function index()
    {
        return view('kiosk.index');
    }

    /** Returns the descriptors of every enrolled employee (matching happens in the browser) */
    public function descriptors()
    {
        $employees = Employee::where('is_active', true)
            ->whereNotNull('face_descriptor')
            ->get(['id', 'first_name', 'last_name', 'face_descriptor']);

        return response()->json($employees->map(function ($employee) {
            $data = json_decode($employee->face_descriptor, true);
            // Compatibility: old format = one flat vector; new = list of vectors
            $descriptors = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data : [$data];

            return [
                'id' => $employee->id,
                'name' => $employee->full_name,
                'descriptors' => $descriptors,
            ];
        }));
    }

    /** Records a check-in or check-out as appropriate */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'distance' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        if ($data['distance'] > self::THRESHOLD) {
            return response()->json(['ok' => false, 'message' => __('Face not recognized with enough confidence.')], 422);
        }

        $employee = Employee::with('schedule')->findOrFail($data['employee_id']);

        // All business rules run on company local time; the server stores UTC timestamps
        $now = company_now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');

        // Blocked on holidays
        if ($holiday = Holiday::onDate($today)) {
            return response()->json([
                'ok' => false,
                'message' => __('Today is a holiday (:name): attendance marking is not required.', ['name' => $holiday->name]),
            ], 422);
        }

        // Blocked while the employee is on approved vacation
        if ($employee->onVacation($today)) {
            return response()->json([
                'ok' => false,
                'message' => __(':name is on vacation: attendance marking is not required.', ['name' => $employee->full_name]),
            ], 422);
        }

        $attendance = Attendance::firstOrNew(['employee_id' => $employee->id, 'date' => $today]);

        // Kiosk device audit trail
        $device = ['ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255)];

        if (!$attendance->exists) {
            // First mark of the day = CHECK-IN
            $status = 'ON_TIME';
            if ($employee->schedule) {
                $limit = \Carbon\Carbon::parse($today.' '.$employee->schedule->start_time, company_timezone())
                    ->addMinutes($employee->schedule->tolerance_minutes);
                if ($now->greaterThan($limit)) {
                    $status = 'LATE';
                }
            }
            $attendance->fill([
                'check_in' => $currentTime,
                'status' => $status,
                'method' => 'FACIAL',
                'similarity' => $data['distance'],
            ] + $device)->save();

            return response()->json([
                'ok' => true,
                'type' => 'CHECK_IN',
                'status' => $status,
                'status_label' => __($status),
                'employee' => $employee->full_name,
                'time' => $now->format('h:i a'),
            ]);
        }

        if (is_null($attendance->check_out)) {
            // Enforce a minimum time since check-in (business rule against double marking)
            $checkIn = \Carbon\Carbon::parse($today.' '.$attendance->check_in, company_timezone());
            $elapsedMinutes = (int) $checkIn->diffInMinutes($now);

            if ($elapsedMinutes < self::MIN_MINUTES_BEFORE_CHECKOUT) {
                return response()->json([
                    'ok' => false,
                    'message' => __(':name: your check-in was already recorded at :time. You can check out in :minutes minute(s).', [
                        'name' => $employee->full_name,
                        'time' => $checkIn->format('h:i a'),
                        'minutes' => self::MIN_MINUTES_BEFORE_CHECKOUT - $elapsedMinutes,
                    ]),
                ], 422);
            }

            // Second mark = CHECK-OUT
            $attendance->update(['check_out' => $currentTime] + $device);

            return response()->json([
                'ok' => true,
                'type' => 'CHECK_OUT',
                'status' => $attendance->status,
                'status_label' => __($attendance->status),
                'employee' => $employee->full_name,
                'time' => $now->format('h:i a'),
            ]);
        }

        return response()->json([
            'ok' => false,
            'message' => __(':name already checked in and out today.', ['name' => $employee->full_name]),
        ], 422);
    }
}
