/* Kiosk camera page: the person was already validated by document on the landing
 * page. Here the camera confirms it is really them (1:1) and, with liveness on,
 * asks for ONE random head gesture (turn left / right / nod) that a printed
 * photo cannot perform and a pre-recorded video cannot anticipate. If they have
 * no enrolled face, they can enroll right here and continue.
 *
 * Fixed rule (no toggle): without a face on camera there is NEVER a mark nor a
 * photo — photographing a wall or a finger is worthless as evidence. */
'use strict';

const MODELS_URL = '/models';
const THRESHOLD = Number(window.KIOSK_THRESHOLD) || 0.5;
const LIVENESS = !!window.KIOSK_LIVENESS;
const VERIFY_WINDOW_MS = (Number(window.KIOSK_VERIFY_SECONDS) || 10) * 1000;
const RESULT_PAUSE_MS = 4000;
const CHALLENGE_MS = 3500;   // time to complete a gesture before a new one is rolled
const YAW_TURN = 1.75;       // yaw ratio beyond which a head turn counts (1 = frontal)
const NOD_DELTA = 0.25;      // relative pitch change that counts as a nod
const DETECTOR = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

const I18N = window.KIOSK_I18N;
const spinner = '<span class="spinner-border spinner-border-sm me-1"></span> ';
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const statusBox = document.getElementById('status');

let cameraOk = false;
let verifying = false;   // guards against double loops (retry spam)
let refs = null;         // this person's enrolled descriptors

// Break control: what the next mark is, and the person's choice when ambiguous.
const NEXT_ACTION = window.KIOSK_NEXT_ACTION || 'CHECK_IN';
const EARLY_EXIT_WARN = !!window.KIOSK_EARLY_EXIT_WARN;
let MARK_ACTION = null;      // 'break' | 'out' once chosen (ambiguous case)
let earlyConfirmed = false;  // the person confirmed an early check-out

// Geolocation: capture the device location (once) to send with the mark.
const GEO_ENABLED = !!window.KIOSK_GEO;
const GEO_REQUIRED = !!window.KIOSK_GEO_REQUIRED; // no location → no mark (camera stays closed)
let geoCoords = null;
function fetchGeo() {
    return new Promise(resolve => {
        if (!navigator.geolocation) { resolve(null); return; }
        navigator.geolocation.getCurrentPosition(
            pos => resolve({ lat: pos.coords.latitude, lng: pos.coords.longitude, accuracy: Math.round(pos.coords.accuracy) }),
            () => resolve(null),
            { enableHighAccuracy: true, timeout: 8000, maximumAge: 60000 },
        );
    });
}
// Single in-flight fetch shared by all callers (page load + the mark), so a
// first-time permission prompt is requested once and everyone awaits the same
// result. A failed attempt (denied/timeout) clears it so the next call retries.
let geoPromise = null;
async function ensureGeo() {
    if (!GEO_ENABLED || geoCoords) return;
    if (!geoPromise) geoPromise = fetchGeo();
    geoCoords = await geoPromise;
    if (!geoCoords) geoPromise = null;
}

let shownType = null, shownHtml = null;
function show(type, html) {
    // Skip identical re-renders: rewriting the same HTML every frame restarts the
    // spinner and makes the text visibly flicker.
    if (type === shownType && html === shownHtml) return;
    shownType = type; shownHtml = html;
    statusBox.className = `alert alert-${type} d-inline-block px-4`;
    statusBox.innerHTML = html;
}

/* Message stabilizer (anti-dizziness). Detection jitters near the size/centre
 * thresholds, so the raw state can flip several times a second. We only switch
 * the DISPLAYED message once a new stage has held steady for a short while — if
 * the state is bouncing, the last calm message simply stays on screen. */
let coachStage = null, coachCand = null, coachCandAt = 0;
const COACH_HOLD_MS = 650;
function resetCoach() { coachStage = null; coachCand = null; coachCandAt = 0; }
function coach(stage, type, html) {
    if (stage === coachStage) { coachCand = null; return; }
    if (coachStage === null) { coachStage = stage; show(type, html); return; } // first one instantly
    const now = Date.now();
    if (stage !== coachCand) { coachCand = stage; coachCandAt = now; return; }
    if (now - coachCandAt >= COACH_HOLD_MS) { coachStage = stage; coachCand = null; show(type, html); }
}
function clearOverlay() { overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height); }
function drawBox(box, color) {
    const ctx = overlay.getContext('2d');
    ctx.strokeStyle = color; ctx.lineWidth = 4;
    ctx.strokeRect(box.x, box.y, box.width, box.height);
}

/* ---------- face-placement guide (RENIEC-style) ----------
 * The circular camera frame itself is the guide: its border reacts to what the
 * camera detects (blue searching, amber adjusting, green ready). The person
 * fills the circle by coming closer, the way phone Face-ID / bank kiosks work. */
const videoFrame = document.querySelector('.video-frame');
let lastOvalGreenAt = 0;
/* Single guide: the circular FRAME border reacts to what the camera detects —
 * blue while searching/positioning, amber while adjusting, green when ready.
 * (No second dashed oval drawn on the canvas: one clean indicator, not two.)
 *   state: 'idle' | 'adjust' | 'ok'  */
