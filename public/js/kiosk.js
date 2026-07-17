/**
 * Facial marking kiosk - robust version
 * State machine: LOADING -> SCANNING -> PROCESSING -> PAUSED -> SCANNING
 * ('UI' phase while an overlay panel is open: scanning is suspended)
 *
 * Extras:
 *  - DNI fallback marking with an evidence snapshot
 *  - Supervisor-unlocked self-enrollment mode (PIN + consent + 3 samples)
 *  - Smart descriptor refresh: polls a tiny /version endpoint and only
 *    re-downloads the face list when it actually changed in the database.
 *
 * UI strings come from window.KIOSK_I18N (injected by the Blade view).
 */
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const statusBox = document.getElementById('status');
const I18N = window.KIOSK_I18N || {};

const MODELS_URL = '/models';
const PAUSE_AFTER_MARK_MS = 5000;
const COOLDOWN_MS = 60000;
const THRESHOLD = 0.55; // must match KioskController::THRESHOLD
const VERSION_POLL_MS = 5 * 60 * 1000; // tiny request; full list only when changed
const KIOSK_DETECTOR_OPTIONS = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

let phase = 'LOADING';          // LOADING | SCANNING | PROCESSING | PAUSED | UI
let matcher = null;
let descriptorsVersion = null;
const lastAttempt = {};
let slowTimer = null;

/* Clock (shown in the company timezone) */
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

function clearOverlay() {
    overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);
}

function drawBox(box, color) {
    const ctx = overlay.getContext('2d');
    ctx.strokeStyle = color;
    ctx.lineWidth = 4;
    ctx.strokeRect(box.x, box.y, box.width, box.height);
}

/** Warns when something takes too long: switches the message after 3 seconds */
function warnIfSlow(slowMessage) {
    clearTimeout(slowTimer);
    slowTimer = setTimeout(() => {
        show('info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + slowMessage);
    }, 3000);
}

async function postJson(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
        body: JSON.stringify(payload),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
}

/** Downloads the descriptor list and rebuilds the in-memory matcher */
async function loadDescriptors() {
    const res = await fetch(window.DESCRIPTORS_URL, { headers: { Accept: 'application/json' } });
    const payload = await res.json();
    descriptorsVersion = payload.version;

    if (!payload.employees.length) {
        matcher = null;
        return 0;
    }

    // Each employee may have SEVERAL samples: the matcher compares against all of them
    const labeled = payload.employees.map(employee =>
        new faceapi.LabeledFaceDescriptors(
            String(employee.id) + '|' + employee.name,
            employee.descriptors.map(descriptor => new Float32Array(descriptor))
        )
    );
    matcher = new faceapi.FaceMatcher(labeled, THRESHOLD);
    return payload.employees.length;
}

/**
 * Smart refresh: compares a tiny fingerprint against the database instead of
 * blindly re-downloading everything. New enrollments show up within minutes
 * without reloading the page.
 */
async function pollVersion() {
    try {
        const res = await fetch(window.VERSION_URL, { headers: { Accept: 'application/json' } });
        const { version } = await res.json();
        if (version && version !== descriptorsVersion) {
            await loadDescriptors();
            console.info('Kiosk: face list refreshed');
        }
    } catch (e) {
        /* offline blip: retry on the next poll */
    }
}

async function start() {
    try {
        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingModels1);
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingModels2);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL);
        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingModels3);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL);

        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingEmployees);
        const count = await loadDescriptors();
        if (count === 0) {
            show('warning', I18N.noEmployees);
            // Keep going: the camera is still needed for DNI evidence and enrollment
        }

        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.startingCamera);
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            phase = 'SCANNING';
            show('secondary', I18N.waitingFace);
            setInterval(detectionCycle, 1300);
            setInterval(pollVersion, VERSION_POLL_MS);
        });
    } catch (e) {
        show('danger', I18N.startError + ' ' + e.message + '<br><small>' + I18N.startErrorHint + '</small>');
    }
}

async function detectionCycle() {
    // Only scan while in SCANNING phase; don't touch the screen in PROCESSING/PAUSED/UI
    if (phase !== 'SCANNING' || !matcher) return;

    let detection;
    try {
        detection = await faceapi
            .detectSingleFace(video, KIOSK_DETECTOR_OPTIONS())
            .withFaceLandmarks()
            .withFaceDescriptor();
    } catch (e) {
        return; // transient detection failure: retry on the next cycle
    }

    if (phase !== 'SCANNING') return; // it may have changed while detecting

    clearOverlay();

    // No face: ALWAYS reset the message (fixes the stuck-message bug)
    if (!detection) {
        show('secondary', I18N.waitingFace);
        return;
    }

    const match = matcher.findBestMatch(detection.descriptor);
    const recognized = match.label !== 'unknown';

    drawBox(detection.detection.box, recognized ? '#28a745' : '#dc3545');

    if (!recognized) {
        show('danger', '<i class="fas fa-times-circle"></i> ' + I18N.notRecognized);
        return; // keep scanning: leaving the frame resets to "Waiting for a face..."
    }

    const [id, name] = match.label.split('|');

    // Per-person cooldown
    if (lastAttempt[id] && Date.now() - lastAttempt[id] < COOLDOWN_MS) {
        return;
    }
    lastAttempt[id] = Date.now();

    // ===== PROCESSING =====
    phase = 'PROCESSING';
    show('info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.verifying.replace(':name', name));
    warnIfSlow(I18N.savingSlow);

    try {
        const { data } = await postJson(window.MARK_URL, { employee_id: Number(id), distance: match.distance.toFixed(4) });
        clearTimeout(slowTimer);
        showMarkResult(data);
    } catch (e) {
        clearTimeout(slowTimer);
        show('danger', I18N.connectionError);
    } finally {
        pauseThenScan();
    }
}

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
    // ===== PAUSED then GUARANTEED return to scanning (fixes the stuck-state bug) =====
    phase = 'PAUSED';
    setTimeout(() => {
        if (phase !== 'PAUSED') return; // a panel may have opened meanwhile
        clearOverlay();
        show('secondary', I18N.waitingFace);
        phase = 'SCANNING';
    }, PAUSE_AFTER_MARK_MS);
}

