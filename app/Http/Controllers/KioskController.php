<?php

namespace App\Http\Controllers;

use App\Models\Attendance;
use App\Models\AuditLog;
use App\Models\Employee;
use App\Models\Holiday;
use App\Models\Site;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class KioskController extends Controller
{
    /** Euclidean distance threshold: lower = more similar (0.55 balances accuracy and light tolerance) */
    public const THRESHOLD = 0.55;

    /** Minimum minutes that must pass between check-in and check-out (avoids double marking) */
    public const MIN_MINUTES_BEFORE_CHECKOUT = 30;

    /** How long the enrollment mode stays unlocked after entering the PIN */
    public const ENROLL_SESSION_MINUTES = 15;

    /** How long the person picked on the keypad stays valid for the camera step */
    public const VERIFY_SESSION_SECONDS = 180;

    public function index(Request $request)
    {
        // Multi-site: a site kiosk link carries ?site=ID; remember it for this device
        if ($request->filled('site')) {
            $site = Site::where('is_active', true)->find($request->integer('site'));
            $request->session()->put('kiosk_site', $site?->id);
        }

        $site = $request->session()->get('kiosk_site') ? Site::find($request->session()->get('kiosk_site')) : null;

        return view('kiosk.index', ['site' => $site]);
    }

    /**
     * Step 1 of the marking flow (document-first): validate the typed document
     * against this site's active employees. Only when it exists does the kiosk
     * move on to the camera page — the document filters BEFORE any camera opens.
     */
    public function lookup(Request $request)
    {
        $data = $request->validate(['document_number' => ['required', 'regex:/^\d{8,12}$/']]);

        $employee = $this->scopedEmployees($request)
            ->where('document_number', $data['document_number'])
            ->first();

        if (!$employee) {
            return response()->json([
                'ok' => false,
                'message' => __('That document does not belong to an active employee of this site. Check the number or contact your supervisor.'),
            ], 404);
        }

        $request->session()->put('kiosk_verify_doc', $employee->document_number);
        $request->session()->put('kiosk_verify_until', now()->addSeconds(self::VERIFY_SESSION_SECONDS)->timestamp);

        return response()->json(['ok' => true, 'redirect' => route('kiosk.verify')]);
    }

    /**
     * Step 2: the camera page for the person validated on the keypad. If they have
     * an enrolled face it verifies 1:1; if not, they can enroll right here (consent
     * + supervisor PIN when configured) so the next mark is already facial.
     */
    public function verifyPage(Request $request)
    {
        $document = $request->session()->get('kiosk_verify_doc');
        $until = (int) $request->session()->get('kiosk_verify_until', 0);

        if (!$document || $until < now()->timestamp) {
            return redirect()->route('kiosk'); // expired or direct access: back to the keypad
        }

        $employee = $this->scopedEmployees($request)->where('document_number', $document)->first();
        if (!$employee) {
            return redirect()->route('kiosk');
        }

        $site = $request->session()->get('kiosk_site') ? Site::find($request->session()->get('kiosk_site')) : null;

        return view('kiosk.verify', [
            'employee' => $employee,
            'site' => $site,
            'enrollPinConfigured' => (bool) app_setting()->kiosk_enroll_pin,
            'enrollUnlocked' => $this->enrollUnlocked($request),
        ]);
    }

    /** Supervisor enrollment as its own page (no stacked modals over the camera) */
    public function enrollPage(Request $request)
    {
        $site = $request->session()->get('kiosk_site') ? Site::find($request->session()->get('kiosk_site')) : null;

        return view('kiosk.enroll', [
            'site' => $site,
            'enrollUnlocked' => $this->enrollUnlocked($request),
        ]);
    }

    /** Device pairing page (outside the kiosk gate; the one-time code is the secret) */
    public function showPair(Request $request)
    {
        return view('kiosk.pair', ['code' => (string) $request->query('code', '')]);
    }

    /**
     * Binds THIS device: validates the one-time code, stores the hash of a new
     * device secret and sets a long-lived cookie. From now on only this device
     * (which carries the cookie) can open the kiosk — a copied URL cannot.
     */
    public function pair(Request $request)
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);
        $code = strtoupper(trim($data['code']));

        // The one-time code identifies which site to bind — no need to pass the site
        $site = Site::where('kiosk_pair_code', $code)
            ->where('kiosk_pair_expires_at', '>', now())
            ->first();

        if (!$site) {
            return back()->withErrors(['code' => __('Invalid or expired pairing code. Ask an administrator for a new one.')]);
        }

        $secret = Str::random(48);
        $site->update([
            'kiosk_device_hash' => hash('sha256', $secret),
            'kiosk_pair_code' => null,
            'kiosk_pair_expires_at' => null,
        ]);

        AuditLog::record('UPDATE', 'Sites', __('A kiosk device was paired to site :name', ['name' => $site->name]));

        // 10-year cookie identifies this device; open this site's kiosk
        return redirect($site->kioskLink())->withCookie(cookie('kiosk_device', $secret, 60 * 24 * 365 * 10));
    }

    /** Employees scoped to the kiosk's site (all sites when none is selected) */
    private function scopedEmployees(Request $request)
    {
        return Employee::where('is_active', true)
            ->when($request->session()->get('kiosk_site'), fn ($q, $siteId) => $q->where('site_id', $siteId));
    }

    /** Returns the descriptors of every enrolled employee (matching happens in the browser) */
    public function descriptors(Request $request)
    {
        $employees = $this->scopedEmployees($request)
            ->whereNotNull('face_descriptor')
            ->get(['id', 'first_name', 'last_name', 'face_descriptor']);

        return response()->json([
            'version' => $this->descriptorsVersion($request),
            'employees' => $employees->map(function ($employee) {
                $data = json_decode($employee->face_descriptor, true);
                // Compatibility: old format = one flat vector; new = list of vectors
                $descriptors = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data : [$data];

                return [
                    'id' => $employee->id,
                    'name' => $employee->full_name,
                    'descriptors' => $descriptors,
                ];
            }),
        ]);
    }

    /**
     * Tiny endpoint (~60 bytes) the kiosk polls every few minutes: the full
     * descriptor list is only re-downloaded when this fingerprint changes.
     */
    public function version(Request $request)
    {
        return response()->json(['version' => $this->descriptorsVersion($request)]);
    }

    private function descriptorsVersion(Request $request): string
    {
        $enrolled = $this->scopedEmployees($request)->whereNotNull('face_descriptor');

        return md5(($request->session()->get('kiosk_site') ?? 'all').'|'.$enrolled->count().'|'.($enrolled->max('updated_at') ?? ''));
    }

    /** Face descriptors of ONE person (for 1:1 verification after typing the DNI) */
    public function personFace(Request $request, string $document)
    {
        $employee = $this->scopedEmployees($request)
            ->whereNotNull('face_descriptor')
            ->where('document_number', strtoupper(trim($document)))
            ->first(['id', 'first_name', 'last_name', 'face_descriptor']);

        if (!$employee) {
            // 404 = "not enrolled / not here": the browser falls back to DNI + photo
            return response()->json(['ok' => false], 404);
        }

        $data = json_decode($employee->face_descriptor, true);
        $descriptors = is_array($data) && isset($data[0]) && is_array($data[0]) ? $data : [$data];

        return response()->json([
            'ok' => true,
            'id' => $employee->id,
            'name' => $employee->full_name,
            'descriptors' => $descriptors,
        ]);
    }

    /**
     * Records a check-in or check-out after a facial match.
     *
     * No photo is stored here (decided with the product owner): a FACIAL mark
     * already carries strong proof — the recorded match distance plus the
     * completed liveness gesture — so a snapshot would only be redundant bytes
     * on disk. Photos are reserved for the DNI fallback (markByDni), where the
     * recognition failed and a supervisor needs something to review.
     */
    public function mark(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'distance' => ['required', 'numeric', 'min:0', 'max:1'],
        ]);

        if ($data['distance'] > (float) (app_setting()->kiosk_face_threshold ?: self::THRESHOLD)) {
            return response()->json(['ok' => false, 'message' => __('Face not recognized with enough confidence.')], 422);
        }

        $employee = $this->scopedEmployees($request)->with('schedule')->find($data['employee_id']);
        if (!$employee) {
            return response()->json(['ok' => false, 'message' => __('No active employee found with that document number.')], 422);
        }

        return $this->performMark($request, $employee, 'FACIAL', ['similarity' => $data['distance']]);
    }

    /**
     * Fallback when the face is not detected: the employee types their document
     * number and an evidence snapshot is stored for supervisor verification.
     */
    public function markByDni(Request $request)
    {
        $data = $request->validate([
            'document_number' => ['required', 'digits_between:8,12'],
            'photo' => ['nullable', 'string', 'max:1500000'], // base64 JPEG snapshot
        ]);

        $employee = $this->scopedEmployees($request)->with('schedule')
            ->where('document_number', $data['document_number'])
            ->first();

        if (!$employee) {
            return response()->json(['ok' => false, 'message' => __('No active employee found with that document number.')], 422);
        }

        // Business rule (Carlos): document marking is ONLY the fallback for people
        // who already enrolled their face and recognition failed. Someone without
        // an enrolled face must enroll first — they cannot mark by document.
        if (!$employee->hasFace()) {
            return response()->json([
                'ok' => false,
                'message' => __('You have no enrolled face yet. Enroll it on this kiosk (one time) to be able to mark.'),
            ], 422);
        }

        $evidencePath = $this->storeEvidencePhoto($data['photo'] ?? null);

        return $this->performMark($request, $employee, 'DNI', ['evidence_photo' => $evidencePath]);
    }

    /** Shared business rules for facial and DNI marking */
    private function performMark(Request $request, Employee $employee, string $method, array $extra = [])
    {
        // All business rules run on company local time; the server stores UTC timestamps
        $now = company_now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $setting = app_setting();

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

        // Kiosk device audit trail
        $device = ['ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255)];

        // Overnight shifts: if yesterday's shift crosses midnight and is still open,
        // a mark before noon closes it as that shift's CHECK-OUT instead of opening today.
        $yesterday = $now->copy()->subDay();
        $yesterdayShift = $employee->schedule?->worksOn($yesterday->dayOfWeek);
        if ($yesterdayShift && $yesterdayShift->crossesMidnight() && $now->format('H:i') < '12:00') {
            $openOvernight = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $yesterday->toDateString())
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->first();

            if ($openOvernight) {
                // Overnight shift end lands on today's date (it crossed midnight)
                $scheduledEnd = \Carbon\Carbon::parse($today.' '.$yesterdayShift->end_time, company_timezone());
                $earlyNote = $this->earlyDepartureNote($setting, $openOvernight->note, $scheduledEnd, $now);

                $openOvernight->update(['check_out' => $currentTime] + $earlyNote + $device);

                return response()->json([
                    'ok' => true,
                    'type' => 'CHECK_OUT',
                    'status' => $openOvernight->status,
                    'status_label' => __($openOvernight->status),
                    'employee' => $employee->full_name,
                    'time' => $now->format('h:i a'),
                ]);
            }
        }

        $attendance = Attendance::firstOrNew(['employee_id' => $employee->id, 'date' => $today]);

        if (!$attendance->exists) {
            // First mark of the day = CHECK-IN; lateness depends on TODAY's weekday hours
            $todayShift = $employee->schedule?->worksOn($now->dayOfWeek);
            $status = 'ON_TIME';
            if (!$todayShift) {
                // No shift today (e.g. Sunday on a Mon-Sat schedule): there is no
                // start time to be late against, so the mark records as on-time —
                // but say so explicitly instead of looking like a normal "on time".
                $extra['note'] = trim((($extra['note'] ?? '').' '.__('Mark on a non-working day according to their schedule.')));
            }
            if ($todayShift) {
                $start = \Carbon\Carbon::parse($today.' '.$todayShift->start_time, company_timezone());

                // Early check-in window: reject marks made too long before the shift starts
                if ($setting->early_check_in_minutes > 0) {
                    $earliest = $start->copy()->subMinutes($setting->early_check_in_minutes);
                    if ($now->lessThan($earliest)) {
                        return response()->json([
                            'ok' => false,
                            'message' => __(':name starts at :start. You can check in from :earliest.', [
                                'name' => $employee->full_name,
                                'start' => $start->format('h:i a'),
                                'earliest' => $earliest->format('h:i a'),
                            ]),
                        ], 422);
                    }
                }

                if ($now->greaterThan($start->copy()->addMinutes($employee->schedule->tolerance_minutes))) {
                    $status = 'LATE';
                }
            }
            $attendance->fill([
                'check_in' => $currentTime,
                'status' => $status,
                'method' => $method,
            ] + $extra + $device)->save();

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
                // Do not reveal the exact wait time (internal rule against double marking)
                return response()->json([
                    'ok' => false,
                    'message' => __(':name, your check-in was already recorded at :time. Try again later to record your check-out.', [
                        'name' => $employee->full_name,
                        'time' => $checkIn->format('h:i a'),
                    ]),
                ], 422);
            }

            // Early-departure flag if leaving well before the scheduled end
            $todayShift = $employee->schedule?->worksOn($now->dayOfWeek);
            $earlyNote = [];
            if ($todayShift) {
                $scheduledEnd = \Carbon\Carbon::parse($today.' '.$todayShift->end_time, company_timezone());
                $earlyNote = $this->earlyDepartureNote($setting, $attendance->note, $scheduledEnd, $now);
            }

            // Second mark = CHECK-OUT (keep the check-in evidence if it exists)
            $attendance->update(['check_out' => $currentTime] + $earlyNote + array_filter($extra) + $device);

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

    /**
     * Returns ['note' => ...] when the check-out happens more than
     * early_departure_minutes before the scheduled end, otherwise []. The mark
     * is never blocked: this only leaves an audit note for the supervisor.
     */
    private function earlyDepartureNote($setting, ?string $existingNote, \Carbon\Carbon $scheduledEnd, \Carbon\Carbon $now): array
    {
        $grace = (int) $setting->early_departure_minutes;
        if ($grace <= 0 || $now->greaterThanOrEqualTo($scheduledEnd->copy()->subMinutes($grace))) {
            return [];
        }

        $minutesEarly = (int) round($now->diffInMinutes($scheduledEnd));
        $note = __('Early departure (:minutes min before the scheduled end)', ['minutes' => $minutesEarly]);

        return ['note' => $existingNote ? $existingNote.' — '.$note : $note];
    }

    /** Persists the base64 snapshot sent with DNI marks (evidence for supervisors) */
    private function storeEvidencePhoto(?string $base64): ?string
    {
        if (!$base64) {
            return null;
        }

        $data = preg_replace('/^data:image\/\w+;base64,/', '', $base64);
        $binary = base64_decode($data, true);
        if ($binary === false || strlen($binary) < 100) {
            return null;
        }

        $dir = public_path('uploads/kiosk_evidence');
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $name = uniqid('mark_').'.jpg';
        file_put_contents($dir.'/'.$name, $binary);

        return 'uploads/kiosk_evidence/'.$name;
    }

    // ---------- Self-enrollment mode (unlocked with the supervisor PIN) ----------

    /** Unlocks the enrollment mode for a few minutes */
    public function enrollUnlock(Request $request)
    {
        $data = $request->validate(['pin' => ['required', 'digits_between:4,8']]);

        $pin = app_setting()->kiosk_enroll_pin;

        if (!$pin) {
            return response()->json(['ok' => false, 'message' => __('Enrollment mode is disabled: set a PIN in Settings first.')], 422);
        }

        if (!hash_equals($pin, $data['pin'])) {
            return response()->json(['ok' => false, 'message' => __('Incorrect PIN.')], 422);
        }

        $request->session()->put('kiosk_enroll_until', now()->addMinutes(self::ENROLL_SESSION_MINUTES)->timestamp);

        return response()->json(['ok' => true, 'minutes' => self::ENROLL_SESSION_MINUTES]);
    }

    private function enrollUnlocked(Request $request): bool
    {
        return $request->session()->get('kiosk_enroll_until', 0) >= now()->timestamp;
    }

    /** Finds the employee to enroll by document number (must already exist) */
    public function enrollLookup(Request $request)
    {
        abort_unless($this->enrollUnlocked($request), 403, __('Enrollment mode is locked.'));

        $data = $request->validate(['document_number' => ['required', 'digits_between:8,12']]);

        $employee = Employee::where('is_active', true)
            ->where('document_number', $data['document_number'])
            ->first();

        if (!$employee) {
            return response()->json([
                'ok' => false,
                'message' => __('No active employee found with that document number. Ask an administrator to register them first.'),
            ], 422);
        }

        return response()->json([
            'ok' => true,
            'employee_id' => $employee->id,
            'name' => $employee->full_name,
            'has_face' => $employee->hasFace(),
        ]);
    }

    /** Stores the face samples captured on the kiosk (requires on-screen consent) */
    public function enrollDescriptor(Request $request)
    {
        $data = $request->validate([
            'employee_id' => ['required', 'exists:employees,id'],
            'consent' => ['accepted'],
            'descriptors' => ['required', 'array', 'min:1', 'max:5'],
            'descriptors.*' => ['required', 'array', 'size:128'],
            'descriptors.*.*' => ['numeric'],
        ]);

        $employee = Employee::findOrFail($data['employee_id']);

        // Two ways to be authorized:
        //  1. supervisor PIN entered on this tablet (15-min session), OR
        //  2. OPEN self-enrollment: no PIN is configured AND the person being
        //     enrolled is exactly the one validated on the keypad (kiosk_verify_doc)
        //     — this is the "enroll yourself on your first mark" flow.
        $openSelfEnroll = !app_setting()->kiosk_enroll_pin
            && $request->session()->get('kiosk_verify_doc') === $employee->document_number;

        abort_unless($this->enrollUnlocked($request) || $openSelfEnroll, 403, __('Enrollment mode is locked.'));

        $employee->update([
            'face_descriptor' => json_encode($data['descriptors']),
            'biometric_consent_at' => $employee->biometric_consent_at ?? now(),
        ]);

        AuditLog::record('UPDATE', 'Employees',
            __('Face enrolled for :name (:count samples, consent recorded)', [
                'name' => $employee->full_name,
                'count' => count($data['descriptors']),
            ]).' — '.__('kiosk enrollment mode'));

        return response()->json([
            'ok' => true,
            'message' => __('Face enrolled with :count samples (verified in the database).', ['count' => count($data['descriptors'])]),
        ]);
    }
}