function setRing(state) {
    if (!videoFrame) return;
    // Hold the green briefly so the border does not blink at the threshold.
    if (state === 'ok') lastOvalGreenAt = Date.now();
    const green = state === 'ok' || (Date.now() - lastOvalGreenAt < 400);
    videoFrame.classList.toggle('face-ok', green);
    videoFrame.classList.toggle('face-adjust', !green && state === 'adjust');
}

/* Placement relative to the visible circle → drives the come-closer / center /
 * hold-still coaching. The oval is a TARGET TO FILL: the person must be close
 * enough that the face fills a good part of the circle, and centred. This is
 * what makes the circle meaningful (and sets a consistent capture distance —
 * ~50 cm on a tablet) instead of accepting a tiny far-away face. Returns:
 * 'none' | 'far' | 'close' | 'offcenter' | 'ok'. */
let lastPlace = 'none';
function facePlacement(box) {
    if (!box) { lastPlace = 'none'; return 'none'; }
    const W = overlay.width, H = overlay.height;
    const cx = W / 2, cy = H / 2, R = Math.min(W, H) / 2;
    const bcx = box.x + box.width / 2, bcy = box.y + box.height / 2;
    const off = Math.hypot(bcx - cx, bcy - cy);
    const h = box.height;
    // Hysteresis: once OK it takes a bit more drift to fall out of OK, so a face
    // hovering exactly on a threshold does not bounce in and out.
    const ok = lastPlace === 'ok';
    const nearFar = ok ? 0.80 : 0.85, tooClose = ok ? 1.85 : 1.75, offMax = ok ? 0.52 : 0.45;
    let place;
    if (h < R * nearFar) place = 'far';
    else if (h > R * tooClose) place = 'close';
    else if (off > R * offMax) place = 'offcenter';
    else place = 'ok';
    lastPlace = place;
    return place;
}
function wait(ms) { return new Promise(r => setTimeout(r, ms)); }
function setProgress(pct) { document.getElementById('verifyProgress').style.width = pct + '%'; }
function setCountdown(seconds) {
    const el = document.getElementById('countdown');
    if (seconds === null) { el.style.display = 'none'; return; }
    el.style.display = '';
    el.textContent = seconds;
}
function setFaceChip(present) {
    const wrap = document.getElementById('faceChip');
    const badge = document.getElementById('faceChipBadge');
    if (present === null) { wrap.style.display = 'none'; return; }
    wrap.style.display = '';
    badge.style.background = present ? '#28a745' : '#6c757d';
    badge.style.color = '#fff';
    badge.innerHTML = present
        ? '<i class="fas fa-check-circle"></i> ' + I18N.faceDetected
        : '<i class="fas fa-search"></i> ' + I18N.noFaceYet;
}
function showActions(withMarkDoc, withRetry = true) {
    document.getElementById('actionRow').style.display = '';
    // The document button only exists in the DOM for people with an enrolled face
    const markDocBtn = document.getElementById('markDocBtn');
    if (markDocBtn) markDocBtn.style.display = withMarkDoc ? '' : 'none';
    document.getElementById('retryBtn').style.display = withRetry ? '' : 'none';
}
function hideActions() { document.getElementById('actionRow').style.display = 'none'; }

async function postJson(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
        body: JSON.stringify(payload),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
}

/** Small JPEG of the current frame (evidence for document marking) */
function captureSnapshot() {
    if (!video.videoWidth) return null;
    const canvas = document.createElement('canvas');
    const width = 480;
    canvas.width = width;
    canvas.height = Math.round(video.videoHeight * (width / video.videoWidth));
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.8);
}

/* Liveness via random head gestures (replaces the old blink detector, which
 * struggled with glasses and needed the person right up against the tablet).
 *
 * Head pose comes from the 68 landmarks already loaded — no extra models:
 *  - yaw: nose tip position between the jaw extremes. A REAL head turning
 *    shows asymmetric perspective (near cheek widens, nose shifts sideways);
 *    a flat photo rotated on its axis just compresses uniformly and stays
 *    symmetric, so it never crosses the turn threshold.
 *  - pitch proxy: eyes→nose vs nose→chin vertical proportion. A real nod
 *    changes them unevenly; tilting or moving a photo scales both equally.
 *
 * The gesture is picked at random AFTER the identity is confirmed, and must be
 * performed within a short window — a pre-recorded video cannot know which
 * gesture will be asked nor when. */
function headPose(landmarks) {
    const jaw = landmarks.getJawOutline();
    const noseTip = landmarks.getNose()[3]; // point 30
    const xs = jaw.map(p => p.x);
    const jawLeftX = Math.min(...xs), jawRightX = Math.max(...xs);
    const chinY = Math.max(...jaw.map(p => p.y));
    const eyeMidY = [...landmarks.getLeftEye(), ...landmarks.getRightEye()]
        .reduce((sum, p) => sum + p.y, 0) / 12;
    return {
        yaw: Math.max(1, noseTip.x - jawLeftX) / Math.max(1, jawRightX - noseTip.x),
        pitch: Math.max(1, noseTip.y - eyeMidY) / Math.max(1, chinY - noseTip.y),
    };
}
function isFrontal(pose) { return pose.yaw > 0.72 && pose.yaw < 1.4; }

