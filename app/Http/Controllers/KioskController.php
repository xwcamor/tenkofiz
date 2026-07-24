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

        // Fail fast: if they plainly cannot mark now (holiday, vacation, too early),
        // say so here instead of after the whole camera step.
        if ($preCheck = $this->keypadPreCheck($employee)) {
            return response()->json(['ok' => false, 'message' => $preCheck], 422);
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
            'nextAction' => $this->nextMarkAction($employee),
            'earlyExitWarn' => $this->earlyExitWarning($employee),
        ]);
    }

    /**
     * What the person's NEXT kiosk mark will be, so the camera page can adapt its
     * UI (e.g. ask "break or check-out?"). Best-effort for the UI — performMark is
     * still the authority. Returns 'CHECK_IN'|'BREAK_OUT'|'BREAK_IN'|'CHECK_OUT'|
     * 'AMBIGUOUS'|'DONE'.
     */
    private function nextMarkAction(Employee $employee): string
    {
        // Free mode: every mark is just a capture; the UI always offers to mark.
        if ($employee->scheduleOn(company_now())?->isFree()) {
            return 'FREE';
        }

        $setting = app_setting();
        $today = company_now()->toDateString();
        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->first();

        if (!$attendance || is_null($attendance->check_in)) {
            return 'CHECK_IN';
        }
        if ($attendance->check_out) {
            return 'DONE';
        }
        if ($setting->kiosk_breaks_enabled) {
            if ($attendance->break_out && is_null($attendance->break_in)) {
                return 'BREAK_IN';
            }
            if (is_null($attendance->break_out)) {
                return $setting->break_required ? 'BREAK_OUT' : 'AMBIGUOUS';
            }
        }

        return 'CHECK_OUT';
    }

    /**
     * True when checking out NOW would be premature, so the kiosk asks the person
     * to confirm instead of silently recording it. This replaces the old
     * "minimum minutes between marks" rule (no magic number): a second mark right
     * after check-in is always premature, so a stray/"just playing" re-mark is
     * caught and confirmed rather than accidentally closing the day.
     *   - Fixed schedule: premature = before the scheduled end time.
     *   - Flexible schedule: premature = the daily hour target is not met yet.
     */
    private function isEarlyCheckout(Employee $employee, \Carbon\Carbon $now, Attendance $attendance): bool
    {
        $schedule = $employee->scheduleOn($now);
        if (!$schedule || !$attendance->check_in) {
            return false;
        }

        $checkIn = \Carbon\Carbon::parse($now->toDateString().' '.$attendance->check_in, company_timezone());

        if ($schedule->isFlexible()) {
            $target = $schedule->expectedMinutesFor($now->dayOfWeek);

            return $target > 0 && (int) $checkIn->diffInMinutes($now) < $target;
        }

        $shift = $schedule->worksOn($now->dayOfWeek);
        if (!$shift) {
            return false;
        }

        return $now->lessThan(\Carbon\Carbon::parse($now->toDateString().' '.$shift->end_time, company_timezone()));
    }

    /** True when the next mark would be a check-out and it would be premature (for the UI pre-prompt) */
    private function earlyExitWarning(Employee $employee): bool
    {
        if (!in_array($this->nextMarkAction($employee), ['CHECK_OUT', 'AMBIGUOUS'], true)) {
            return false;
        }

        $attendance = Attendance::where('employee_id', $employee->id)->whereDate('date', company_now()->toDateString())->first();

        return $attendance && $this->isEarlyCheckout($employee, company_now(), $attendance);
    }

    /** Human message for the early-checkout confirmation */
    private function earlyCheckoutMessage(Employee $employee, \Carbon\Carbon $now, \Carbon\Carbon $checkIn): string
    {
        $schedule = $employee->scheduleOn($now);
        $tail = ' '.__('Only your time worked up to now will count.');

        if ($schedule && !$schedule->isFlexible()) {
            $shift = $schedule->worksOn($now->dayOfWeek);
            if ($shift) {
                return __(':name, you checked in at :in. It is not your end time yet (:end). Do you want to record your CHECK-OUT?', [
                    'name' => $employee->full_name,
                    'in' => $checkIn->format('h:i a'),
                    'end' => \Carbon\Carbon::parse($now->toDateString().' '.$shift->end_time)->format('h:i a'),
                ]).$tail;
            }
        }

        return __(':name, you checked in at :in. You have not completed your workday yet. Do you want to record your CHECK-OUT?', [
            'name' => $employee->full_name,
            'in' => $checkIn->format('h:i a'),
        ]).$tail;
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
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16'],
            'device_name' => ['nullable', 'string', 'max:60'],
        ]);
        $code = strtoupper(trim($data['code']));

        // The one-time code identifies which site to bind — no need to pass the site
        $site = Site::where('kiosk_pair_code', $code)
            ->where('kiosk_pair_expires_at', '>', now())
            ->first();

        if (!$site) {
            return back()->withErrors(['code' => __('Invalid or expired pairing code. Ask an administrator for a new one.')]);
        }

        // Add THIS tablet as one more paired device for the site (multi-device):
        // the code is single-use, but each site can bind as many tablets as needed.
        $secret = Str::random(48);
        $site->kioskDevices()->create([
            'company_id' => $site->company_id,
            'name' => trim((string) ($data['device_name'] ?? '')) ?: __('Tablet :n', ['n' => $site->kioskDevices()->count() + 1]),
            'device_hash' => hash('sha256', $secret),
            'last_seen_at' => now(),
        ]);
        $site->update(['kiosk_pair_code' => null, 'kiosk_pair_expires_at' => null]);

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

        // Business rule: document marking is ONLY the fallback for people
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

    /**
     * "You cannot mark right now" reasons that apply to any mark (check-in or
     * check-out): today is a holiday, or the employee is on approved vacation.
     * Shared by lookup() (friendly pre-check at the keypad) and performMark()
     * (the authoritative guard). Returns the message, or null when clear.
     */
    private function hardBlockMessage(Employee $employee, \Carbon\Carbon $now): ?string
    {
        $today = $now->toDateString();

        // Holidays block marking UNLESS the company operates on holidays (a setting).
        if (!app_setting()->allow_holiday_marking && ($holiday = Holiday::onDate($today))) {
            return __('Today is a holiday (:name): attendance marking is not required.', ['name' => $holiday->name]);
        }

        if ($employee->onVacation($today)) {
            return __(':name is on vacation: attendance marking is not required.', ['name' => $employee->full_name]);
        }

        return null;
    }

    /**
     * "Too early to check in" message for the configured early-check-in window,
     * or null when the mark is within the allowed window. Only meaningful for a
     * CHECK-IN. Shared by lookup() and performMark().
     */
    private function earlyCheckInMessage($setting, Employee $employee, \App\Models\ScheduleDay $shift, \Carbon\Carbon $now): ?string
    {
        if ($setting->early_check_in_minutes <= 0) {
            return null;
        }

        $start = \Carbon\Carbon::parse($now->toDateString().' '.$shift->start_time, company_timezone());
        $earliest = $start->copy()->subMinutes($setting->early_check_in_minutes);

        if ($now->lessThan($earliest)) {
            return __(':name starts at :start. You can check in from :earliest.', [
                'name' => $employee->full_name,
                'start' => $start->format('h:i a'),
                'earliest' => $earliest->format('h:i a'),
            ]);
        }

        return null;
    }

    /**
     * Friendly pre-check for the keypad step: if this person plainly cannot mark
     * right now (holiday, vacation, too early for their shift) we say so HERE,
     * before sending them to the camera. Returns the message or null. The
     * authoritative enforcement still lives in performMark — this only saves the
     * person from doing the whole face step to be rejected at the end.
     */
    private function keypadPreCheck(Employee $employee): ?string
    {
        $now = company_now();

        if ($message = $this->hardBlockMessage($employee, $now)) {
            return $message;
        }

        // The early-check-in window applies only to a CHECK-IN. Determine whether
        // the next mark would be one: no attendance today AND no open overnight
        // shift from yesterday waiting to be closed as a check-out.
        $today = $now->toDateString();
        $hasToday = Attendance::where('employee_id', $employee->id)->whereDate('date', $today)->exists();
        $openOvernight = Attendance::where('employee_id', $employee->id)
            ->whereDate('date', $now->copy()->subDay()->toDateString())
            ->whereNotNull('check_in')->whereNull('check_out')->exists();

        $nowSchedule = $employee->scheduleOn($now);
        if (!$hasToday && !$openOvernight && $nowSchedule?->isFixed()) {
            $shift = $nowSchedule->worksOn($now->dayOfWeek);
            if ($shift) {
                return $this->earlyCheckInMessage(app_setting(), $employee, $shift, $now);
            }
        }

        return null;
    }

    /**
     * Append a raw punch to the ZKTeco-style log (additive; never affects the
     * in/out computation). Kept out of the response path so a logging hiccup can
     * never block a mark.
     */
    private function recordMark(Request $request, Employee $employee, Attendance $attendance, string $kind, string $method, ?string $photo = null): void
    {
        try {
            // Geolocation (only when enabled and sent by the kiosk browser)
            $lat = $lng = $accuracy = null;
            if (app_setting()->kiosk_geolocation
                && is_numeric($request->input('lat')) && is_numeric($request->input('lng'))) {
                $lat = round((float) $request->input('lat'), 7);
                $lng = round((float) $request->input('lng'), 7);
                $accuracy = is_numeric($request->input('accuracy')) ? (int) $request->input('accuracy') : null;
            }

            \App\Models\AttendanceMark::create([
                'company_id' => $employee->company_id,
                'employee_id' => $employee->id,
                'attendance_id' => $attendance->id,
                'marked_at' => now(),
                'kind' => $kind,
                'method' => $method,
                // Evidence photo of THIS punch (only DNI/document marks carry one), so
                // an admin can verify each mark individually — not just the day's first.
                'photo' => $photo,
                'lat' => $lat,
                'lng' => $lng,
                'accuracy' => $accuracy,
                'ip' => $request->ip(),
                'user_agent' => substr((string) $request->userAgent(), 0, 255),
            ]);
        } catch (\Throwable $e) {
            // Logging the punch is best-effort; the attendance itself is already saved.
        }
    }

    /**
     * Free-mode mark: no schedule judgment. Each punch is logged as its own
     * AttendanceMark (kind FREE) with its evidence; the day's Attendance row is a
     * light container whose span (first→last punch) is informational only. Any
     * number of marks per day is allowed. A human reviews the record.
     */
    private function recordFreeMark(Request $request, Employee $employee, \Carbon\Carbon $now, string $currentTime, string $today, string $method, array $extra, array $device)
    {
        $attendance = Attendance::firstOrNew(['employee_id' => $employee->id, 'date' => $today]);

        if (!$attendance->exists) {
            $attendance->fill([
                'check_in' => $currentTime,
                'status' => 'ON_TIME', // free mode never judges punctuality; neutral status
                'method' => $method,
                'expected_minutes' => 0,
            ] + array_filter($extra) + $device);
        } else {
            // Keep the last punch of the day as the closing bound (span = presence).
            $attendance->fill(['check_out' => $currentTime] + $device);
        }
        $attendance->save();

        $this->recordMark($request, $employee, $attendance, 'FREE', $method, $extra['evidence_photo'] ?? null);

        return response()->json([
            'ok' => true,
            'type' => 'FREE',
            'status' => 'FREE',
            'status_label' => __('Mark registered'),
            'employee' => $employee->full_name,
            'time' => $now->format('h:i a'),
        ]);
    }

    /** Shared business rules for facial and DNI marking */
    private function performMark(Request $request, Employee $employee, string $method, array $extra = [])
    {
        // All business rules run on company local time; the server stores UTC timestamps
        $now = company_now();
        $today = $now->toDateString();
        $currentTime = $now->format('H:i:s');
        $setting = app_setting();

        // Blocked on holidays or approved vacation (same rules pre-checked at the
        // keypad so the person is told BEFORE the camera opens — see lookup()).
        if ($blockMessage = $this->hardBlockMessage($employee, $now)) {
            return response()->json(['ok' => false, 'message' => $blockMessage], 422);
        }

        // Forced geolocation: no coordinates, no mark. The front-end already refuses
        // to open the camera without a location, but this is the authoritative guard
        // (a crafted request without the browser cannot slip a mark through).
        if ($setting->kiosk_geolocation && $setting->kiosk_geolocation_required
            && !(is_numeric($request->input('lat')) && is_numeric($request->input('lng')))) {
            return response()->json([
                'ok' => false,
                'message' => __('This kiosk requires your location to mark. Enable location and try again.'),
            ], 422);
        }

        // Kiosk device audit trail
        $device = ['ip' => $request->ip(), 'user_agent' => substr((string) $request->userAgent(), 0, 255)];

        // ---- Free mode: no schedule rules. Every mark is just a logged capture
        // (with its anti-fraud evidence, already verified above). No check-in/out
        // state machine, no tardiness, no break, any number of marks per day. ----
        if ($employee->scheduleOn($now)?->isFree()) {
            return $this->recordFreeMark($request, $employee, $now, $currentTime, $today, $method, $extra, $device);
        }

        // Overnight shifts: if yesterday's shift crosses midnight and is still open,
        // a mark before noon closes it as that shift's CHECK-OUT instead of opening today.
        $yesterday = $now->copy()->subDay();
        $yesterdayShift = $employee->scheduleOn($yesterday)?->worksOn($yesterday->dayOfWeek);
        if ($yesterdayShift && $yesterdayShift->crossesMidnight() && $now->format('H:i') < '12:00') {
            $openOvernight = Attendance::where('employee_id', $employee->id)
                ->whereDate('date', $yesterday->toDateString())
                ->whereNotNull('check_in')
                ->whereNull('check_out')
                ->first();

            if ($openOvernight) {
                $openOvernight->update(['check_out' => $currentTime] + $device);
                $this->recordMark($request, $employee, $openOvernight, 'CHECK_OUT', $method, $extra['evidence_photo'] ?? null);

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
            // First mark of the day = CHECK-IN; lateness depends on TODAY's weekday hours,
            // using the schedule in force on this date (schedules can change over time)
            $todaySchedule = $employee->scheduleOn($now);
            $todayShift = $todaySchedule?->worksOn($now->dayOfWeek);
            $status = 'ON_TIME';
            // Tag a mark made on a holiday (only reachable when the company allows it)
            if ($holiday = Holiday::onDate($today)) {
                $extra['note'] = trim((($extra['note'] ?? '').' '.__('Mark made on a holiday (:name).', ['name' => $holiday->name])));
            }
            if (!$todayShift) {
                // No shift today (e.g. Sunday on a Mon-Sat schedule): there is no
                // start time to be late against, so the mark records as on-time —
                // but say so explicitly instead of looking like a normal "on time".
                $extra['note'] = trim((($extra['note'] ?? '').' '.__('Mark on a non-working day according to their schedule.')));
            }
            // Flexible schedules have no fixed start: no early-check-in window and no
            // tardiness — the person only has to complete their daily hour target.
            if ($todayShift && $todaySchedule->isFixed()) {
                $start = \Carbon\Carbon::parse($today.' '.$todayShift->start_time, company_timezone());

                // Early check-in window: reject marks made too long before the shift
                // starts (also pre-checked at the keypad, see lookup()).
                if ($earlyMessage = $this->earlyCheckInMessage($setting, $employee, $todayShift, $now)) {
                    return response()->json(['ok' => false, 'message' => $earlyMessage], 422);
                }

                if ($now->greaterThan($start->copy()->addMinutes($todaySchedule->tolerance_minutes))) {
                    $status = 'LATE';
                }
            }
            $attendance->fill([
                'check_in' => $currentTime,
                'status' => $status,
                // Freeze the expected minutes AND the shift bounds for the day so a
                // later schedule change never rewrites this day's balance or worked
                // hours — same idea as freezing the status above.
                'expected_minutes' => $todaySchedule?->expectedMinutesFor($now->dayOfWeek),
                'shift_start' => ($todayShift && $todaySchedule->isFixed()) ? $todayShift->start_time : null,
                'shift_end' => ($todayShift && $todaySchedule->isFixed()) ? $todayShift->end_time : null,
                'method' => $method,
            ] + $extra + $device)->save();
            $this->recordMark($request, $employee, $attendance, 'CHECK_IN', $method, $extra['evidence_photo'] ?? null);

            return response()->json([
                'ok' => true,
                'type' => 'CHECK_IN',
                'status' => $status,
                'status_label' => __($status),
                'employee' => $employee->full_name,
                'time' => $now->format('h:i a'),
            ]);
        }

        // ---- Break control (only when enabled and the day is still open) ----
        if ($setting->kiosk_breaks_enabled && is_null($attendance->check_out)) {
            // They are RETURNING from break (break started, not yet returned)
            if ($attendance->break_out && is_null($attendance->break_in)) {
                $breakOut = \Carbon\Carbon::parse($today.' '.$attendance->break_out, company_timezone());
                if ($breakOut->diffInMinutes($now) < 1) {
                    return response()->json(['ok' => false, 'message' => __('You just marked your break. Try again in a moment.')], 422);
                }
                $attendance->update(['break_in' => $currentTime] + $device);
                $this->recordMark($request, $employee, $attendance, 'BREAK_IN', $method, $extra['evidence_photo'] ?? null);

                return response()->json([
                    'ok' => true, 'type' => 'BREAK_IN',
                    'status' => $attendance->status, 'status_label' => __($attendance->status),
                    'employee' => $employee->full_name, 'time' => $now->format('h:i a'),
                ]);
            }

            // No break taken yet: this second mark is either "leave for break" or
            // the final check-out. break_required forces the break; otherwise the
            // kiosk must send the person's choice (action=break|out).
            if (is_null($attendance->break_out)) {
                $action = $request->input('action'); // 'break' | 'out' | null
                if ($setting->break_required || $action === 'break') {
                    $attendance->update(['break_out' => $currentTime] + $device);
                    $this->recordMark($request, $employee, $attendance, 'BREAK_OUT', $method, $extra['evidence_photo'] ?? null);

                    return response()->json([
                        'ok' => true, 'type' => 'BREAK_OUT',
                        'status' => $attendance->status, 'status_label' => __($attendance->status),
                        'employee' => $employee->full_name, 'time' => $now->format('h:i a'),
                    ]);
                }
                if ($action !== 'out') {
                    // Ambiguous — the kiosk should have asked. Safety net.
                    return response()->json(['ok' => false, 'choose' => true,
                        'message' => __('Choose whether you are leaving for a break or checking out.')], 422);
                }
                // action === 'out' falls through to the final check-out below
            }
        }

        if (is_null($attendance->check_out)) {
            $checkIn = \Carbon\Carbon::parse($today.' '.$attendance->check_in, company_timezone());

            // No silent "minimum minutes" ignore: a premature check-out (right after
            // check-in, or before the scheduled end / daily target) asks for an
            // explicit confirmation instead — so a stray re-mark never quietly closes
            // the day, and the person is told it will only count their time so far.
            if ($this->isEarlyCheckout($employee, $now, $attendance) && !$request->boolean('confirm_out')) {
                return response()->json([
                    'ok' => false,
                    'confirm_out' => true,
                    'message' => $this->earlyCheckoutMessage($employee, $now, $checkIn),
                ], 422);
            }

            // Second mark = CHECK-OUT. Leaving early is not annotated here: the
            // person already confirmed it above and the report shows the owed time.
            $attendance->update(['check_out' => $currentTime] + array_filter($extra) + $device);
            $this->recordMark($request, $employee, $attendance, 'CHECK_OUT', $method, $extra['evidence_photo'] ?? null);

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

    // ---------- Guided self-enrollment on the first mark ----------

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

        // Guided self-enrollment on the first mark (no PIN). Two guards:
        //  1. It must be the person validated on the keypad (kiosk_verify_doc), so
        //     the payload can't target a different employee.
        //  2. It only ENROLLS a face that doesn't exist yet — an already-enrolled
        //     face is never overwritten from the kiosk (that needs the admin panel),
        //     so nobody can replace someone else's template.
        abort_unless($request->session()->get('kiosk_verify_doc') === $employee->document_number, 403, __('Enrollment is not authorized for this document.'));
        abort_if($employee->hasFace(), 403, __('This person already has an enrolled face.'));

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
