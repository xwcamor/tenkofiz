/**
 * Kiosco de marcado facial - versión robusta
 * Máquina de estados: CARGANDO -> ESCANEANDO -> PROCESANDO -> PAUSA -> ESCANEANDO
 * Con mensajes progresivos y recuperación garantizada ante errores.
 */
const video = document.getElementById('video');
const overlay = document.getElementById('overlay');
const estado = document.getElementById('estado');

const MODELOS_URL = '/models';
const PAUSA_TRAS_MARCA_MS = 5000;
const COOLDOWN_MS = 60000;
const UMBRAL = 0.55; // debe coincidir con KioscoController::UMBRAL
const OPCIONES_DETECTOR_KIOSCO = () => new faceapi.TinyFaceDetectorOptions({ inputSize: 416, scoreThreshold: 0.5 });

let fase = 'CARGANDO';          // CARGANDO | ESCANEANDO | PROCESANDO | PAUSA
let matcher = null;
const ultimoIntento = {};
let timerLento = null;

/* Reloj */
setInterval(() => {
    const d = new Date();
    document.getElementById('reloj').textContent = d.toLocaleTimeString('es-PE');
    document.getElementById('fecha').textContent = d.toLocaleDateString('es-PE', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}, 500);

function mostrar(tipo, html) {
    estado.className = `alert alert-${tipo} d-inline-block px-4 px-md-5`;
    estado.innerHTML = html;
}

function limpiarOverlay() {
    overlay.getContext('2d').clearRect(0, 0, overlay.width, overlay.height);
}

function dibujarCaja(box, color) {
    const ctx = overlay.getContext('2d');
    ctx.strokeStyle = color;
    ctx.lineWidth = 4;
    ctx.strokeRect(box.x, box.y, box.width, box.height);
}

/** Aviso si algo tarda demasiado: cambia el mensaje a los 3 segundos */
function avisarSiTarda(mensajeLento) {
    clearTimeout(timerLento);
    timerLento = setTimeout(() => {
        mostrar('info', '<span class="spinner-border spinner-border-sm me-1"></span> ' + mensajeLento);
    }, 3000);
}

async function iniciar() {
    try {
        mostrar('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> Cargando modelos de reconocimiento (1/3)...');
        await faceapi.nets.tinyFaceDetector.loadFromUri(MODELOS_URL);
        mostrar('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> Cargando puntos faciales (2/3)...');
        await faceapi.nets.faceLandmark68Net.loadFromUri(MODELOS_URL);
        mostrar('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> Cargando red de reconocimiento (3/3)...');
        await faceapi.nets.faceRecognitionNet.loadFromUri(MODELOS_URL);

        mostrar('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> Consultando empleados enrolados...');
        const res = await fetch(window.DESCRIPTORES_URL);
        const empleados = await res.json();

        if (!empleados.length) {
            mostrar('warning', 'No hay empleados con rostro enrolado.');
            return;
        }

        // Cada empleado puede tener VARIAS muestras: el matcher compara contra todas
        const etiquetados = empleados.map(e =>
            new faceapi.LabeledFaceDescriptors(
                String(e.id) + '|' + e.nombre,
                e.descriptores.map(d => new Float32Array(d))
            )
        );
        matcher = new faceapi.FaceMatcher(etiquetados, UMBRAL);

        mostrar('secondary', '<span class="spinner-border spinner-border-sm me-1"></span> Encendiendo cámara...');
        const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'user', width: { ideal: 640 }, height: { ideal: 480 } } });
        video.srcObject = stream;

        video.addEventListener('playing', () => {
            overlay.width = video.videoWidth;
            overlay.height = video.videoHeight;
            fase = 'ESCANEANDO';
            mostrar('secondary', 'Esperando rostro...');
            setInterval(cicloDeteccion, 1300);
        });
    } catch (e) {
        mostrar('danger', 'Error al iniciar: ' + e.message + '<br><small>Verifique la cámara y la carpeta /public/models</small>');
    }
}

async function cicloDeteccion() {
    // Solo escanear en fase ESCANEANDO; en PROCESANDO/PAUSA no tocar la pantalla
    if (fase !== 'ESCANEANDO' || !matcher) return;

    let det;
    try {
        det = await faceapi
            .detectSingleFace(video, OPCIONES_DETECTOR_KIOSCO())
            .withFaceLandmarks()
            .withFaceDescriptor();
    } catch (e) {
        return; // fallo puntual de detección: reintentar en el próximo ciclo
    }

    if (fase !== 'ESCANEANDO') return; // pudo cambiar mientras detectaba

    limpiarOverlay();

    // Sin rostro: restablecer el mensaje SIEMPRE (arregla el bug de quedarse pegado)
    if (!det) {
        mostrar('secondary', 'Esperando rostro...');
        return;
    }

    const match = matcher.findBestMatch(det.descriptor);
    const reconocido = match.label !== 'unknown';

    dibujarCaja(det.detection.box, reconocido ? '#28a745' : '#dc3545');

    if (!reconocido) {
        mostrar('danger', '<i class="fas fa-times-circle"></i> Rostro no reconocido');
        return; // sigue escaneando: al salir del cuadro vuelve a "Esperando rostro..."
    }

    const [id, nombre] = match.label.split('|');

    // Cooldown por persona
    if (ultimoIntento[id] && Date.now() - ultimoIntento[id] < COOLDOWN_MS) {
        return;
    }
    ultimoIntento[id] = Date.now();

    // ===== PROCESANDO =====
    fase = 'PROCESANDO';
    mostrar('info', `<span class="spinner-border spinner-border-sm me-1"></span> Verificando identidad de ${nombre}...`);
    avisarSiTarda('Registrando en la base de datos, un momento por favor...');

    try {
        const res = await fetch(window.MARCAR_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
            body: JSON.stringify({ empleado_id: Number(id), distancia: match.distance.toFixed(4) }),
        });
        const data = await res.json();
        clearTimeout(timerLento);

        if (data.ok) {
            const color = data.estado === 'TARDANZA' ? 'warning' : 'success';
            mostrar(color, `<i class="fas fa-check-circle"></i> <strong>${data.tipo}</strong> registrada: ${data.empleado}<br>${data.hora} — ${data.estado}`);
        } else {
            mostrar('warning', '<i class="fas fa-info-circle"></i> ' + (data.mensaje || 'No se pudo registrar.'));
        }
    } catch (e) {
        clearTimeout(timerLento);
        mostrar('danger', 'Error de conexión con el servidor. Reintentando en unos segundos...');
    } finally {
        // ===== PAUSA y retorno GARANTIZADO al escaneo (arregla el bug de quedarse trabado) =====
        fase = 'PAUSA';
        setTimeout(() => {
            limpiarOverlay();
            mostrar('secondary', 'Esperando rostro...');
            fase = 'ESCANEANDO';
        }, PAUSA_TRAS_MARCA_MS);
    }
}

document.addEventListener('DOMContentLoaded', iniciar);