// Direction-agnostic on purpose: 'turn' accepts a head turn to EITHER side. Some
// tablets mirror the camera feed and others do not, which would invert a fixed
// left/right instruction and confuse people; "turn your head to a side" works the
// same no matter how the device mirrors, and is easier to follow under pressure.
function pickChallenge(except = null) {
    const options = ['turn', 'nod'].filter(c => c !== except);
    return options[Math.floor(Math.random() * options.length)];
}
function challengeMet(challenge, pose, pitchBaseline) {
    if (challenge === 'turn') return pose.yaw >= YAW_TURN || pose.yaw <= 1 / YAW_TURN;
    return pitchBaseline > 0 && Math.abs(pose.pitch - pitchBaseline) / pitchBaseline >= NOD_DELTA;
}
function challengeLabel(challenge) {
    if (challenge === 'turn') return '<i class="fas fa-arrows-left-right"></i> ' + I18N.challengeTurn;
    return '<i class="fas fa-arrows-up-down"></i> ' + I18N.challengeNod;
}
// The gesture instruction is shown in the status box below the circle (readable on
// the white background); there is no longer any text overlaid on the video.
function setChallenge(challenge) {
    const el = document.getElementById('challenge');
    if (!el) return;
    if (!challenge) { el.style.display = 'none'; return; }
    el.style.display = '';
    el.innerHTML = challengeLabel(challenge);
}

/* ---------- live diagnosis (open /kiosk/verify?debug=1) ---------- */
const DEBUG = new URLSearchParams(location.search).has('debug');
let debugBox = null, debugSamples = 0, debugWindowStart = 0, debugHz = 0;
function debugUpdate(pose, distance, identityOk, challenge, pitchBaseline) {
    if (!DEBUG) return;
    if (!debugBox) {
        debugBox = document.createElement('div');
        debugBox.style.cssText = 'position:fixed;left:8px;bottom:8px;z-index:99;background:rgba(0,0,0,.85);color:#7CFC00;font:12px/1.5 monospace;padding:8px 10px;border-radius:8px;text-align:left';
        document.body.appendChild(debugBox);
    }
    const now = Date.now();
    debugSamples++;
    if (now - debugWindowStart > 1000) { debugHz = debugSamples; debugSamples = 0; debugWindowStart = now; }
    debugBox.innerHTML =
        `yaw: <b>${pose ? pose.yaw.toFixed(2) : '—'}</b> (gira: ≥ ${YAW_TURN} o ≤ ${(1 / YAW_TURN).toFixed(2)})<br>` +
        `pitch: ${pose ? pose.pitch.toFixed(2) : '—'} | base: ${pitchBaseline ? pitchBaseline.toFixed(2) : '—'} (±${NOD_DELTA * 100}%)<br>` +
        `reto: ${challenge || '—'} | muestras/s: ${debugHz}<br>` +
        `identidad: ${identityOk ? 'OK' : 'buscando'}${distance !== null ? ' (dist ' + distance.toFixed(3) + ' / max ' + THRESHOLD + ')' : ''}`;
}

/* ---------- startup ---------- */
let modelsLoaded = false;

/** Load the recognition models once (needed for both verify and enroll). */
async function loadModels() {
    if (modelsLoaded) return;
    show('secondary', spinner + I18N.loadingModels);
    await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
    await faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL);
    await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL);
    modelsLoaded = true;
}

/**
 * Forced-geolocation gate. Returns true when we may proceed. When location is
 * required and missing, it shows a retry and returns false (camera stays off).
 * When not required, it just kicks off a background location fetch.
 */
async function geoGate() {
    if (!GEO_REQUIRED) { ensureGeo(); return true; }
    show('secondary', spinner + I18N.requestingLocation);
    await ensureGeo();
    if (!geoCoords) {
        show('warning', '<i class="fas fa-map-marker-alt"></i> ' + I18N.locationRequired);
        showActions(false, true); // "Try again" re-requests the location
        return false;
    }
    return true;
}

/** Turn the camera on and resolve once it is actually playing. */
function openCamera() {
    return new Promise(async (resolve, reject) => {
        try {
            // Reveal the circle now (it stays hidden until the camera actually opens)
            const area = document.getElementById('cameraArea');
            if (area) area.style.display = '';
            show('secondary', spinner + I18N.startingCamera);
            const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
            video.srcObject = stream;
            video.addEventListener('playing', () => {
                overlay.width = video.videoWidth;
                overlay.height = video.videoHeight;
                cameraOk = true;
                resolve();
            }, { once: true });
        } catch (e) { reject(e); }
    });
}

