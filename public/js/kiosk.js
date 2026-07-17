/**
 * Facial marking kiosk - robust version
 * State machine: LOADING -> SCANNING -> PROCESSING -> PAUSED -> SCANNING
 * With progressive messages and guaranteed recovery after errors.
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
const KIOSK_DETECTOR_OPTIONS = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

let phase = 'LOADING';          // LOADING | SCANNING | PROCESSING | PAUSED
let matcher = null;
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

async function start() {
    try {
        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingModels1);
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL);
        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingModels2);
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL);
        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingModels3);
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL);

        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.loadingEmployees);
        const res = await fetch(window.DESCRIPTORS_URL);
        const employees = await res.json();

        if (!employees.length) {
            show('warning', I18N.noEmployees);
            return;
        }

        // Each employee may have SEVERAL samples: the matcher compares against all of them
        const labeled = employees.map(employee =>
            new faceapi.LabeledFaceDescriptors(
                String(employee.id) + '|' + employee.name,
                employee.descriptors.map(descriptor => new Float32Array(descriptor))
            )
        );
        matcher = new faceapi.FaceMatcher(labeled, THRESHOLD);

        show('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> ' + I18N.startingCamera);
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            phase = 'SCANNING';
            show('secondary', I18N.waitingFace);
            setInterval(detectionCycle, 1300);
        });
    } catch (e) {
        show('danger', I18N.startError + ' ' + e.message + '<br><small>' + I18N.startErrorHint + '</small>');
    }
}

async function detectionCycle() {
    // Only scan while in SCANNING phase; don't touch the screen in PROCESSING/PAUSED
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
        const res = await fetch(window.MARK_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
            body: JSON.stringify({ employee_id: Number(id), distance: match.distance.toFixed(4) }),
        });
        const data = await res.json();
        clearTimeout(slowTimer);

        if (data.ok) {
            const color = data.status === 'LATE' ? 'warning' : 'success';
            const typeLabel = data.type === 'CHECK_IN' ? I18N.checkIn : I18N.checkOut;
            show(color, `<i class="fas fa-check-circle"></i> <strong>${typeLabel}</strong> ${I18N.recorded}: ${data.employee}<br>${data.time} — ${data.status_label}`);
        } else {
            show('warning', '<i class="fas fa-info-circle"></i> ' + (data.message || I18N.couldNotRecord));
        }
    } catch (e) {
        clearTimeout(slowTimer);
        show('danger', I18N.connectionError);
    } finally {
        // ===== PAUSED then GUARANTEED return to scanning (fixes the stuck-state bug) =====
        phase = 'PAUSED';
        setTimeout(() => {
            clearOverlay();
            show('secondary', I18N.waitingFace);
            phase = 'SCANNING';
        }, PAUSE_AFTER_MARK_MS);
    }
}

document.addEventListener('DOMContentLoaded', start);
