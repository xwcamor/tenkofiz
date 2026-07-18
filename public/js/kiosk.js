/**
 * Facial marking kiosk - two modes (configured in Settings → Facial):
 *
 *  · VERIFY mode (default): the employee types their document, then the camera
 *    confirms it is really them (1:1). No confusion between similar faces; the
 *    threshold can be strict. If the face can't be confirmed (or the person has
 *    no enrolled face), it marks by document and saves an evidence photo.
 *
 *  · FAST mode (auto-scan): the old 1:N flow — stand in front and be recognized.
 *
 * Extras: liveness (blink) check, evidence photo on facial marks, DNI fallback,
 * PIN-unlocked self-enrollment, smart descriptor refresh (fast mode only).
 * UI strings come from window.KIOSK_I18N.
 */
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const statusBox = document.getElementById('status');
const I18N = window.KIOSK_I18N || {};

const MODELS_URL = '/models';
const PAUSE_AFTER_MARK_MS = 5000;
const COOLDOWN_MS = 60000;
const THRESHOLD = Number(window.KIOSK_THRESHOLD) || 0.5; // match strictness (lower = stricter)
const FAST_MODE = !!window.KIOSK_FAST_MODE;
const LIVENESS = !!window.KIOSK_LIVENESS;
const VERIFY_WINDOW_MS = 7000;   // how long the 1:1 verification tries before giving up
const EAR_OPEN = 0.25;           // eye-aspect-ratio thresholds for the blink detector
const EAR_CLOSED = 0.18;
const VERSION_POLL_MS = 5 * 60 * 1000;
const KIOSK_DETECTOR_OPTIONS = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

let phase = 'LOADING';          // LOADING | SCANNING | PROCESSING | PAUSED | UI | VERIFYING | IDLE
let matcher = null;             // only built in FAST mode
let descriptorsVersion = null;
const lastAttempt = {};
let slowTimer = null;

/* Clock (company timezone) */
setInterval(() => {
    const now = new Date();
    const timeZone = window.KIOSK_TZ || undefined;
    document.getElementById('clock').textContent = now.toLocaleTimeString(window.KIOSK_LOCALE, { timeZone });
    document.getElementById('date').textContent = now.toLocaleDateString(window.KIOSK_LOCALE, { timeZone, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}, 500);

function show(type, html) {
    statusBox.className = `alert alert-${type} d-inline-block px-4 px-md-5`;
    statusBox.innerHTML = html;
}
const spinner = '<span class="spinner-border spinner-border-sm me-1"></span> ';
function clearOverlay() { overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height); }
function drawBox(box, color) {
    const ctx = overlay.getContext('2d');
    ctx.strokeStyle = color; ctx.lineWidth = 4;
    ctx.strokeRect(box.x, box.y, box.width, box.height);
}
function wait(ms) { return new Promise(r => setTimeout(r, ms)); }

function warnIfSlow(slowMessage) {
    clearTimeout(slowTimer);
    slowTimer = setTimeout(() => show('info', spinner + slowMessage), 3000);
}

async function postJson(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
        body: JSON.stringify(payload),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
}

/** Captures the current camera frame as a small JPEG (evidence) */
function captureSnapshot() {
    if (!video.videoWidth) return null;
    const canvas = document.createElement('canvas');
    const width = 480;
    canvas.width = width;
    canvas.height = Math.round(video.videoHeight * (width / video.videoWidth));
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.8);
}

/* ---------- blink / liveness ---------- */
function dist(a, b) { return Math.hypot(a.x - b.x, a.y - b.y); }
function eyeRatio(eye) { return (dist(eye[1], eye[5]) + dist(eye[2], eye[4])) / (2 * dist(eye[0], eye[3])); }
function eyeAspectRatio(landmarks) { return (eyeRatio(landmarks.getLeftEye()) + eyeRatio(landmarks.getRightEye())) / 2; }

/* =====================================================================
   FAST mode: 1:N auto-scan (download all descriptors, recognize anyone)
   ===================================================================== */
async function loadDescriptors() {
    const res = await fetch(window.DESCRIPTORS_URL, { headers: { Accept: 'application/json' } });
    const payload = await res.json();
    descriptorsVersion = payload.version;
    if (!payload.employees.length) { matcher = null; return 0; }
    const labeled = payload.employees.map(e =>
        new faceapi.LabeledFaceDescriptors(String(e.id) + '|' + e.name, e.descriptors.map(d => new Float32Array(d))));
    matcher = new faceapi.FaceMatcher(labeled, THRESHOLD);
    return payload.employees.length;
}

async function pollVersion() {
    try {
        const res = await fetch(window.VERSION_URL, { headers: { Accept: 'application/json' } });
        const { version } = await res.json();
        if (version && version !== descriptorsVersion) { await loadDescriptors(); }
    } catch (e) { /* offline blip */ }
}