async function start() {
    try {
        // Ask for the location as EARLY as possible (page load), so a first-time
        // permission prompt has the whole flow to resolve before the mark is sent.
        // This does not touch the camera. Forced mode still gates below/at enroll.
        ensureGeo();

        await loadModels();

        if (window.HAS_FACE) {
            // Enrolled: verify identity. The camera opens straight away (gated by
            // forced location, if enabled).
            if (!await geoGate()) return;
            await openCamera();
            begin();
            return;
        }

        // NOT enrolled: show ONLY the consent card — the camera stays OFF. It only
        // turns on once the person accepts the consent (see startEnroll()).
        const card = document.getElementById('enrollCard');
        if (card) card.style.display = '';
        show('info', '<i class="fas fa-user-plus"></i> ' + I18N.enrollFirst);
        showActions(false, false); // just Cancel
    } catch (e) {
        // Camera/models unavailable. Enrolled people may still mark by document so
        // attendance is not blocked; non-enrolled people must enroll first (and
        // enrolling needs the camera), so they can only cancel.
        show('warning', '<i class="fas fa-video-slash"></i> ' + (window.HAS_FACE ? I18N.cameraFallback : I18N.cameraNeededToEnroll));
        showActions(window.HAS_FACE, false);
    }
}

function begin() {
    if (window.HAS_FACE) {
        // Break control: if this second mark could be a break OR a check-out, ask
        // the person first. And warn before a clearly-early check-out so a stray
        // mark does not hurt them. Only then run the camera verification.
        if (NEXT_ACTION === 'AMBIGUOUS' && !MARK_ACTION) { promptBreakOrOut(); return; }
        // Confirm before a premature check-out (fixed: before end time; flexible:
        // target not met). Covers both the plain CHECK_OUT and the "chose out" path.
        if (EARLY_EXIT_WARN && !earlyConfirmed && (NEXT_ACTION === 'CHECK_OUT' || MARK_ACTION === 'out')) { promptEarlyExit(); return; }
        hideActionChoice();
        runVerify();
        return;
    }
    // No enrolled face → offer the guided self-enrollment card (accept consent →
    // the camera guides the capture). No PIN; the keypad already validated who it is.
    const card = document.getElementById('enrollCard');
    if (card) card.style.display = '';
    show('info', '<i class="fas fa-user-plus"></i> ' + I18N.enrollFirst);
    showActions(false, false); // just Cancel
}

/* ---------- break / early-exit choice panel (shown before the camera) ---------- */
// A choice left untouched must not block the kiosk for the next person: after this
// idle it auto-returns to the home screen (as if they cancelled).
const CHOICE_IDLE_MS = 30000;
let choiceTimer = null;
function hideActionChoice() {
    clearTimeout(choiceTimer);
    document.getElementById('actionChoice').style.display = 'none';
}
function showChoice(title, body, buttons) {
    document.getElementById('actionChoiceTitle').innerHTML = title;
    const b = document.getElementById('actionChoiceBody');
    if (body) { b.textContent = body; b.style.display = ''; } else { b.style.display = 'none'; }
    const wrap = document.getElementById('actionChoiceButtons');
    wrap.innerHTML = '';
    buttons.forEach(btn => {
        const el = document.createElement('button');
        el.className = 'btn ' + btn.cls + ' px-4 m-1';
        el.innerHTML = btn.label;
        el.onclick = btn.onClick;
        wrap.appendChild(el);
    });
    clearTimeout(choiceTimer);
    choiceTimer = setTimeout(() => { window.location.href = window.HOME_URL; }, CHOICE_IDLE_MS);
    document.getElementById('actionChoice').style.display = '';
    show('secondary', I18N.chooseTitle);
}
function promptBreakOrOut() {
    showChoice(I18N.chooseTitle, null, [
        { label: '<i class="fas fa-mug-hot"></i> ' + I18N.chooseBreak, cls: 'btn-warning',
          onClick: () => { MARK_ACTION = 'break'; hideActionChoice(); runVerify(); } },
        { label: '<i class="fas fa-right-from-bracket"></i> ' + I18N.chooseOut, cls: 'btn-primary',
          onClick: () => { MARK_ACTION = 'out'; hideActionChoice(); begin(); } },
    ]);
}
function promptEarlyExit() {
    // Pre-camera confirmation (the person hasn't been recognized yet)
    showChoice('<i class="fas fa-triangle-exclamation text-warning"></i> ' + I18N.earlyExitTitle, I18N.earlyExitBody, [
        { label: '<i class="fas fa-check"></i> ' + I18N.earlyExitYes, cls: 'btn-danger',
          onClick: () => { earlyConfirmed = true; hideActionChoice(); runVerify(); } },
        { label: I18N.cancel, cls: 'btn-outline-light',
          onClick: () => { window.location.href = window.HOME_URL; } },
    ]);
}
/* Backend safety net: the mark POST said this check-out is premature. Confirm with
   the server's own message, then retry the same POST carrying confirm_out. */
