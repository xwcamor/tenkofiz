/* Supervisor enrollment page: PIN → find employee → consent → capture 3 samples.
 * Its own page (no modals): the camera stays visible at the top the whole time. */
'use strict';

const MODELS_URL = '/models';
const DETECTOR = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });
const I18N = window.KIOSK_I18N;
const spinner = '<span class="spinner-border spinner-border-sm me-1"></span> ';
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const statusBox = document.getElementById('status');

let employeeId = null;
let capturing = false; // while true the capture loop owns the overlay

function show(type, html) {
    statusBox.className = `alert alert-${type} d-inline-block px-4`;
    statusBox.innerHTML = html;
}
function wait(ms) { return new Promise(r => setTimeout(r, ms)); }

/* Face-placement guide (oval, RENIEC-style): white until the face is centered
 * and at a good size, green when it is. Well-centered samples make a much
 * better enrolled template, so it matters even more here than when marking. */
function clearOverlay() { overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height); }
function ovalGeom() {
    const w = overlay.width, h = overlay.height;
    return { cx: w / 2, cy: h * 0.47, rx: w * 0.30, ry: h * 0.40 };
}
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
}
function faceWellPlaced(box) {
    const { cx, cy, rx, ry } = ovalGeom();
    const bcx = box.x + box.width / 2, bcy = box.y + box.height / 2;
    const centered = Math.abs(bcx - cx) < rx * 0.55 && Math.abs(bcy - cy) < ry * 0.5;
    const sized = box.height > ry * 0.9 && box.height < ry * 1.95;
    return centered && sized;
}
/** Continuous live guide (paused while the capture loop is running its own detection) */
async function guideLoop() {
    while (true) {
        if (!capturing) {
            let det = null;
            try { det = await faceapi.detectSingleFace(video, DETECTOR()); } catch (e) { det = null; }
            clearOverlay();
            drawGuideOval(det ? faceWellPlaced(det.box) : false);
        }
        await wait(200);
    }
}
function setMessage(id, type, html) {
    document.getElementById(id).innerHTML = html ? `<div class="alert alert-${type} py-2 small mb-2">${html}</div>` : '';
}
async function postJson(url, payload) {
    const res = await fetch(url, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
        body: JSON.stringify(payload),
    });
    return { status: res.status, data: await res.json().catch(() => ({})) };
}

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
            show('secondary', I18N.cameraReady);
            guideLoop(); // live placement oval from now on
        }, { once: true });
    } catch (e) {
        show('danger', I18N.startError + ' ' + e.message);
    }
}

async function unlockEnroll() {
    const pin = document.getElementById('enrollPin').value.trim();
    if (!pin) { setMessage('pinMessage', 'warning', I18N.pinRequired); return; }
    setMessage('pinMessage', 'info', spinner + I18N.unlocking);
    try {
        const { data } = await postJson(window.ENROLL_UNLOCK_URL, { pin });
        if (data.ok) {
            document.getElementById('stepPin').style.display = 'none';
            document.getElementById('stepLookup').style.display = '';
        } else {
            setMessage('pinMessage', 'danger', data.message || I18N.couldNotRecord);
        }
    } catch (e) { setMessage('pinMessage', 'danger', I18N.connectionError); }
}

async function lookupEmployee() {
    const documentNumber = document.getElementById('enrollDni').value.trim();
    if (!/^\d{8,12}$/.test(documentNumber)) { setMessage('lookupMessage', 'warning', I18N.dniIncomplete); return; }
    setMessage('lookupMessage', 'info', spinner + I18N.searching);
    try {
        const { data } = await postJson(window.ENROLL_LOOKUP_URL, { document_number: documentNumber });
        if (data.ok) {
            employeeId = data.employee_id;
            document.getElementById('stepCapture').style.display = '';
            document.getElementById('enrollName').textContent = data.name;
            document.getElementById('hasFaceWarning').style.display = data.has_face ? '' : 'none';
            document.getElementById('enrollConsent').checked = false;
            setMessage('lookupMessage', '', '');
            setMessage('captureMessage', '', '');
            document.getElementById('stepCapture').scrollIntoView({ behavior: 'smooth', block: 'center' });
        } else {
            setMessage('lookupMessage', 'warning', data.message || I18N.couldNotRecord);
        }
    } catch (e) { setMessage('lookupMessage', 'danger', I18N.connectionError); }
}

async function captureSamples() {
    if (!document.getElementById('enrollConsent').checked) { setMessage('captureMessage', 'warning', I18N.consentRequired); return; }
    const btn = document.getElementById('captureBtn');
    btn.disabled = true;
    capturing = true; // take the overlay from the guide loop
    // Make sure the person sees themself while capturing
    document.querySelector('.video-frame').scrollIntoView({ behavior: 'smooth', block: 'center' });

    const SAMPLES = 3;
    const descriptors = [];
    for (let i = 1; i <= SAMPLES; i++) {
        setMessage('captureMessage', 'info', spinner + I18N.capturingSample.replace(':current', `<strong>${i}</strong>`).replace(':total', SAMPLES));
        let detection = null;
        for (let attempt = 0; attempt < 6 && !detection; attempt++) {
            try { detection = await faceapi.detectSingleFace(video, DETECTOR()).withFaceLandmarks().withFaceDescriptor(); } catch (e) { /* retry */ }
            clearOverlay();
            drawGuideOval(detection ? faceWellPlaced(detection.detection.box) : false);
            if (!detection) await wait(500);
        }
        if (!detection) { setMessage('captureMessage', 'warning', I18N.noFaceInSample.replace(':current', i)); btn.disabled = false; capturing = false; return; }
        descriptors.push(Array.from(detection.descriptor));
        await wait(900);
    }
    capturing = false; // hand the overlay back to the guide loop

    setMessage('captureMessage', 'info', spinner + I18N.saving);
    try {
        const { data } = await postJson(window.ENROLL_DESCRIPTOR_URL, { employee_id: employeeId, consent: true, descriptors });
        if (data.ok) {
            setMessage('captureMessage', 'success', '<i class="fas fa-check-circle"></i> ' + I18N.enrolled);
            // Reset for the next person (batch enrollment stays unlocked 15 min)
            setTimeout(() => {
                document.getElementById('stepCapture').style.display = 'none';
                document.getElementById('enrollDni').value = '';
                btn.disabled = false;
            }, 2200);
        } else {
            setMessage('captureMessage', 'danger', data.message || I18N.couldNotRecord);
            btn.disabled = false;
        }
    } catch (e) {
        setMessage('captureMessage', 'danger', I18N.connectionError);
        btn.disabled = false;
    }
}

document.addEventListener('DOMContentLoaded', start);