async function detectionCycle() {
    if (phase !== 'SCANNING' || !matcher) return;
    let detection;
    try {
        detection = await faceapi.detectSingleFace(video, KIOSK_DETECTOR_OPTIONS()).withFaceLandmarks().withFaceDescriptor();
    } catch (e) { return; }
    if (phase !== 'SCANNING') return;
    clearOverlay();
    if (!detection) { show('secondary', I18N.waitingFace); return; }

    const match = matcher.findBestMatch(detection.descriptor);
    const recognized = match.label !== 'unknown';
    drawBox(detection.detection.box, recognized ? '#28a745' : '#dc3545');
    if (!recognized) { show('danger', '<i class="fas fa-times-circle"></i> ' + I18N.notRecognized); return; }

    const [id, name] = match.label.split('|');
    if (lastAttempt[id] && Date.now() - lastAttempt[id] < COOLDOWN_MS) return;
    lastAttempt[id] = Date.now();

    phase = 'PROCESSING';
    show('info', spinner + I18N.verifying.replace(':name', name));
    warnIfSlow(I18N.savingSlow);
    try {
        const { data } = await postJson(window.MARK_URL, { employee_id: Number(id), distance: match.distance.toFixed(4), photo: captureSnapshot() });
        clearTimeout(slowTimer);
        showMarkResult(data);
    } catch (e) {
        clearTimeout(slowTimer);
        show('danger', I18N.connectionError);
    } finally {
        pauseThenScan();
    }
}

/* =====================================================================
   VERIFY mode: type DNI -> 1:1 face confirmation -> mark (or DNI + photo)
   ===================================================================== */
async function verifyAndMark(dni) {
    document.getElementById('dniPanel').classList.remove('open');
    phase = 'VERIFYING';
    show('info', spinner + I18N.marking);

    // 1) fetch just this person's face
    let person;
    try {
        const res = await fetch(window.FACE_URL_BASE + '/' + encodeURIComponent(dni), { headers: { Accept: 'application/json' } });
        if (res.status === 404) return markByDniWithPhoto(dni, I18N.notEnrolledPhoto);
        person = await res.json();
        if (!person.ok) return markByDniWithPhoto(dni, I18N.notEnrolledPhoto);
    } catch (e) {
        return markByDniWithPhoto(dni, I18N.verifyFailedPhoto);
    }

    // 2) confirm it is really them (1:1) within the time window
    const refs = person.descriptors.map(d => new Float32Array(d));
    let blinked = !LIVENESS, sawOpen = false, bestDistance = 1;
    const deadline = Date.now() + VERIFY_WINDOW_MS;

    while (Date.now() < deadline) {
        let det;
        try {
            det = await faceapi.detectSingleFace(video, KIOSK_DETECTOR_OPTIONS()).withFaceLandmarks().withFaceDescriptor();
        } catch (e) { det = null; }

        if (det) {
            clearOverlay();
            const d = Math.min(...refs.map(r => faceapi.euclideanDistance(r, det.descriptor)));
            bestDistance = Math.min(bestDistance, d);
            const ok = d <= THRESHOLD;
            drawBox(det.detection.box, ok ? '#28a745' : '#ffc107');

            if (LIVENESS && !blinked) {
                const ear = eyeAspectRatio(det.landmarks);
                if (ear > EAR_OPEN) sawOpen = true;
                if (sawOpen && ear < EAR_CLOSED) blinked = true;
                show('info', spinner + (blinked ? I18N.lookAtCamera.replace(':name', person.name) : I18N.blinkNow));
            } else {
                show('info', spinner + I18N.lookAtCamera.replace(':name', person.name));
            }

            if (ok && blinked) return commitFacial(person, d);
        }
        await wait(300);
    }

    // 3) could not confirm -> mark by document with evidence photo (flagged)
    return markByDniWithPhoto(dni, I18N.verifyFailedPhoto);
}

async function commitFacial(person, distance) {
    show('info', spinner + I18N.savingSlow);
    try {
        const { data } = await postJson(window.MARK_URL, {
            employee_id: Number(person.id),
            distance: distance.toFixed(4),
            photo: captureSnapshot(),
        });
        showMarkResult(data);
    } catch (e) {
        show('danger', I18N.connectionError);
    }
    resumeIdleOrScan();
}

async function markByDniWithPhoto(dni, note) {
    try {
        const { data } = await postJson(window.MARK_DNI_URL, { document_number: dni, photo: captureSnapshot() });
        if (data.ok) {
            showMarkResult(data);
            if (note) statusBox.innerHTML += `<br><small>${note}</small>`;
        } else {
            show('warning', data.message || I18N.couldNotRecord);
        }
    } catch (e) {
        show('danger', I18N.connectionError);
    }
    resumeIdleOrScan();
}

