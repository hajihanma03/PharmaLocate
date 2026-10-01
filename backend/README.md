# PharmaLocate — Laravel backend

Laravel 13 + MySQL API for the PharmaLocate prototype. The single-page frontend is served from `public/` (copies of the root HTML/CSS/JS).

**Full documentation:** [../Documentation_v2.0.md](../Documentation_v2.0.md)

## Quick start

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

App URL: http://127.0.0.1:8000

## Demo accounts (password: `password`)

| Username | Role |
|----------|------|
| `admin` | admin |
| `sparx_staff` | staff (SpaRx) |
| `magic8_staff` | staff (Magic 8) |
| `maria` | customer |

## API endpoints

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/api/health` | – | Health check |
| POST | `/api/register` | – | Register customer |
| POST | `/api/login` | – | Login (username or email) |
| GET | `/api/pharmacies` | – | List; optional `lat`, `lng` |
| GET | `/api/medicines` | – | List; optional `search` |
| GET | `/api/availability` | – | Flat stock rows |
| GET | `/api/inquiries` | token | List inquiries |
| POST | `/api/inquiries` | token | Submit inquiry |
| PATCH | `/api/inquiries/{id}` | staff/admin | Reply |
| GET | `/api/admin/dashboard` | staff/admin | Dashboard stats |
| GET | `/api/admin/stock` | staff/admin | Stock list |
| PATCH | `/api/admin/stock/{pharmacy}/{medicine}` | staff/admin | Update stock |

## Data model

`users` (role), `pharmacies`, `medicines`, `pharmacy_medicine`, `geofences`, `geofence_pharmacy`, `inquiries`, `transactions`, `transaction_items`, `audit_logs`.

Priority user columns were removed in migration `2026_07_20_000001_remove_user_priority_types`.

## Not yet implemented

Pharmacy/geofence CRUD routes, Leaflet map wiring, Tile38, POS, user management UI, settings/backup.