function confirmEarly(message, retry) {
    show('secondary', I18N.chooseTitle);
    showChoice('<i class="fas fa-triangle-exclamation text-warning"></i> ' + I18N.earlyExitTitle, message || I18N.earlyExitBody, [
        { label: '<i class="fas fa-check"></i> ' + I18N.earlyExitYes, cls: 'btn-danger',
          onClick: () => { earlyConfirmed = true; hideActionChoice(); retry(); } },
        { label: I18N.cancel, cls: 'btn-outline-light',
          onClick: () => { window.location.href = window.HOME_URL; } },
    ]);
}

/* ---------- 1:1 verification with a real, configurable window ---------- */
async function runVerify() {
    if (verifying) return;
    verifying = true;
    hideActions();
    clearOverlay();
    resetCoach();

    try {
        if (!refs) {
            const res = await fetch(window.FACE_URL, { headers: { Accept: 'application/json' } });
            const person = await res.json().catch(() => ({}));
            if (!res.ok || !person.ok) throw new Error('no-face-data');
            refs = person.descriptors.map(d => new Float32Array(d));
        }

        // Identity is confirmed ONCE and stays confirmed (sticky): the full
        // face-descriptor pass is slow (300-500ms on modest hardware), so after
        // the match the loop switches to the cheap landmarks-only pass and
        // tracks the gesture at the camera's real frame rate.
        let challengeDone = !LIVENESS, sawFace = false;
        let identityOk = false, matchedDistance = null;
        let lastDistance = null;
        // Liveness challenge state: issued only AFTER identity locks and the
        // face has been frontal for a few samples (so the pitch baseline is
        // clean and the gesture is a response to the prompt, not residue).
        let challenge = null, challengeAt = 0, frontalStreak = 0, pitchBaseline = null;
        const pitchSamples = [];
        const startAt = Date.now();
        const deadline = startAt + VERIFY_WINDOW_MS;

        while (Date.now() < deadline) {
            setProgress(Math.min(100, Math.round((Date.now() - startAt) * 100 / VERIFY_WINDOW_MS)));
            setCountdown(Math.max(0, Math.ceil((deadline - Date.now()) / 1000)));

            let det;
            try {
                det = identityOk
                    ? await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks()
                    : await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks().withFaceDescriptor();
            } catch (e) { det = null; }

            clearOverlay();
            setFaceChip(!!det);
            if (!det) {
                // No face and "too far" share ONE message/stage, so a face blinking
                // in and out of detection does not swap the text back and forth.
                setRing('idle');
                coach('far', 'warning', '<i class="fas fa-magnifying-glass-plus"></i> ' + I18N.comeCloser2);
                debugUpdate(null, lastDistance, identityOk, challenge, pitchBaseline);
                await wait(200);
                continue;
            }
            sawFace = true;

            // The circle must actually matter: until the face FILLS it and is
            // centred, we neither confirm identity nor run the gesture. This sets a
            // consistent capture distance and stops a mark from succeeding with a
            // tiny far-away or half-out face (recognition reads the whole frame).
            const place = facePlacement(det.detection.box);
            setRing(place === 'ok' ? 'ok' : 'adjust');
            if (DEBUG) drawBox(det.detection.box, identityOk ? '#28a745' : '#ffc107');
            if (place !== 'ok') {
                const c = place === 'close'
                    ? ['close', 'fa-magnifying-glass-minus', I18N.moveBack]
                    : place === 'offcenter'
                        ? ['offcenter', 'fa-crosshairs', I18N.centerFace]
                        : ['far', 'fa-magnifying-glass-plus', I18N.comeCloser2];
                coach(c[0], 'warning', `<i class="fas ${c[1]}"></i> ` + c[2]);
                debugUpdate(headPose(det.landmarks), lastDistance, identityOk, challenge, pitchBaseline);
                await wait(120);
                continue;
            }

            if (!identityOk) {
                const d = Math.min(...refs.map(r => faceapi.euclideanDistance(r, det.descriptor)));
                lastDistance = d;
                if (d <= THRESHOLD) { identityOk = true; matchedDistance = d; }
            }

            const pose = headPose(det.landmarks);
            if (LIVENESS && !challengeDone && identityOk) {
                if (!challenge) {
                    // Frontal warm-up: collect the person's own pitch baseline
                    if (isFrontal(pose)) {
                        frontalStreak++;
                        pitchSamples.push(pose.pitch);
                        if (pitchSamples.length > 10) pitchSamples.shift();
                        if (frontalStreak >= 3) {
                            pitchBaseline = [...pitchSamples].sort((a, b) => a - b)[Math.floor(pitchSamples.length / 2)];
                            challenge = pickChallenge();
                            challengeAt = Date.now();
                            setChallenge(challenge);
                        }
                    } else {
                        frontalStreak = 0;
                    }
                } else if (challengeMet(challenge, pose, pitchBaseline)) {
                    challengeDone = true;
                    setChallenge(null);
                } else if (Date.now() - challengeAt > CHALLENGE_MS) {
                    // Not performed in time: roll a DIFFERENT random gesture. A
                    // fresh chance for a real person, extra noise for a looped video.
                    challenge = pickChallenge(challenge);
                    challengeAt = Date.now();
                    setChallenge(challenge);
                }
            }
            debugUpdate(pose, lastDistance, identityOk, challenge, pitchBaseline);

            // Honest per-stage message (stabilized): never ask for the gesture
            // while the real blocker is still the identity match.
            if (challenge && !challengeDone) {
                coach('gesture-' + challenge, 'info', spinner + challengeLabel(challenge));
            } else {
                coach('confirm', 'info', spinner + I18N.lookAtCamera.replace(':name', window.EMPLOYEE.name));
            }

            if (identityOk && challengeDone) {
                setProgress(100);
                setCountdown(null);
                setFaceChip(null);
                setChallenge(null);
                verifying = false;
                return commitFacial(matchedDistance);
            }
            // Gesture tracking runs flat out (no artificial pause once identity locked)
            await wait(identityOk ? 15 : 150);
        }
        setChallenge(null);

        // Window over: automatic evidence phase — no buttons, no decisions. The
        // ONLY condition to record by document is that a face shows up on camera
        // (even a cheater's photo: the evidence snapshot exposes them). No face at
        // all (finger on the lens, walked away) -> nothing recorded, back home.
        setProgress(0);
        clearOverlay();
        verifying = false;
        return evidencePhase();
    } catch (e) {
        verifying = false;
        setCountdown(null);
        setChallenge(null);
        show('danger', I18N.connectionError);
        showActions(true);
    }
}