function resumeIdleOrScan() {
    clearOverlay();
    if (FAST_MODE) { pauseThenScan(); return; }
    phase = 'PAUSED';
    setTimeout(() => { if (phase === 'PAUSED') { phase = 'IDLE'; show('secondary', I18N.typeDocument); } }, PAUSE_AFTER_MARK_MS);
}

/* ---------- shared result / pause ---------- */
function showMarkResult(data) {
    if (data.ok) {
        const color = data.status === 'LATE' ? 'warning' : 'success';
        const typeLabel = data.type === 'CHECK_IN' ? I18N.checkIn : I18N.checkOut;
        show(color, `<i class="fas fa-check-circle"></i> <strong>${typeLabel}</strong> ${I18N.recorded}: ${data.employee}<br>${data.time} — ${data.status_label}`);
    } else {
        show('warning', '<i class="fas fa-info-circle"></i> ' + (data.message || I18N.couldNotRecord));
    }
}

function pauseThenScan() {
    phase = 'PAUSED';
    setTimeout(() => {
        if (phase !== 'PAUSED') return;
        clearOverlay();
        show('secondary', FAST_MODE ? I18N.waitingFace : I18N.typeDocument);
        phase = FAST_MODE ? 'SCANNING' : 'IDLE';
    }, PAUSE_AFTER_MARK_MS);
}

/* =====================================================================
   Startup
   ===================================================================== */
async function start() {
    try {
        show('secondary', spinner + I18N.loadingModels1);
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
        show('secondary', spinner + I18N.loadingModels2);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL);
        show('secondary', spinner + I18N.loadingModels3);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL);

        if (FAST_MODE) {
            show('secondary', spinner + I18N.loadingEmployees);
            const count = await loadDescriptors();
            if (count === 0) show('warning', I18N.noEmployees);
        }

        show('secondary', spinner + I18N.startingCamera);
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            if (FAST_MODE) {
                phase = 'SCANNING';
                show('secondary', I18N.waitingFace);
                setInterval(detectionCycle, 1300);
                setInterval(pollVersion, VERSION_POLL_MS);
            } else {
                phase = 'IDLE';
                show('secondary', I18N.typeDocument);
            }
        });
    } catch (e) {
        show('danger', I18N.startError + ' ' + e.message + '<br><small>' + I18N.startErrorHint + '</small>');
    }
}

/* =====================================================================
   DNI panel (entry point in VERIFY mode; fallback button in FAST mode)
   ===================================================================== */
let dniValue = '';

function openDniPanel() {
    phase = 'UI';
    dniValue = '';
    renderDni();
    setPanelMessage('dniMessage', '', '');
    document.getElementById('dniPanel').classList.add('open');
}
function closeDniPanel() {
    document.getElementById('dniPanel').classList.remove('open');
    resumeScanning();
}
function resumeScanning() {
    clearOverlay();
    if (FAST_MODE) { show('secondary', I18N.waitingFace); phase = 'SCANNING'; }
    else { show('secondary', I18N.typeDocument); phase = 'IDLE'; }
}
function renderDni() { document.getElementById('dniDisplay').textContent = dniValue || ' '; }
function dniKey(d) { if (dniValue.length < 12) { dniValue += d; renderDni(); } }
function dniBackspace() { dniValue = dniValue.slice(0, -1); renderDni(); }
function dniClear() { dniValue = ''; renderDni(); }
function setPanelMessage(id, type, html) {
    document.getElementById(id).innerHTML = html ? `<div class="alert alert-${type} py-2 small mb-0">${html}</div>` : '';
}

async function submitDniMark() {
    if (!/^\d{8,12}$/.test(dniValue)) {
        setPanelMessage('dniMessage', 'warning', I18N.dniIncomplete);
        return;
    }
    // VERIFY mode: confirm the face 1:1 on the main screen
    if (!FAST_MODE) {
        const dni = dniValue;
        return verifyAndMark(dni);
    }
    // FAST mode: the DNI button is the fallback -> mark by document + evidence photo
    const btn = document.getElementById('dniSubmitBtn');
    btn.disabled = true;
    setPanelMessage('dniMessage', 'info', spinner + I18N.marking);
    try {
        const { data } = await postJson(window.MARK_DNI_URL, { document_number: dniValue, photo: captureSnapshot() });
        if (data.ok) {
            document.getElementById('dniPanel').classList.remove('open');
            showMarkResult(data);
            phase = 'PROCESSING';
            pauseThenScan();
        } else {
            setPanelMessage('dniMessage', 'warning', data.message || I18N.couldNotRecord);
        }
    } catch (e) {
        setPanelMessage('dniMessage', 'danger', I18N.connectionError);
    } finally {
        btn.disabled = false;
    }
}

/* =====================================================================
   Self-enrollment mode (PIN protected)
   ===================================================================== */
