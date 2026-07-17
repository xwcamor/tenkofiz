# Attendance Control System with Facial Recognition

Laravel application for employee attendance control using facial recognition in the browser
(face-api.js) through a kiosk screen (tablet at the entrance), with vacation and justification
workflows, reports and a full audit log.

## Features

- **Facial marking kiosk**: check-in / check-out with face matching done in the browser
  (only a 128-value mathematical descriptor is stored, never the photograph).
- **Kiosk device restriction**: an access token can be generated in Settings so only the
  authorized tablet can open the kiosk; every mark also records the device IP and user agent.
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

## Setup

```bash
composer run setup      # install, .env, key, migrate, npm build
php artisan db:seed     # base data (profiles, admin user, schedules, holidays)
php artisan db:seed --class=DemoSeeder   # optional demo data
php artisan serve
```

Default admin: `admin@sistema.test` / `admin123` (change it immediately).

Face models must be present in `public/models` (see `download_models.sh`).

## Scheduled tasks

`attendances:mark-absences` runs daily at 23:50 **company time** and marks ABSENT every
active employee without a record that day (skipping holidays, non-working days and approved
vacations). Requires `php artisan schedule:work` or a cron entry in production.

## Notes

- `APP_TIMEZONE` must stay `UTC` in production; the business timezone is configured inside
  the app (Settings → Company timezone).
- The UI language defaults to Spanish; users switch language from the navbar or My account.
