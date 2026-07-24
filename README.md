# Tenkofiz — Attendance control with facial recognition

Multi-tenant SaaS for employee attendance control using **in-browser facial recognition**
(face-api.js) on a kiosk screen (a tablet at the entrance), with weekly schedules, breaks,
vacation and justification workflows, formal reports (Excel/PDF) and a full audit log.

The face never leaves the browser as an image: only a 128-value mathematical descriptor is
stored, and enrollment requires the employee's explicit biometric-data consent.

---

## What it does

Each **workspace** (company) is fully isolated: its own employees, sites, schedules,
settings and data. A **super-admin** operates the platform (creates workspaces, suspends for
non-payment, calibrates the recognition engine); each workspace has its own admins, managers
and employees driven by module permissions.

### Attendance kiosk (facial)
- **1:1 facial verification** in the browser: the person types their document, the camera
  confirms it is them, and marks. Only the descriptor is stored, never the photo.
- **Two independent clocks** so a present person is never punished for trying:
  *inactivity* (`kiosk_verify_seconds`) counts only while **no** face is on camera and returns
  to the keypad; *attempt* (`kiosk_match_seconds`) counts only while a face **is** present and,
  if it never matches, falls back to document + evidence photo.
- **Liveness challenge**: random head-gesture prompt to defeat a printed photo or video.
- **DNI fallback**: if the face is not recognized, the mark is recorded by document with an
  evidence snapshot flagged for supervisor review (only for already-enrolled people).
- **Guided self-enrollment** on the first mark, with on-screen biometric consent.
- **Kiosk hardening per site**: one-time pairing code + device cookie, so only the authorized
  tablet opens that site's kiosk; every mark logs IP and user agent.
- **Optional geolocation** stored on each mark (can be required).
- **RENIEC autofill** of names from the document number via the Decolecta API.

### Schedules & marking rules
- **Three schedule types**: **Fixed** (start time + tolerance → judges tardiness),
  **Flexible** (daily hour target, no tardiness) and **Free** (no rules — every punch is just
  logged with its evidence for a person to review; unlimited marks per day).
- **Weekly schedules** with per-day hours and **overnight shifts** (crossing midnight).
- **Schedules by period (vigencias)**: the assigned schedule can change over date ranges
  (rotating shifts); reports use the one in force on each date.
- **Break control**: optional mid-shift break in/out marks, with a configurable limit.
- **Credited (async) hours**: remote hours counted as done per working day (never a deficit).
- **Automatic absences**: a daily job flags ABSENT anyone without a record (skips holidays,
  days off, and approved vacations).

### Management
- **Employees, sites (sedes), areas, positions, users and profiles** with module-level
  permissions (employees, attendance, reports, approvals, users, profiles, schedules,
  holidays, audit log, settings).
- **Approvals**: vacation requests and absence justifications with supporting documents;
  in-app notification bell + email alerts; printable PDFs.
- **Vacation balance** per employee.
- **Holidays**: country-customizable recurring templates.

### Reports & data
- Worked hours/days per period, compliance (**expected vs worked vs owed**), late minutes,
  break analysis; **Excel exports** (with AutoFilter) and a **printable formal sheet (PDF)**.
- Dashboards with charts; site KPIs.
- **Audit log** of sensitive actions (deletions, manual edits, user creation) with before/after
  data, IP and pagination; **soft deletes with reason** and an admin-only trash view.
- **Backups** (DB + uploads, with retention) and **evidence-photo purge** (data minimization).

### Platform
- **i18n** Spanish/English; server runs and stores in **UTC**, with a company operational
  timezone and a per-user display timezone.
- **Dark mode**; AdminLTE 3 / Bootstrap 4 UI.
- **Local vendor assets** with CDN fallback (works offline on a LAN kiosk).

---

## Tech stack

| Layer | Choice |
|---|---|
| Framework | Laravel 13 · PHP 8.3 |
| Frontend | AdminLTE 3 · Bootstrap 4 · jQuery · Select2 · Chart.js · SweetAlert2 |
| Facial recognition | `@vladmandic/face-api` (in-browser, WASM/WebGL) |
| Excel / PDF | PhpSpreadsheet · dompdf |
| Database | MySQL/MariaDB (production) · SQLite (dev/tests) |
| Tests | PHPUnit — **183 tests** |

