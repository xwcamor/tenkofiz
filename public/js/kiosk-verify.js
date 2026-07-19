/* Kiosk camera page: the person was already validated by document on the landing
 * page. Here the camera confirms it is really them (1:1). If they have no
 * enrolled face, they can enroll right here and continue. Nothing auto-returns:
 * on timeout the person chooses (retry / mark by document / cancel). */
'use strict';

const MODELS_URL = '/models';
const THRESHOLD = Number(window.KIOSK_THRESHOLD) || 0.5;
const LIVENESS = !!window.KIOSK_LIVENESS;
const REQUIRE_FACE = window.KIOSK_REQUIRE_FACE !== false;
const VERIFY_WINDOW_MS = (Number(window.KIOSK_VERIFY_SECONDS) || 15) * 1000;
const RESULT_PAUSE_MS = 4000;
const EAR_CLOSED = 0.18; // absolute closed-eye threshold (the adaptive baseline handles glasses)
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
function wait(ms) { return new Promise(r => setTimeout(r, ms)); }
function setProgress(pct) { document.getElementById('verifyProgress').style.width = pct + '%'; }
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

/* Blink / liveness.
 * Glasses (and small eyes) compress the eye-aspect-ratio, so fixed thresholds
 * miss real blinks. The detector is ADAPTIVE: it learns the person's own
 * open-eye baseline from recent samples and fires on a significant RELATIVE
 * drop (or the absolute closed threshold, whichever happens first). A printed
 * photo still fails: its ratio is constant, so there is never a drop. */
function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }
function eyeRatio(eye) { return (dist(eye[1], eye[5]) + dist(eye[2], eye[4])) / (2 * dist(eye[0], eye[3])); }
function eyeAspectRatio(landmarks) { return (eyeRatio(landmarks.getLeftEye()) + eyeRatio(landmarks.getRightEye())) / 2; }

const earSamples = [];
let lastBaseline = null, lastFactor = 0.83; // exposed for the debug overlay
function blinkDetected(ear, factor = 0.83) {
    lastFactor = factor;
    // Baseline = the person's typical OPEN eye (median of the upper half of samples)
    if (earSamples.length >= 4) {
        const sorted = [...earSamples].sort((a, b) => a - b);
        const baseline = sorted[Math.floor(sorted.length * 0.75)]; // upper quartile
        lastBaseline = baseline;
        const relativeDrop = baseline > 0.12 && ear < baseline * factor;
        if (relativeDrop || ear < EAR_CLOSED) return true;
    } else if (ear < EAR_CLOSED) {
        return true;
    }
    earSamples.push(ear);
    if (earSamples.length > 14) earSamples.shift();
    return false;
}