/** Second phase after a failed verification: hunt for ANY face for a few seconds
 *  and auto-mark by document + evidence photo the moment one appears. The
 *  on-screen message is deliberately NEUTRAL ("retrying detection"): announcing
 *  that an evidence photo is coming would tell a cheater exactly when to hide. */
async function evidencePhase() {
    const EVIDENCE_MS = 8000;
    hideActions();
    show('info', spinner + I18N.evidenceIntro);

    const deadline = Date.now() + EVIDENCE_MS;
    while (Date.now() < deadline) {
        setCountdown(Math.max(0, Math.ceil((deadline - Date.now()) / 1000)));

        let det = null;
        try { det = await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks(); } catch (e) { /* keep looking */ }
        clearOverlay();
        setFaceChip(!!det);

        if (det) {
            setRing('ok');
            setCountdown(null);
            return autoMarkByDocument(); // face on camera -> snapshot is meaningful evidence
        }
        setRing('idle');
        await wait(200);
    }

    setCountdown(null);
    setFaceChip(null);

    // No face ever showed up (finger on the lens, walked away): nothing is
    // recorded and the kiosk closes. This rule has no off-switch — a "mark"
    // whose evidence is a photo of the ceiling is worse than no mark.
    show('warning', '<i class="fas fa-exclamation-triangle"></i> ' + I18N.evidenceClosing);
    setTimeout(() => { window.location.href = window.HOME_URL; }, 2500);
}

async function autoMarkByDocument() {
    show('info', spinner + I18N.savingSlow);
    try {
        await ensureGeo(); // wait for a still-pending location so the mark carries it
        const { data } = await postJson(window.MARK_DNI_URL, {
            document_number: window.EMPLOYEE.document,
            photo: captureSnapshot(),
            action: MARK_ACTION,
            confirm_out: earlyConfirmed ? 1 : 0,
            ...(geoCoords || {}),
        });
        if (data.confirm_out) {
            confirmEarly(data.message, () => autoMarkByDocument());
        } else if (data.ok) {
            finishWithResult(data, I18N.verifyFailedPhoto);
        } else {
            show('warning', (data.message || I18N.couldNotRecord) + `<br><small class="text-muted">${I18N.backSoon}</small>`);
            setTimeout(() => { window.location.href = window.HOME_URL; }, 3500);
        }
    } catch (e) {
        show('danger', I18N.connectionError);
        showActions(true);
    }
}

function retryVerify() {
    hideActions();
    // Camera not running yet. If the person already accepted consent (and the
    // camera/location then failed), retry the enroll — which re-opens the camera.
    // Otherwise restart from scratch (re-requests location / camera / consent).
    if (!cameraOk) {
        const consent = document.getElementById('enrollConsent');
        if (!window.HAS_FACE && consent && consent.checked) { startEnroll(); }
        else { start(); }
        return;
    }
    if (window.HAS_FACE) { runVerify(); return; }
    // No face, camera already on: resume the guided capture.
    enrollGuided();
}

/* ---------- marking ---------- */
async function commitFacial(distance) {
    // A FACIAL mark saves NO photo: the match distance + completed liveness
    // gesture are proof enough, and a snapshot per successful mark would just
    // pile up bytes on disk. Photos are kept only for the DNI fallback below.
    show('info', spinner + I18N.savingSlow);
    try {
        await ensureGeo(); // wait for a still-pending location so the mark carries it
        const { data } = await postJson(window.MARK_URL, {
            employee_id: Number(window.EMPLOYEE.id),
            distance: distance.toFixed(4),
            action: MARK_ACTION,
            confirm_out: earlyConfirmed ? 1 : 0,
            ...(geoCoords || {}),
        });
        if (data.confirm_out) { confirmEarly(data.message, () => commitFacial(distance)); return; }
        finishWithResult(data);
    } catch (e) {
        show('danger', I18N.connectionError);
        showActions(true);
    }
}