---

## Quick setup

```bash
git clone <repo> && cd tenkofiz
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite            # or configure MySQL in .env
php artisan migrate --seed
bash download_models.sh                    # face models → public/models (required by the kiosk)
php artisan serve
```

Default test users (change immediately): `admin@test.com`, `aprobador@test.com`,
`empleado@test.com` — all with password `123456`.

Optional demo data:

```bash
php artisan demo:workforce        # 4 employees across 2 sites (1 flexible), breaks on, seeded attendance
php artisan demo:academic         # institute demo: 1 instructor, 2 periods with presential + async hours
php artisan attendances:seed-demo # realistic present/late/absent/excused history
```

Then follow **[docs/CONFIGURACION.md](docs/CONFIGURACION.md)** so nothing is left unconfigured.

---

## Custom artisan commands

| Command | What it does |
|---|---|
| `attendances:mark-absences` | Flags ABSENT everyone without a record that day (skips holidays, days off, vacations) |
| `attendances:seed-demo` | Generates realistic demo attendance respecting schedules, holidays and vacations |
| `demo:workforce` | Creates a demo workforce (2 sites, a flexible schedule, breaks) with attendance |
| `demo:academic` | Seeds an educational-institute demo with periods and async hours |
| `kiosk:purge-evidence` | Deletes old DNI-mark evidence photos (records are kept) |
| `system:backup` | Zips the database and `public/uploads` into `storage/app/backups` with retention |

### Scheduled tasks
`attendances:mark-absences` runs daily at **23:50 company time**; `system:backup` and
`kiosk:purge-evidence` are scheduled too. In production run `php artisan schedule:work`
(or a system cron calling `schedule:run`).

---

## Testing

```bash
php artisan test          # 183 tests
```

Tests run on an in-memory/SQLite database and pin the clock where dates matter, so they are
deterministic on any day of the year.

---

## Documentation (`docs/`, in Spanish)

Step-by-step guides — start here after cloning or when setting up a server:

| Guide | Answers |
|---|---|
| [INSTALACION.md](docs/INSTALACION.md) | "I downloaded the repo, now what?" — stack, install, default credentials, common errors |
| [CONFIGURACION.md](docs/CONFIGURACION.md) | **Post-install checklist**: every `.env` variable, in-app Settings, recognition calibration, face models, cron, kiosk hardening |
| [BASE_DE_DATOS.md](docs/BASE_DE_DATOS.md) | SQLite ↔ MySQL/MariaDB, `DB_*` parameters, backups, useful commands |
| [CORREO.md](docs/CORREO.md) | SMTP setup (Gmail app passwords, Mailtrap, log driver), emails sent, how to test |
| [REGLAS_DE_NEGOCIO.md](docs/REGLAS_DE_NEGOCIO.md) | **Developer reference**: every business rule and where it lives in the code |
| [ARQUITECTURA_MULTISEDE_Y_SAAS.md](docs/ARQUITECTURA_MULTISEDE_Y_SAAS.md) | Multi-tenant (workspaces) and multi-site (sedes) architecture and isolation |
| [ROADMAP_TURNOS_Y_RECONOCIMIENTO.md](docs/ROADMAP_TURNOS_Y_RECONOCIMIENTO.md) | Schedule types and recognition roadmap/decisions |

---

## Security & data-protection notes

- Only the **facial descriptor** (128 numbers) is stored, never the photograph; enrollment
  requires recorded biometric consent.
- Evidence photos exist only for the DNI fallback and are purged on a schedule.
- `APP_TIMEZONE` must stay `UTC`; the business timezone lives in Settings.
- Never commit secrets: `DECOLECTA_API_TOKEN` and mail credentials belong only in `.env`.
- Recognition calibration (match threshold and the two kiosk timers) is **super-admin only** —
  workspace admins never see it, because a wrong threshold lets anyone pass as anyone.