/* ---------- live diagnosis (open /kiosk/verify?debug=1) ---------- */
const DEBUG = new URLSearchParams(location.search).has('debug');
let debugBox = null, debugSamples = 0, debugWindowStart = 0, debugHz = 0;
function debugUpdate(ear, distance, identityOk) {
    if (!DEBUG) return;
    if (!debugBox) {
        debugBox = document.createElement('div');
        debugBox.style.cssText = 'position:fixed;left:8px;bottom:8px;z-index:99;background:rgba(0,0,0,.85);color:#7CFC00;font:12px/1.5 monospace;padding:8px 10px;border-radius:8px;text-align:left';
        document.body.appendChild(debugBox);
    }
    const now = Date.now();
    debugSamples++;
    if (now - debugWindowStart > 1000) { debugHz = debugSamples; debugSamples = 0; debugWindowStart = now; }
    const need = lastBaseline ? (lastBaseline * lastFactor).toFixed(3) : '—';
    debugBox.innerHTML =
        `EAR ojo: <b>${ear !== null ? ear.toFixed(3) : '—'}</b><br>` +
        `base: ${lastBaseline ? lastBaseline.toFixed(3) : '—'} | dispara &lt; ${need} (o &lt; ${EAR_CLOSED})<br>` +
        `factor: ${lastFactor} | muestras/s: ${debugHz}<br>` +
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
        // the match the loop switches to the cheap landmarks-only pass and hunts
        // the blink at the camera's real frame rate. Sampling eyes through the
        // slow pass was why real blinks (~120ms) kept falling between samples.
        let blinked = !LIVENESS, sawFace = false;
        let identityOk = false, matchedDistance = null, identityLockedAt = null;
        let lastDistance = null;
        earSamples.length = 0; // fresh baseline per attempt
        const startAt = Date.now();
        const deadline = startAt + VERIFY_WINDOW_MS;

        while (Date.now() < deadline) {
            setProgress(Math.min(100, Math.round((Date.now() - startAt) * 100 / VERIFY_WINDOW_MS)));

            let det;
            try {
                det = identityOk
                    ? await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks()
                    : await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks().withFaceDescriptor();
            } catch (e) { det = null; }

            clearOverlay();
            if (!det) {
                show('info', spinner + I18N.comeCloser.replace(':name', window.EMPLOYEE.name));
                debugUpdate(null, lastDistance, identityOk);
                await wait(200);
                continue;
            }
            sawFace = true;

            if (!identityOk) {
                const d = Math.min(...refs.map(r => faceapi.euclideanDistance(r, det.descriptor)));
                lastDistance = d;
                if (d <= THRESHOLD) { identityOk = true; matchedDistance = d; identityLockedAt = Date.now(); }
            }
            drawBox(det.detection.box, identityOk ? '#28a745' : '#ffc107');

            const ear = eyeAspectRatio(det.landmarks);
            if (LIVENESS && !blinked) {
                // The longer a confirmed person keeps trying, the more forgiving the
                // relative threshold gets (capped at a 12% dip, which a static
                // photo's constant ratio never produces).
                const hunting = identityLockedAt ? Date.now() - identityLockedAt : 0;
                const factor = hunting > 8000 ? 0.88 : (hunting > 4000 ? 0.86 : 0.83);
                blinked = blinkDetected(ear, factor);
            }
            debugUpdate(ear, lastDistance, identityOk);

            // Honest per-stage message: never ask for a blink while the real
            // blocker is still the identity match.
            if (!identityOk || blinked || !LIVENESS) {
                show('info', spinner + I18N.lookAtCamera.replace(':name', window.EMPLOYEE.name));
            } else {
                show('info', spinner + I18N.blinkNow);
            }

            if (identityOk && blinked) {
                setProgress(100);
                verifying = false;
                return commitFacial(matchedDistance);
            }
            // Blink hunting runs flat out (no artificial pause once identity locked)
            await wait(identityOk ? 15 : 150);
        }

        // Window over: the PERSON decides what happens next — no silent fallbacks.
        setProgress(0);
        clearOverlay();
        verifying = false;
        if (sawFace) {
            show('warning', '<i class="fas fa-user-clock"></i> ' + I18N.notConfirmed.replace(':sec', String(VERIFY_WINDOW_MS / 1000)));
            showActions(true); // retry / document+photo / cancel
        } else {
            show('warning', '<i class="fas fa-exclamation-triangle"></i> ' + I18N.noFaceSeen);
            showActions(!REQUIRE_FACE); // without a face, document marking only if the rule allows it
        }
    } catch (e) {
        verifying = false;
        show('danger', I18N.connectionError);
        showActions(true);
    }
}

function retryVerify() {
    if (window.HAS_FACE) { runVerify(); } else { begin(); hideActions(); }
}

/* ---------- marking ---------- */
async function commitFacial(distance) {
    show('info', spinner + I18N.savingSlow);
    try {
        const { data } = await postJson(window.MARK_URL, {
            employee_id: Number(window.EMPLOYEE.id),
            distance: distance.toFixed(4),
            photo: captureSnapshot(),
        });
        finishWithResult(data);
    } catch (e) {
        show('danger', I18N.connectionError);
        showActions(true);
    }
}

async function markByDocument() {
    hideActions();

    // Require-face rule also applies to the photo evidence path: a face must be on
    // camera at the moment of the snapshot (5s grace), otherwise nothing is saved.
    if (REQUIRE_FACE && cameraOk) {
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
        if (det) { drawBox(det.detection.box, '#28a745'); return true; }
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
