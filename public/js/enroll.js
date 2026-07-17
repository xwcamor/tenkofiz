/**
 * Face enrollment with 3 SAMPLES for better accuracy.
 * Captures 3 descriptors (with slight natural pose variations) and sends them together:
 * recognition compares against all 3, reducing false "not recognized" results.
 * Requires the biometric consent checkbox to be accepted before sending.
 * UI strings come from window.ENROLL_I18N (injected by the Blade view).
 */
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const statusBox = document.getElementById('status');
const captureBtn = document.getElementById('captureBtn');
const consentCheck = document.getElementById('consentCheck');
const I18N = window.ENROLL_I18N || {};

const MODELS_URL = '/models';
const SAMPLE_COUNT = 3;
const DETECTOR_OPTIONS = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

function show(type, html) {
    statusBox.className = 'alert alert-' + type + ' mt-3';
    statusBox.innerHTML = html;
}

async function start() {
    try {
        show('info', '<span class="spinner-border spinner-border-sm mr-1"></span> ' + I18N.loadingModels);
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODELS_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODELS_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODELS_URL),
        ]);

        show('info', I18N.startingCamera);
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            show('success', I18N.cameraReady.replace(':count', SAMPLE_COUNT));
            captureBtn.disabled = false;
            liveFrame();
        });
    } catch (e) {
        show('danger', I18N.cameraError.replace(':message', e.message));
    }
}

/** Live frame around the detected face */
async function liveFrame() {
    const ctx = overlay.getContext('2d');
    setInterval(async () => {
        try {
            const detection = await faceapi.detectSingleFace(video, DETECTOR_OPTIONS);
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            if (detection) {
                ctx.strokeStyle = '#28a745';
                ctx.lineWidth = 3;
                ctx.strokeRect(detection.box.x, detection.box.y, detection.box.width, detection.box.height);
            }
        } catch (e) { /* retry on the next cycle */ }
    }, 400);
}

function wait(ms) { return new Promise(resolve => setTimeout(resolve, ms)); }

captureBtn.addEventListener('click', async () => {
    // Data protection: consent is mandatory before capturing biometric data
    if (consentCheck && !consentCheck.checked) {
        show('warning', '<i class="fas fa-user-shield"></i> ' + I18N.consentRequired);
        return;
    }

    captureBtn.disabled = true;
    const descriptors = [];

    for (let i = 1; i <= SAMPLE_COUNT; i++) {
        show('info', '<span class="spinner-border spinner-border-sm mr-1"></span> ' +
            I18N.capturingSample.replace(':current', `<strong>${i}</strong>`).replace(':total', SAMPLE_COUNT));

        let detection = null;
        // Up to 5 attempts per sample
        for (let attempt = 0; attempt < 5 && !detection; attempt++) {
            detection = await faceapi
                .detectSingleFace(video, DETECTOR_OPTIONS)
                .withFaceLandmarks()
                .withFaceDescriptor();
            if (!detection) await wait(500);
        }

        if (!detection) {
            show('warning', I18N.noFaceInSample.replace(':current', i));
            captureBtn.disabled = false;
            return;
        }

        descriptors.push(Array.from(detection.descriptor));
        await wait(900); // pause between captures to vary the pose slightly
    }

    show('info', '<span class="spinner-border spinner-border-sm mr-1"></span> ' + I18N.saving);

    try {
        const res = await fetch(window.ENROLL_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',   // key: forces a JSON response (422 errors visible, no redirects)
                'X-CSRF-TOKEN': window.CSRF
            },
            body: JSON.stringify({ descriptors, consent: consentCheck ? consentCheck.checked : false }),
        });

        const data = await res.json();

        if (res.ok && data.ok) {
            show('success', '<i class="fas fa-check-circle"></i> ' + data.message + ' ' + I18N.redirecting);
            setTimeout(() => (window.location = window.INDEX_URL), 1500);
        } else {
            show('danger', I18N.rejected + ' ' + (data.message || JSON.stringify(data.errors || data)));
            captureBtn.disabled = false;
        }
    } catch (e) {
        show('danger', I18N.connectionError + ' ' + e.message);
        captureBtn.disabled = false;
    }
});

document.addEventListener('DOMContentLoaded', start);
