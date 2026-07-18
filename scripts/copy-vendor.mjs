/**
 * Copies the frontend libraries from node_modules into public/vendor so the
 * app (especially the kiosk tablet) works without internet access.
 * Run once after `npm install`:  npm run vendor
 * The Blade views use vendor_asset(): local file when present, CDN otherwise.
 */
import { cpSync, mkdirSync, writeFileSync, existsSync } from 'fs';
import { dirname, join } from 'path';
import { fileURLToPath } from 'url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const nm = join(root, 'node_modules');
const out = join(root, 'public', 'vendor');

const FILES = [
    // AdminLTE + Bootstrap 4 (main app)
    ['admin-lte/dist/css/adminlte.min.css', 'adminlte/adminlte.min.css'],
    ['admin-lte/dist/js/adminlte.min.js', 'adminlte/adminlte.min.js'],
    ['bootstrap4/dist/css/bootstrap.min.css', 'bootstrap4/bootstrap.min.css'],
    ['bootstrap4/dist/js/bootstrap.bundle.min.js', 'bootstrap4/bootstrap.bundle.min.js'],
    // Bootstrap 5 (kiosk)
    ['bootstrap5/dist/css/bootstrap.min.css', 'bootstrap5/bootstrap.min.css'],
    // Core JS
    ['jquery/dist/jquery.min.js', 'jquery/jquery.min.js'],
    ['chart.js/dist/chart.umd.js', 'chartjs/chart.umd.min.js'],
    ['sweetalert2/dist/sweetalert2.all.min.js', 'sweetalert2/sweetalert2.all.min.js'],
    ['@vladmandic/face-api/dist/face-api.js', 'faceapi/face-api.min.js'],
    // FullCalendar
    ['fullcalendar/index.global.min.js', 'fullcalendar/index.global.min.js'],
    ['@fullcalendar/core/locales/es.global.min.js', 'fullcalendar/es.global.min.js'],
    // DataTables
    ['datatables.net/js/jquery.dataTables.min.js', 'datatables/jquery.dataTables.min.js'],
    ['datatables.net-bs4/js/dataTables.bootstrap4.min.js', 'datatables/dataTables.bootstrap4.min.js'],
    ['datatables.net-bs4/css/dataTables.bootstrap4.min.css', 'datatables/dataTables.bootstrap4.min.css'],
    ['datatables.net-responsive/js/dataTables.responsive.min.js', 'datatables/dataTables.responsive.min.js'],
    ['datatables.net-responsive-bs4/js/responsive.bootstrap4.min.js', 'datatables/responsive.bootstrap4.min.js'],
    ['datatables.net-responsive-bs4/css/responsive.bootstrap4.min.css', 'datatables/responsive.bootstrap4.min.css'],
    ['datatables.net-buttons/js/dataTables.buttons.min.js', 'datatables/dataTables.buttons.min.js'],
    ['datatables.net-buttons-bs4/js/buttons.bootstrap4.min.js', 'datatables/buttons.bootstrap4.min.js'],
    ['datatables.net-buttons-bs4/css/buttons.bootstrap4.min.css', 'datatables/buttons.bootstrap4.min.css'],
    ['datatables.net-buttons/js/buttons.html5.min.js', 'datatables/buttons.html5.min.js'],
    ['datatables.net-buttons/js/buttons.print.min.js', 'datatables/buttons.print.min.js'],
    ['jszip/dist/jszip.min.js', 'jszip/jszip.min.js'],
    // Select2 (AJAX autocomplete for large employee lists)
    ['select2/dist/css/select2.min.css', 'select2/select2.min.css'],
    ['select2/dist/js/select2.min.js', 'select2/select2.min.js'],
    ['select2/dist/js/i18n/es.js', 'select2/i18n/es.js'],
    ['select2-bootstrap4-theme/dist/select2-bootstrap4.min.css', 'select2/select2-bootstrap4.min.css'],
];

const DIRS = [
    // Font Awesome keeps its css/../webfonts structure
    ['@fortawesome/fontawesome-free/css/all.min.css', 'fontawesome/css/all.min.css'],
    ['@fortawesome/fontawesome-free/webfonts', 'fontawesome/webfonts'],
    // Inter font files (a small CSS is generated below)
    ['@fontsource/inter/files', 'inter/files'],
];

let copied = 0;
for (const [src, dest] of [...FILES, ...DIRS]) {
    const from = join(nm, src);
    const to = join(out, dest);
    if (!existsSync(from)) {
        console.warn(`SKIP (missing): ${src}`);
        continue;
    }
    mkdirSync(dirname(to), { recursive: true });
    cpSync(from, to, { recursive: true });
    copied++;
}

// Minimal Inter stylesheet (replaces the Google Fonts request)
const interCss = [400, 500, 600, 700].map(weight => `@font-face {
  font-family: 'Inter';
  font-style: normal;
  font-weight: ${weight};
  font-display: swap;
  src: url('./files/inter-latin-${weight}-normal.woff2') format('woff2');
}`).join('\n');
mkdirSync(join(out, 'inter'), { recursive: true });
writeFileSync(join(out, 'inter', 'inter.css'), interCss + '\n');

console.log(`Vendored ${copied} assets into public/vendor — the app now works without CDNs.`);
