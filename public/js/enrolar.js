/**
 * Enrolamiento facial con 3 MUESTRAS para mayor precisión.
 * Captura 3 descriptores (con leves variaciones naturales de pose) y los envía juntos:
 * el reconocimiento compara contra las 3, lo que reduce los falsos "no reconocido".
 */
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const estado = document.getElementById('estado');
const btn = document.getElementById('btnCapturar');

const MODELOS_URL = '/models';
const NUM_MUESTRAS = 3;
const OPCIONES_DETECTOR = new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

function mostrar(clase, html) {
    estado.className = 'alert alert-' + clase + ' mt-3';
    estado.innerHTML = html;
}

async function iniciar() {
    try {
        mostrar('info', '<span class="spinner-border spinner-border-sm mr-1"></span> Cargando modelos de reconocimiento facial...');
        await Promise.all([
            faceapi.nets.tinyFaceDetector.loadFromUri(MODELOS_URL),
            faceapi.nets.faceLandmark68Net.loadFromUri(MODELOS_URL),
            faceapi.nets.faceRecognitionNet.loadFromUri(MODELOS_URL),
        ]);

        mostrar('info', 'Modelos cargados. Encendiendo cámara...');
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            mostrar('success', 'Cámara lista. Mire de frente y presione el botón: se tomarán ' + NUM_MUESTRAS + ' capturas.');
            btn.disabled = false;
            marcoEnVivo();
        });
    } catch (e) {
        mostrar('danger', 'Error: ' + e.message + '. Verifique la cámara y que los modelos estén en /public/models.');
    }
}

/** Recuadro en vivo del rostro detectado */
async function marcoEnVivo() {
    const ctx = overlay.getContext('2d');
    setInterval(async () => {
        try {
            const det = await faceapi.detectSingleFace(video, OPCIONES_DETECTOR);
            ctx.clearRect(0, 0, overlay.width, overlay.height);
            if (det) {
                ctx.strokeStyle = '#28a745';
                ctx.lineWidth = 3;
                ctx.strokeRect(det.box.x, det.box.y, det.box.width, det.box.height);
            }
        } catch (e) { /* reintentar en el próximo ciclo */ }
    }, 400);
}

function esperar(ms) { return new Promise(r => setTimeout(r, ms)); }

btn.addEventListener('click', async () => {
    btn.disabled = true;
    const descriptores = [];

    for (let i = 1; i <= NUM_MUESTRAS; i++) {
        mostrar('info', `<span class="spinner-border spinner-border-sm mr-1"></span> Capturando muestra <strong>${i} de ${NUM_MUESTRAS}</strong>... mueva ligeramente la cabeza entre capturas.`);

        let deteccion = null;
        // Hasta 5 intentos por muestra
        for (let intento = 0; intento < 5 && !deteccion; intento++) {
            deteccion = await faceapi
                .detectSingleFace(video, OPCIONES_DETECTOR)
                .withFaceLandmarks()
                .withFaceDescriptor();
            if (!deteccion) await esperar(500);
        }

        if (!deteccion) {
            mostrar('warning', `No se detectó el rostro en la muestra ${i}. Acérquese, mejore la iluminación e intente de nuevo.`);
            btn.disabled = false;
            return;
        }

        descriptores.push(Array.from(deteccion.descriptor));
        await esperar(900); // pausa entre capturas para variar levemente la pose
    }

    mostrar('info', '<span class="spinner-border spinner-border-sm mr-1"></span> Guardando en la base de datos...');

    try {
        const res = await fetch(window.ENROLAR_URL, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',   // clave: fuerza respuesta JSON (errores 422 visibles, no redirecciones)
                'X-CSRF-TOKEN': window.CSRF
            },
            body: JSON.stringify({ descriptores }),
        });

        const data = await res.json();

        if (res.ok && data.ok) {
            mostrar('success', '<i class="fas fa-check-circle"></i> ' + data.mensaje + ' Redirigiendo...');
            setTimeout(() => (window.location = window.INDEX_URL), 1500);
        } else {
            mostrar('danger', 'El servidor rechazó el enrolamiento: ' + (data.message || JSON.stringify(data.errors || data)));
            btn.disabled = false;
        }
    } catch (e) {
        mostrar('danger', 'Error de comunicación: ' + e.message);
        btn.disabled = false;
    }
});

document.addEventListener('DOMContentLoaded', iniciar);