async function markByDocument() {
    hideActions();

    // The fixed no-face-no-mark rule also applies to the manual button: a face
    // must be on camera at the moment of the snapshot (5s grace), or nothing is
    // saved. The message stays neutral on purpose (see evidencePhase).
    if (cameraOk) {
        show('info', spinner + I18N.showYourFace);
        const seen = await waitForAnyFace(5000);
        if (!seen) {
            show('warning', '<i class="fas fa-exclamation-triangle"></i> ' + I18N.noFaceSeen);
            showActions(false);
            return;
        }
    }

    show('info', spinner + I18N.savingSlow);
    try {
        await ensureGeo(); // wait for a still-pending location so the mark carries it
        const { data } = await postJson(window.MARK_DNI_URL, {
            document_number: window.EMPLOYEE.document,
            photo: captureSnapshot(),
            action: MARK_ACTION,
            confirm_out: earlyConfirmed ? 1 : 0,
            ...(geoCoords || {}),
        });
        if (data.confirm_out) {
            confirmEarly(data.message, () => markByDocument());
        } else if (data.ok) {
            finishWithResult(data, I18N.verifyFailedPhoto);
        } else {
            show('warning', data.message || I18N.couldNotRecord);
            showActions(true);
        }
    } catch (e) {
        show('danger', I18N.connectionError);
        showActions(true);
    }
}

async function waitForAnyFace(ms) {
    const deadline = Date.now() + ms;
    while (Date.now() < deadline) {
        let det;
        try { det = await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks(); } catch (e) { det = null; }
        clearOverlay();
        if (det) { setRing('ok'); return true; }
        setRing('idle');
        await wait(250);
    }
    return false;
}

function finishWithResult(data, note) {
    clearOverlay();
    if (data.ok) {
        const isBreak = data.type === 'BREAK_OUT' || data.type === 'BREAK_IN';
        const color = isBreak ? 'info' : (data.status === 'LATE' ? 'warning' : 'success');
        const typeLabel = data.type === 'CHECK_IN' ? I18N.checkIn
            : data.type === 'CHECK_OUT' ? I18N.checkOut
            : data.type === 'BREAK_OUT' ? I18N.breakOut
            : data.type === 'BREAK_IN' ? I18N.breakIn : data.type;
        // Break marks have no punctuality status to show
        const tail = isBreak ? '' : ` — ${data.status_label}`;
        show(color, `<i class="fas fa-check-circle"></i> <strong>${typeLabel}</strong> ${I18N.recorded}: ${data.employee}<br>${data.time}${tail}` + (note ? `<br><small>${note}</small>` : ''));
    } else {
        show('warning', '<i class="fas fa-info-circle"></i> ' + (data.message || I18N.couldNotRecord));
    }
    statusBox.innerHTML += `<br><small class="text-muted">${I18N.backSoon}</small>`;
    setTimeout(() => { window.location.href = window.HOME_URL; }, RESULT_PAUSE_MS);
}

/* ---------- self-enrollment on first mark ---------- */
function setEnrollMessage(id, type, html) {
    document.getElementById(id).innerHTML = html ? `<div class="alert alert-${type} py-2 small mb-2">${html}</div>` : '';
}

// Consent gate → ONLY THEN the camera turns on and guides the enrollment. Until
// the person accepts the biometric consent, the camera never activates.
async function startEnroll() {
    if (!document.getElementById('enrollConsent').checked) {
        setEnrollMessage('enrollMessage', 'warning', I18N.consentRequired);
        return;
    }
    const card = document.getElementById('enrollCard');
    if (card) card.style.display = 'none';
    try {
        if (!await geoGate()) return;      // forced location, if enabled
        if (!cameraOk) await openCamera(); // turn the camera on now
        enrollGuided();
    } catch (e) {
        show('warning', '<i class="fas fa-video-slash"></i> ' + I18N.cameraNeededToEnroll);
        showActions(false, true); // "Try again" re-opens the camera
    }
}

const ENROLL_SAMPLES = 3;
const ENROLL_HOLD_SECONDS = 4;   // green "hold still" time that actually captures
const ENROLL_TICK_MS = 160;      // one placement check per tick
const ENROLL_GOOD_TICKS = Math.round(ENROLL_HOLD_SECONDS * 1000 / ENROLL_TICK_MS);
const ENROLL_LOST_TICKS = 20;    // ~3s fully off frame → restart the guidance

/** One detection with the full descriptor (null on failure) */
async function enrollDetect() {
    try { return await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks().withFaceDescriptor(); }
    catch (e) { return null; }
}

/**
 * Guided enrollment: SAME reactive ring + coaching as marking (come closer /
 * center). When the face fills the circle and is centred (green), it holds a
 * "don't move" countdown of a few seconds and AUTO-captures the samples across
 * it — no button. If the person drifts out of the circle mid-countdown, it
 * restarts the guidance so the captured template is always well-framed.
 */