/** Captures the current camera frame as a small JPEG (evidence for DNI marks) */
function captureSnapshot() {
    if (!video.videoWidth) return null;
    const canvas = document.createElement('canvas');
    const width = 480;
    canvas.width = width;
    canvas.height = Math.round(video.videoHeight * (width / video.videoWidth));
    canvas.getContext('2d').drawImage(video, 0, 0, canvas.width, canvas.height);
    return canvas.toDataURL('image/jpeg', 0.8);
}

/* =====================================================================
   DNI fallback marking
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
    show('secondary', I18N.waitingFace);
    phase = 'SCANNING';
}

function renderDni() {
    document.getElementById('dniDisplay').textContent = dniValue || ' ';
}

function dniKey(digit) {
    if (dniValue.length < 12) {
        dniValue += digit;
        renderDni();
    }
}

function dniBackspace() {
    dniValue = dniValue.slice(0, -1);
    renderDni();
}

function dniClear() {
    dniValue = '';
    renderDni();
}

function setPanelMessage(id, type, html) {
    document.getElementById(id).innerHTML = html
        ? `<div class="alert alert-${type} py-2 small mb-0">${html}</div>`
        : '';
}

async function submitDniMark() {
    if (!/^\d{8,12}$/.test(dniValue)) {
        setPanelMessage('dniMessage', 'warning', I18N.dniIncomplete);
        return;
    }

    const btn = document.getElementById('dniSubmitBtn');
    btn.disabled = true;
    setPanelMessage('dniMessage', 'info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.marking);

    try {
        const { data } = await postJson(window.MARK_DNI_URL, {
            document_number: dniValue,
            photo: captureSnapshot(),
        });

        if (data.ok) {
            document.getElementById('dniPanel').classList.remove('open');
            showMarkResult(data);
            phase = 'PROCESSING'; // showMarkResult stays on screen during the pause
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
    const panel = document.getElementById('enrollPanel');
    panel.classList.remove('open', 'capturing');
    resumeScanning();
}

async function enrollUnlock() {
    const pin = document.getElementById('enrollPin').value.trim();
    if (!pin) {
        setPanelMessage('enrollPinMessage', 'warning', I18N.pinRequired);
        return;
    }

    setPanelMessage('enrollPinMessage', 'info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.unlocking);

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
    } catch (e) {
        setPanelMessage('enrollPinMessage', 'danger', I18N.connectionError);
    }
}

async function enrollLookup() {
    const documentNumber = document.getElementById('enrollDni').value.trim();
    if (!/^\d{8,12}$/.test(documentNumber)) {
        setPanelMessage('enrollLookupMessage', 'warning', I18N.dniIncomplete);
        return;
    }

    setPanelMessage('enrollLookupMessage', 'info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.searching);

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
    } catch (e) {
        setPanelMessage('enrollLookupMessage', 'danger', I18N.connectionError);
    }
}

function wait(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }

async function enrollCapture() {
    if (!document.getElementById('enrollConsent').checked) {
        setPanelMessage('enrollCaptureMessage', 'warning', I18N.consentRequired);
        return;
    }

    const btn = document.getElementById('enrollCaptureBtn');
    btn.disabled = true;
    document.getElementById('enrollPanel').classList.add('capturing'); // let the person see the camera
    const SAMPLES = 3;
    const descriptors = [];

    // The panel covers the video, but the stream keeps running underneath
    for (let i = 1; i <= SAMPLES; i++) {
        setPanelMessage('enrollCaptureMessage', 'info',
            '<span class="spinner-border spinner-border-sm me-1"></span> ' +
            I18N.capturingSample.replace(':current', `<strong>${i}</strong>`).replace(':total', SAMPLES));

        let detection = null;
        for (let attempt = 0; attempt < 5 && !detection; attempt++) {
            try {
                detection = await faceapi
                    .detectSingleFace(video, KIOSK_DETECTOR_OPTIONS())
                    .withFaceLandmarks()
                    .withFaceDescriptor();
            } catch (e) { /* retry */ }
            if (!detection) await wait(500);
        }

        if (!detection) {
            setPanelMessage('enrollCaptureMessage', 'warning', I18N.noFaceInSample.replace(':current', i));
            btn.disabled = false;
            return;
        }

        descriptors.push(Array.from(detection.descriptor));
        await wait(900);
    }

    setPanelMessage('enrollCaptureMessage', 'info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.saving);

    try {
        const { data } = await postJson(window.ENROLL_DESCRIPTOR_URL, {
            employee_id: enrollEmployeeId,
            consent: true,
            descriptors,
        });

        if (data.ok) {
            setPanelMessage('enrollCaptureMessage', 'success', '<i class="fas fa-check-circle"></i> ' + I18N.enrolled);
            await loadDescriptors(); // recognize the new face immediately
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
