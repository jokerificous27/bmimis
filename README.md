# PNP Personnel BMI Monitoring and Health Information System

A role-based Laravel application for the PNP Health Service to record full BMI assessments, monthly weight monitoring, interventions and compliance — with official printable forms, Excel exports and an audit trail.

- **Stack:** Laravel 13 · PHP 8.5 · Livewire 4 + Flux UI (Livewire starter kit) · Fortify authentication · Tailwind CSS 4 · Chart.js 4 · MySQL (SQLite in tests) · Pest 5
- **Docs:** [User manual](docs/USER_MANUAL.md) · [System architecture](docs/ARCHITECTURE.md)

## Installation (local)

```bash
composer install
npm install
cp .env.example .env        # then set DB_* (MySQL database, e.g. bmi_db)
php artisan key:generate
php artisan migrate         # tables, reference data and fictitious demo data (all migrations, no seeders)
npm run build               # or `composer run dev` while developing
```

With Laravel Herd the app is served at `http://bmi.test`. Run the scheduler so monthly reminders are sent (`php artisan schedule:work` locally, a cron entry for `schedule:run` in production).

## Default local accounts

All demo passwords are `password`. Demo personnel are fictitious.

| Role | Email |
|---|---|
| Super Administrator | `admin@bmi.test` |
| Health Service Administrator | `hsadmin@bmi.test` |
| Health Service Encoder | `encoder@bmi.test` |
| Unit Representative (ITMS) | `unitrep@bmi.test` |
| PNP Personnel (linked to DEMO-00001) | `personnel@bmi.test` |
| PNP Personnel (linked to DEMO-00002, another unit) | `personnel2@bmi.test` |

The two default demo samples are **DEMO-00001 (PCpl DEMO, Daniel P.)** and **DEMO-00002 (Pat SAMPLE, Elena T.)**. Their weigh-in QR codes are fixed in the demo migration, so the demo weigh-in photos (scale reading 59.80 kg) identify them on every deployment; their history leaves the current month open so a demo photo saves straight away.

## Demo deployment (Laravel Cloud)

Everything the app needs is created by migrations, so the default deploy command `php artisan migrate --force` is enough: no seeders are run.

- `seed_reference_data` creates ranks, sample units and packages, the WHO and provisional PNP standards and the default permissions.
- `seed_demo_data` creates the demo accounts above and 48 fictitious personnel with two years of history (about a minute on first deploy). It runs unless `BMI_DEMO_DATA=false`.
- The demo accounts use the password `password`: anyone who finds the site can sign in. Use this for demonstrations only, with no real personnel data.
- Weigh-in and assessment photos are stored on the local disk; attach persistent object storage if they must survive redeploys.

## Production checklist

1. `APP_ENV=production`, `APP_DEBUG=false`, HTTPS, a strong `APP_KEY`.
2. `ALLOW_SELF_REGISTRATION=false` — accounts are created by administrators.
3. Set `BMI_DEMO_DATA=false` **before the first migration**, so no demo accounts are created. `php artisan migrate --force` then creates **reference data only**; run `php artisan bmi:create-admin` to create the first Super Administrator.
4. Encode the **official PNP Acceptable BMI thresholds** in *Administration → BMI Standards* (the seeded PNP standard is marked *provisional* and mirrors WHO cut-offs).
5. Encode the official **intervention package** descriptions (seeded packages A/B/C are placeholders).
6. Review *System Settings* (deadline day, compliance target, IBW method, validation ranges, letterhead).
7. Weigh-in Station: serve over HTTPS so phones can use the Smart camera (live weight and QR status before capture). QR codes and the scale's LED display are read on the phone by default (no API key). Optionally set `ANTHROPIC_API_KEY` and choose the *AI* weight reader in System Settings (after Data Privacy review) for harder photos.
8. Configure the scheduler (`bmi:send-reminders` runs daily at 07:00) and, optionally, mail (`MAIL_*`) before enabling email notifications.

## Useful commands

```bash
php artisan test --compact          # full test suite
vendor/bin/pint --format agent      # code style
php artisan bmi:send-reminders      # monthly / follow-up reminders (--force to send now)
php artisan bmi:create-admin        # create a Super Administrator
php artisan bmi:compact-qr-codes    # re-issue old long weigh-in QR codes in the compact, easier-to-scan format
```

## Notes on the reference forms

- Height is stored and validated in **centimetres** (the monitoring form labels 169 as "meters"); a value such as `1.69` typed into the assessment form is converted to `169.0 cm` automatically.
- The IBW shown on the reference BMI Form (52.8–71.4 kg at 169 cm) equals BMI 18.5–25.0 × height²; this is the default, configurable IBW method. Hamwi, Devine or manually encoded ranges are also supported.
- PDF export uses the browser's print dialog (**Print / Save as PDF**) from an A4 print preview, so no PDF library dependency was added.
