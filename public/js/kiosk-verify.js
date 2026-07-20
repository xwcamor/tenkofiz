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

function show(type, html) {
    statusBox.className = `alert alert-${type} d-inline-block px-4`;
    statusBox.innerHTML = html;
}
function clearOverlay() { overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height); }
function drawBox(box, color) {
    const ctx = overlay.getContext('2d');
    ctx.strokeStyle = color; ctx.lineWidth = 4;
    ctx.strokeRect(box.x, box.y, box.width, box.height);
}

/* ---------- face-placement guide (oval, RENIEC-style) ----------
 * A dashed vertical oval over the video: white while the face is missing or
 * poorly framed, green when it is centered and at a good size. It standardizes
 * position and distance so recognition and the liveness gesture read clean
 * landmarks — fewer failed verifications and fewer drops to the evidence phase.
 * It is a GUIDE only: it never blocks marking, it just helps the person frame. */
function ovalGeom() {
    const w = overlay.width, h = overlay.height;
    return { cx: w / 2, cy: h * 0.47, rx: w * 0.30, ry: h * 0.40 };
}
const videoFrame = document.querySelector('.video-frame');
function drawGuideOval(ok) {
    const ctx = overlay.getContext('2d');
    const { cx, cy, rx, ry } = ovalGeom();
    ctx.save();
    ctx.setLineDash([14, 11]);
    ctx.lineWidth = 5;
    ctx.strokeStyle = ok ? '#28a745' : 'rgba(255,255,255,.8)';
    ctx.beginPath();
    ctx.ellipse(cx, cy, rx, ry, 0, 0, Math.PI * 2);
    ctx.stroke();
    ctx.restore();
    // On the white camera page the circular frame border echoes the state
    if (videoFrame) videoFrame.classList.toggle('face-ok', !!ok);
}
function faceWellPlaced(box) {
    const { cx, cy, rx, ry } = ovalGeom();
    const bcx = box.x + box.width / 2, bcy = box.y + box.height / 2;
    const centered = Math.abs(bcx - cx) < rx * 0.55 && Math.abs(bcy - cy) < ry * 0.5;
    const sized = box.height > ry * 0.9 && box.height < ry * 1.95; // not too far, not too close
    return centered && sized;
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
    if (challenge === 'turn') return '<i class="fas fa-arrows-alt-h"></i> ' + I18N.challengeTurn;
    return '<i class="fas fa-arrows-alt-v"></i> ' + I18N.challengeNod;
}
function setChallenge(challenge) {
    const el = document.getElementById('challenge');
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
async function start() {
    try {
        show('secondary', spinner + I18N.loadingModels);
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL);

        show('secondary', spinner + I18N.startingCamera);
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            cameraOk = true;
            begin();
        }, { once: true });
    } catch (e) {
        // Camera unavailable. Enrolled people may still mark by document so
        // attendance is not blocked; non-enrolled people must enroll first (and
        // enrolling needs the camera), so they can only cancel.
        show('warning', '<i class="fas fa-video-slash"></i> ' + (window.HAS_FACE ? I18N.cameraFallback : I18N.cameraNeededToEnroll));
        showActions(window.HAS_FACE, false);
    }
}

function begin() {
    if (window.HAS_FACE) { runVerify(); return; }
    // No enrolled face: the ONLY way to mark is enrolling right here first
    // (document marking is reserved for enrolled people whose recognition fails).
    const card = document.getElementById('enrollCard');
    if (card) card.style.display = '';
    show('info', '<i class="fas fa-user-plus"></i> ' + I18N.enrollFirst);
    showActions(false, false); // just Cancel; the enroll card is the path forward
}

/* ---------- 1:1 verification with a real, configurable window ---------- */
async function runVerify() {
    if (verifying) return;
    verifying = true;
    hideActions();
    clearOverlay();

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
                drawGuideOval(false);
                show('info', spinner + I18N.placeFaceInOval);
                debugUpdate(null, lastDistance, identityOk, challenge, pitchBaseline);
                await wait(200);
                continue;
            }
            sawFace = true;

            if (!identityOk) {
                const d = Math.min(...refs.map(r => faceapi.euclideanDistance(r, det.descriptor)));
                lastDistance = d;
                if (d <= THRESHOLD) { identityOk = true; matchedDistance = d; }
            }
            // Green oval when the face is well framed; the detection box only in debug
            drawGuideOval(faceWellPlaced(det.detection.box));
            if (DEBUG) drawBox(det.detection.box, identityOk ? '#28a745' : '#ffc107');

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

            // Honest per-stage message: never ask for the gesture while the real
            // blocker is still the identity match.
            if (challenge && !challengeDone) {
                show('info', spinner + challengeLabel(challenge));
            } else {
                show('info', spinner + I18N.lookAtCamera.replace(':name', window.EMPLOYEE.name));
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
            drawGuideOval(faceWellPlaced(det.detection.box));
            setCountdown(null);
            return autoMarkByDocument(); // face on camera -> snapshot is meaningful evidence
        }
        drawGuideOval(false);
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
        const { data } = await postJson(window.MARK_DNI_URL, {
            document_number: window.EMPLOYEE.document,
            photo: captureSnapshot(),
        });
        if (data.ok) {
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
    if (window.HAS_FACE) { runVerify(); } else { begin(); hideActions(); }
}