async function enrollGuided() {
    while (true) {
        await enrollWaitForGreen();
        const descriptors = await enrollHoldAndCapture();
        setCountdown(null);
        if (descriptors && descriptors.length) { await enrollSave(descriptors); return; }
        // Drifted out mid-capture → coach and try again
        setRing('idle');
        coach('moved', 'warning', '<i class="fas fa-triangle-exclamation"></i> ' + I18N.enrollMoved);
        await wait(900);
    }
}

/** Guide with the ring until the face fills the circle and is centred (green). */
async function enrollWaitForGreen() {
    while (true) {
        const det = await enrollDetect();
        clearOverlay();
        setFaceChip(!!det);
        if (!det) {
            setRing('idle');
            coach('far', 'info', '<i class="fas fa-magnifying-glass-plus"></i> ' + I18N.comeCloser2);
            await wait(180);
            continue;
        }
        const place = facePlacement(det.detection.box);
        setRing(place === 'ok' ? 'ok' : 'adjust');
        if (place === 'ok') return;
        const c = place === 'close'
            ? ['close', 'fa-magnifying-glass-minus', I18N.moveBack]
            : place === 'offcenter'
                ? ['offcenter', 'fa-crosshairs', I18N.centerFace]
                : ['far', 'fa-magnifying-glass-plus', I18N.comeCloser2];
        coach(c[0], 'warning', `<i class="fas ${c[1]}"></i> ` + c[2]);
        await wait(120);
    }
}

/** Placement hint shown while the ring is amber during capture. */
function enrollAdjustMessage(place) {
    const c = place === 'close' ? ['fa-magnifying-glass-minus', I18N.moveBack]
        : place === 'offcenter' ? ['fa-crosshairs', I18N.centerFace]
            : ['fa-magnifying-glass-plus', I18N.comeCloser2];
    return `<i class="fas ${c[0]}"></i> ` + c[1];
}

/**
 * Capture the samples while the face stays green. The message is driven by the
 * SAME green signal as the ring border, so they can never disagree:
 *  - Ring green → "registering, don't move" + a countdown that advances.
 *  - Ring amber → a placement hint (come closer / center / move back); countdown
 *    pauses. Both flip together (with the ring's short green-hold), so you never
 *    see "registering" over an amber ring.
 *  - Fully off frame for ~3s → give up and re-run the guidance from the top.
 * Only genuinely well-placed frames advance the countdown and are sampled.
 */
async function enrollHoldAndCapture() {
    const descriptors = [];
    let goodTicks = 0, lostTicks = 0, lastSampleTick = -99, lastPlace = 'far';
    const sampleGap = Math.max(1, Math.floor(ENROLL_GOOD_TICKS / (ENROLL_SAMPLES + 1)));

    while (goodTicks < ENROLL_GOOD_TICKS) {
        const det = await enrollDetect();
        clearOverlay();
        const place = det ? facePlacement(det.detection.box) : 'none';
        const isOk = place === 'ok';

        if (isOk) {
            lostTicks = 0;
            goodTicks++;
            setRing('ok');
            if (descriptors.length < ENROLL_SAMPLES && goodTicks - lastSampleTick >= sampleGap) {
                descriptors.push(Array.from(det.descriptor));
                lastSampleTick = goodTicks;
            }
        } else {
            if (++lostTicks >= ENROLL_LOST_TICKS) { setRing('idle'); return null; }
            if (place !== 'none') lastPlace = place;
            setRing('adjust');
        }

        // Message follows the EXACT ring color (same 400 ms green-hold as setRing):
        // green ⇒ "registering", amber ⇒ placement hint. They flip together.
        const ringGreen = isOk || (Date.now() - lastOvalGreenAt < 400);
        if (ringGreen) {
            setCountdown(Math.max(1, Math.ceil((ENROLL_GOOD_TICKS - goodTicks) * ENROLL_TICK_MS / 1000)));
            show('success', '<i class="fas fa-camera"></i> ' + I18N.enrollHold);
        } else {
            setCountdown(null);
            show('warning', enrollAdjustMessage(lastPlace));
        }
        await wait(ENROLL_TICK_MS);
    }
    return descriptors.length ? descriptors : null;
}

async function enrollSave(descriptors) {
    show('info', spinner + I18N.saving);
    try {
        const { data } = await postJson(window.ENROLL_DESCRIPTOR_URL, {
            employee_id: Number(window.EMPLOYEE.id),
            consent: true,
            descriptors,
        });
        if (data.ok) {
            window.HAS_FACE = true;
            refs = descriptors.map(d => new Float32Array(d));
            show('success', '<i class="fas fa-check-circle"></i> ' + I18N.enrolled);
            setTimeout(() => { runVerify(); }, 1300); // straight into confirmation → mark
        } else {
            show('danger', data.message || I18N.couldNotRecord);
            showActions(false, true);
        }
    } catch (e) {
        show('danger', I18N.connectionError);
        showActions(false, true);
    }
}

document.addEventListener('DOMContentLoaded', start);