let enrollEmployeeId = null;

function openEnrollPanel() {
    phase = 'UI';
    enrollEmployeeId = null;
    document.getElementById('enrollStepPin').style.display = '';
    document.getElementById('enrollStepLookup').style.display = 'none';
    document.getElementById('enrollStepCapture').style.display = 'none';
    document.getElementById('enrollPin').value = '';
    setPanelMessage('enrollPinMessage', '', '');
    document.getElementById('enrollPanel').classList.add('open');
}
function closeEnrollPanel() {
    document.getElementById('enrollPanel').classList.remove('open', 'capturing');
    resumeScanning();
}
async function enrollUnlock() {
    const pin = document.getElementById('enrollPin').value.trim();
    if (!pin) { setPanelMessage('enrollPinMessage', 'warning', I18N.pinRequired); return; }
    setPanelMessage('enrollPinMessage', 'info', spinner + I18N.unlocking);
    try {
        const { data } = await postJson(window.ENROLL_UNLOCK_URL, { pin });
        if (data.ok) {
            document.getElementById('enrollStepPin').style.display = 'none';
            document.getElementById('enrollStepLookup').style.display = '';
            document.getElementById('enrollDni').value = '';
            setPanelMessage('enrollLookupMessage', '', '');
        } else {
            setPanelMessage('enrollPinMessage', 'danger', data.message || I18N.couldNotRecord);
        }
    } catch (e) { setPanelMessage('enrollPinMessage', 'danger', I18N.connectionError); }
}
async function enrollLookup() {
    const documentNumber = document.getElementById('enrollDni').value.trim();
    if (!/^\d{8,12}$/.test(documentNumber)) { setPanelMessage('enrollLookupMessage', 'warning', I18N.dniIncomplete); return; }
    setPanelMessage('enrollLookupMessage', 'info', spinner + I18N.searching);
    try {
        const { data } = await postJson(window.ENROLL_LOOKUP_URL, { document_number: documentNumber });
        if (data.ok) {
            enrollEmployeeId = data.employee_id;
            document.getElementById('enrollStepLookup').style.display = 'none';
            document.getElementById('enrollStepCapture').style.display = '';
            document.getElementById('enrollName').textContent = data.name;
            document.getElementById('enrollHasFaceWarning').style.display = data.has_face ? '' : 'none';
            document.getElementById('enrollConsent').checked = false;
            setPanelMessage('enrollCaptureMessage', '', '');
        } else {
            setPanelMessage('enrollLookupMessage', 'warning', data.message || I18N.couldNotRecord);
        }
    } catch (e) { setPanelMessage('enrollLookupMessage', 'danger', I18N.connectionError); }
}
async function enrollCapture() {
    if (!document.getElementById('enrollConsent').checked) { setPanelMessage('enrollCaptureMessage', 'warning', I18N.consentRequired); return; }
    const btn = document.getElementById('enrollCaptureBtn');
    btn.disabled = true;
    document.getElementById('enrollPanel').classList.add('capturing');
    const SAMPLES = 3;
    const descriptors = [];
    for (let i = 1; i <= SAMPLES; i++) {
        setPanelMessage('enrollCaptureMessage', 'info', spinner + I18N.capturingSample.replace(':current', `<strong>${i}</strong>`).replace(':total', SAMPLES));
        let detection = null;
        for (let attempt = 0; attempt < 5 && !detection; attempt++) {
            try { detection = await faceapi.detectSingleFace(video, KIOSK_DETECTOR_OPTIONS()).withFaceLandmarks().withFaceDescriptor(); } catch (e) { /* retry */ }
            if (!detection) await wait(500);
        }
        if (!detection) { setPanelMessage('enrollCaptureMessage', 'warning', I18N.noFaceInSample.replace(':current', i)); btn.disabled = false; return; }
        descriptors.push(Array.from(detection.descriptor));
        await wait(900);
    }
    setPanelMessage('enrollCaptureMessage', 'info', spinner + I18N.saving);
    try {
        const { data } = await postJson(window.ENROLL_DESCRIPTOR_URL, { employee_id: enrollEmployeeId, consent: true, descriptors });
        if (data.ok) {
            setPanelMessage('enrollCaptureMessage', 'success', '<i class="fas fa-check-circle"></i> ' + I18N.enrolled);
            if (FAST_MODE) await loadDescriptors();
            setTimeout(closeEnrollPanel, 2500);
        } else {
            setPanelMessage('enrollCaptureMessage', 'danger', data.message || I18N.couldNotRecord);
            btn.disabled = false;
        }
    } catch (e) {
        setPanelMessage('enrollCaptureMessage', 'danger', I18N.connectionError);
        btn.disabled = false;
    }
    btn.disabled = false;
}

document.addEventListener('DOMContentLoaded', start);