/* ---------- marking ---------- */
async function commitFacial(distance) {
    // A FACIAL mark saves NO photo: the match distance + completed liveness
    // gesture are proof enough, and a snapshot per successful mark would just
    // pile up bytes on disk. Photos are kept only for the DNI fallback below.
    show('info', spinner + I18N.savingSlow);
    try {
        const { data } = await postJson(window.MARK_URL, {
            employee_id: Number(window.EMPLOYEE.id),
            distance: distance.toFixed(4),
        });
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
        const { data } = await postJson(window.MARK_DNI_URL, {
            document_number: window.EMPLOYEE.document,
            photo: captureSnapshot(),
        });
        if (data.ok) {
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
        if (det) { drawGuideOval(faceWellPlaced(det.detection.box)); return true; }
        drawGuideOval(false);
        await wait(250);
    }
    return false;
}

function finishWithResult(data, note) {
    clearOverlay();
    if (data.ok) {
        const color = data.status === 'LATE' ? 'warning' : 'success';
        const typeLabel = data.type === 'CHECK_IN' ? I18N.checkIn : I18N.checkOut;
        show(color, `<i class="fas fa-check-circle"></i> <strong>${typeLabel}</strong> ${I18N.recorded}: ${data.employee}<br>${data.time} — ${data.status_label}` + (note ? `<br><small>${note}</small>` : ''));
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

async function unlockEnroll() {
    const pin = (document.getElementById('enrollPin')?.value || '').trim();
    if (!pin) { setEnrollMessage('enrollPinMessage', 'warning', I18N.pinRequired); return; }
    setEnrollMessage('enrollPinMessage', 'info', spinner + I18N.unlocking);
    try {
        const { data } = await postJson(window.ENROLL_UNLOCK_URL, { pin });
        if (data.ok) {
            document.getElementById('enrollPinStep').style.display = 'none';
            document.getElementById('enrollCaptureStep').style.display = '';
        } else {
            setEnrollMessage('enrollPinMessage', 'danger', data.message || I18N.couldNotRecord);
        }
    } catch (e) { setEnrollMessage('enrollPinMessage', 'danger', I18N.connectionError); }
}

async function enrollNow() {
    if (!document.getElementById('enrollConsent').checked) {
        setEnrollMessage('enrollMessage', 'warning', I18N.consentRequired);
        return;
    }
    const btn = document.getElementById('enrollBtn');
    btn.disabled = true;

    const SAMPLES = 3;
    const descriptors = [];
    for (let i = 1; i <= SAMPLES; i++) {
        setEnrollMessage('enrollMessage', 'info', spinner + I18N.capturingSample.replace(':current', `<strong>${i}</strong>`).replace(':total', SAMPLES));
        let detection = null;
        for (let attempt = 0; attempt < 6 && !detection; attempt++) {
            try { detection = await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks().withFaceDescriptor(); } catch (e) { /* retry */ }
            clearOverlay();
            drawGuideOval(detection ? faceWellPlaced(detection.detection.box) : false);
            if (!detection) await wait(500);
        }
        if (!detection) {
            setEnrollMessage('enrollMessage', 'warning', I18N.noFaceInSample.replace(':current', i));
            btn.disabled = false;
            return;
        }
        descriptors.push(Array.from(detection.descriptor));
        await wait(900);
    }

    setEnrollMessage('enrollMessage', 'info', spinner + I18N.saving);
    try {
        const { data } = await postJson(window.ENROLL_DESCRIPTOR_URL, {
            employee_id: Number(window.EMPLOYEE.id),
            consent: true,
            descriptors,
        });
        if (data.ok) {
            setEnrollMessage('enrollMessage', 'success', '<i class="fas fa-check-circle"></i> ' + I18N.enrolled);
            window.HAS_FACE = true;
            refs = descriptors.map(d => new Float32Array(d));
            setTimeout(() => {
                document.getElementById('enrollCard').style.display = 'none';
                runVerify(); // straight into confirmation → mark
            }, 1200);
        } else {
            setEnrollMessage('enrollMessage', 'danger', data.message || I18N.couldNotRecord);
            btn.disabled = false;
        }
    } catch (e) {
        setEnrollMessage('enrollMessage', 'danger', I18N.connectionError);
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', start);
