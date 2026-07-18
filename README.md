# Attendance Control System with Facial Recognition

Laravel application for employee attendance control using facial recognition in the browser
(face-api.js) through a kiosk screen (tablet at the entrance), with vacation and justification
workflows, reports and a full audit log.

## Features

- **Facial marking kiosk**: check-in / check-out with face matching done in the browser
  (only a 128-value mathematical descriptor is stored, never the photograph).
- **Kiosk device restriction**: an access token can be generated in Settings so only the
  authorized tablet can open the kiosk; every mark also records the device IP and user agent.
- **DNI fallback marking**: when the face is not detected, the employee types their document
  number on a keypad; an evidence snapshot is stored and the mark is flagged for review.
- **Kiosk self-enrollment mode**: a supervisor unlocks it with a PIN (Settings); the employee
  types their document, accepts the biometric consent on screen and captures 3 samples.
- **Smart face-list refresh**: the kiosk polls a tiny version endpoint and only re-downloads
  descriptors when they changed in the database — new enrollments appear without reloading.
- **RENIEC autofill**: the employee form can look a DNI up through the Decolecta API
  (set `DECOLECTA_API_TOKEN` in `.env`) and prefill first/last names.
- **Data protection**: face enrollment requires recording the employee's biometric data consent.
- **Profiles with module permissions**: each profile selects (with checkboxes) which modules
  it can see — employees, attendance, reports, approvals, users, profiles, schedules,
  holidays, audit log and system settings.
- **Approval workflows**: vacation requests (reason required) and absence justifications with
  supporting documents; approvers see an in-app notification bell and receive email alerts.
- **Timezones**: the server runs and stores in UTC; the company operational timezone is set in
  Settings and each user can pick their own display timezone; UI available in Spanish and English.
- **Reports**: worked hours/days per period, printable formal attendance sheet, Excel export.
- **Audit log**: sensitive actions (deletions, manual attendance edits, user creation) with
  user, IP and before/after data, with server-side pagination.

## Documentation (docs/, in Spanish)

Full step-by-step guides live in the [`docs/`](docs/) folder — start there if you just
cloned the repository or are setting up a new server:

| Guide | Answers |
|---|---|
| [docs/INSTALACION.md](docs/INSTALACION.md) | "I downloaded the repo, now what?" — required stack, step-by-step install, default credentials, common errors |
| [docs/CONFIGURACION.md](docs/CONFIGURACION.md) | **Post-install checklist**: every `.env` variable (incl. `DECOLECTA_API_TOKEN`), in-app Settings, face models, cron, kiosk hardening, file permissions |
| [docs/CORREO.md](docs/CORREO.md) | SMTP setup (Gmail app passwords, Mailtrap, log driver), which emails the system sends, how to test |
| [docs/BASE_DE_DATOS.md](docs/BASE_DE_DATOS.md) | Switching between SQLite and MySQL/MariaDB, `DB_*` parameters, backups, useful artisan commands |
| [docs/REGLAS_DE_NEGOCIO.md](docs/REGLAS_DE_NEGOCIO.md) | **Developer reference**: every business rule (kiosk marking, lateness, overnight shifts, automatic absences, vacation balance, soft deletes, permissions, timezones) and where it lives in the code |

## Quick setup (short version)

```bash
git clone <repo> && cd tenkofiz
composer install
cp .env.example .env && php artisan key:generate
touch database/database.sqlite
php artisan migrate --seed
php artisan db:seed --class=DemoSeeder   # optional demo data
bash download_models.sh                  # face models → public/models (required by the kiosk)
php artisan serve
```

Default test users: `admin@test.com`, `aprobador@test.com`, `empleado@test.com` — all with password `123456` (change them immediately).
Then walk through [docs/CONFIGURACION.md](docs/CONFIGURACION.md) so nothing is left unconfigured.

## Scheduled tasks

`attendances:mark-absences` runs daily at 23:50 **company time** and marks ABSENT every
active employee without a record that day (skipping holidays, non-working days and approved
vacations). Requires `php artisan schedule:work` or a cron entry in production.

## Notes

- `APP_TIMEZONE` must stay `UTC` in production; the business timezone is configured inside
  the app (Settings → Company timezone).
- The UI language defaults to Spanish; users switch language from the navbar or My account.
