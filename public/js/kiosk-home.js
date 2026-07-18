/* Kiosk landing: document-first keypad. No camera here — the person is validated
 * against this site's employees and only then the camera page opens. */
'use strict';

setInterval(() => {
    const now = new Date();
    const timeZone = window.KIOSK_TZ || undefined;
    document.getElementById('clock').textContent = now.toLocaleTimeString(window.KIOSK_LOCALE, { timeZone });
    document.getElementById('date').textContent = now.toLocaleDateString(window.KIOSK_LOCALE, { timeZone, weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' });
}, 500);

const I18N = window.KIOSK_I18N;
const spinner = '<span class="spinner-border spinner-border-sm me-1"></span> ';
let dniValue = '';

function renderDni() { document.getElementById('dniDisplay').textContent = dniValue || ' '; }
function dniKey(d) { if (dniValue.length < 12) { dniValue += d; renderDni(); } }
function dniBackspace() { dniValue = dniValue.slice(0, -1); renderDni(); }
function dniClear() { dniValue = ''; renderDni(); }

function setMessage(type, html) {
    document.getElementById('dniMessage').innerHTML = html
        ? `<div class="alert alert-${type} py-2 small">${html}</div>` : '';
}

async function submitLookup() {
    if (!/^\d{8,12}$/.test(dniValue)) { setMessage('warning', I18N.dniIncomplete); return; }

    const btn = document.getElementById('dniSubmitBtn');
    btn.disabled = true;
    setMessage('info', spinner + I18N.searching);

    try {
        const res = await fetch(window.LOOKUP_URL, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json', 'X-CSRF-TOKEN': window.CSRF },
            body: JSON.stringify({ document_number: dniValue }),
        });
        const data = await res.json().catch(() => ({}));

        if (data.ok && data.redirect) {
            window.location.href = data.redirect; // on to the camera page
            return;
        }
        setMessage('warning', data.message || I18N.connectionError);
    } catch (e) {
        setMessage('danger', I18N.connectionError);
    } finally {
        btn.disabled = false;
    }
}

// Physical keyboard support (USB numeric pads, testing)
document.addEventListener('keydown', (e) => {
    if (e.key >= '0' && e.key <= '9') { dniKey(e.key); e.preventDefault(); }
    else if (e.key === 'Backspace') { dniBackspace(); e.preventDefault(); }
    else if (e.key === 'Enter') { submitLookup(); e.preventDefault(); }
});
