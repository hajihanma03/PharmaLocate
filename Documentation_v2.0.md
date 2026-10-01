# PharmaLocate — Documentation v2.0

**Project:** Web-Based Pharmacy Inquiry System with Geofencing  
**Version:** 2.0  
**Date:** July 2026  
**Status:** Steps 1–4 complete (customer + admin core)  
**Location:** `D:\ADMIN\Documents\PharmaLocate\Programming4\`

**Frozen snapshot (v1.0):** `Programming2/` — do not edit unless explicitly requested.

---

## Table of contents

1. [Project overview](#1-project-overview)
2. [Project structure](#2-project-structure)
3. [Development timeline](#3-development-timeline)
4. [How to run](#4-how-to-run)
5. [Demo accounts](#5-demo-accounts)
6. [API reference](#6-api-reference)
7. [Current capabilities](#7-current-capabilities)
8. [Known limits (prototype areas)](#8-known-limits-prototype-areas)
9. [Roadmap — Step 5 and beyond](#9-roadmap--step-5-and-beyond)
10. [Troubleshooting](#10-troubleshooting)

---

## 1. Project overview

PharmaLocate is a capstone project: a pharmacy inquiry system where customers browse medicine availability, locate nearby pharmacies, and submit inquiries. Staff and admins manage stock and reply to inquiries.

The UI is a **3-file frontend** (HTML + CSS + JS) served either as a static demo or through **Laravel 13 + MySQL**.

### Two operating modes

| Mode | How to open | Data source |
|------|-------------|-------------|
| **Demo** | Double-click `PharmaLocateFrontEnd.html` | Static `DEMO_MEDICINES` in `app.js` |
| **Live** | `php artisan serve` → http://127.0.0.1:8000 | MySQL via `/api/*` |

### Change from v1.0

**Priority user types removed** (July 2026): `users.priority_type` and `inquiries.is_priority` were dropped. Signup no longer asks for PWD/Senior/Pregnant/Parent. Inquiries are ordered by date, not priority. Roles (`admin`, `staff`, `customer`) remain.

---

## 2. Project structure

```
Programming4/
├── Documentation_v2.0.md      ← This file (single source of truth)
├── README.md                  ← Quick start pointer
├── PharmaLocateFrontEnd.html  ← All UI (Guest, User, Auth, Admin)
├── styles.css                 ← Design source styles
├── app.js                     ← Demo + live API logic
└── backend/                   ← Laravel 13 + MySQL
    ├── app/Http/Controllers/
    ├── routes/api.php
    ├── database/migrations/
    ├── database/seeders/
    └── public/                ← Served copies of frontend (sync after edits)
        ├── PharmaLocateFrontEnd.html
        ├── styles.css
        └── app.js
```

**Design rule:** Edit root HTML/CSS/JS, then copy to `backend/public/` before testing via Laravel.

---

## 3. Development timeline

| Step | Scope | Status |
|------|-------|--------|
| **1** | Frontend lock (4 views, 10 medicines, UX fixes) | Done |
| **2** | Backend map (screen → API plan) | Done |
| **3** | P1 integration (auth, medicines, pharmacies, inquiries) | Done |
| **4** | P2 admin core (dashboard, inquiry reply, stock updates) | Done |
| **5** | P3 geofencing (pharmacy/geofence CRUD, Leaflet map) | **Next** |
| **6** | P4 POS, user mgmt, settings, backup | Planned |
| **7** | Testing docs (ISO/IEC 25010) | Planned |

### Step 4 deliverables (complete)

- `EnsureStaffOrAdmin` middleware — staff scoped to their pharmacy
- `GET /api/admin/dashboard` — stats for admin dashboard UI
- `GET /api/admin/stock`, `PATCH /api/admin/stock/{pharmacy}/{medicine}` — stock management
- Admin login opens Admin view; staff see only their pharmacy’s data
- Inquiry reply wired in admin UI (`PATCH /api/inquiries/{id}`)

---

## 4. How to run

**Requirements:** PHP 8.3+ (tested with 8.5.8), Composer, MySQL (XAMPP), Node not required.

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
composer install
cp .env.example .env   # or copy manually on Windows
php artisan key:generate
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000

**After editing frontend at repo root:**

```powershell
Copy-Item -Force ..\PharmaLocateFrontEnd.html public\
Copy-Item -Force ..\styles.css public\
Copy-Item -Force ..\app.js public\
```

---

## 5. Demo accounts

All passwords: **`password`**

| Username | Role | Notes |
|----------|------|-------|
| `admin` | admin | Full access |
| `sparx_staff` | staff | SpaRx pharmacy only |
| `magic8_staff` | staff | Magic 8 pharmacy only |
| `maria` | customer | Sample customer |
| `Test_User1`, `Test_User2` | customer | Registered via signup |

Login accepts **username or email**.

---

## 6. API reference

| Method | Path | Auth | Purpose |
|--------|------|------|---------|
| GET | `/api/health` | – | Health check |
| POST | `/api/register` | – | Register customer |
| POST | `/api/login` | – | Login → Sanctum token |
| GET | `/api/pharmacies` | – | List; `?lat=&lng=` sorts nearest |
| GET | `/api/pharmacies/{id}` | – | Pharmacy + stock |
| GET | `/api/medicines` | – | List; `?search=` filter |
| GET | `/api/medicines/{id}` | – | Medicine + pharmacies |
| GET | `/api/availability` | – | Flat availability rows |
| GET | `/api/me` | token | Current user |
| POST | `/api/logout` | token | Revoke token |
| GET | `/api/inquiries` | token | Own (customer) or all (staff/admin) |
| POST | `/api/inquiries` | token | Submit inquiry |
| PATCH | `/api/inquiries/{id}` | staff/admin | Reply + update status |
| GET | `/api/admin/dashboard` | staff/admin | Dashboard stats |
| GET | `/api/admin/stock` | staff/admin | Stock list (scoped for staff) |
| PATCH | `/api/admin/stock/{pharmacy}/{medicine}` | staff/admin | Update stock/availability |

Send token as `Authorization: Bearer <token>`.

---

## 7. Current capabilities

### Customer (live mode)

- Browse medicines and availability
- View pharmacies (distance sort when lat/lng provided)
- Register, login, logout
- Submit and view own inquiries

### Admin / staff (live mode)

- Dashboard stats (inquiries, stock alerts, etc.)
- Reply to inquiries, change status
- View and update stock (staff limited to assigned pharmacy)

### Demo mode

- Full UI navigation without backend
- Static medicine/pharmacy data only

---

## 8. Known limits (prototype areas)

These admin sections exist in the UI but are **not yet wired** to the API:

- POS transactions
- Pharmacy CRUD (`#admin-pharmacies`)
- Geofence CRUD (`#admin-geofences`)
- User management, settings, backup/export
- Customer Pharmacies tab uses decorative SVG, not live Leaflet map
- `GeofenceController` exists but is not registered in `routes/api.php` yet
- Tile38 integration not implemented (haversine distance sort only)

---

## 9. Roadmap — Step 5 and beyond

### Step 5 — Priority 3: Geofencing (next)

**Goal:** Real map + geofence management aligned with Chapter 1 geofence objectives.

| Task | Details |
|------|---------|
| **5.1 Pharmacy CRUD API** | `GET/POST/PATCH/DELETE /api/admin/pharmacies` — wire `#admin-pharmacies` table and forms |
| **5.2 Geofence CRUD API** | Register `GeofenceController`; CRUD zones + assign pharmacies via `geofence_pharmacy` |
| **5.3 Leaflet map (customer)** | Replace SVG on Pharmacies tab; show pharmacy markers from API |
| **5.4 Geofence filtering** | When user location is known, filter/sort pharmacies inside active zone radius |
| **5.5 Admin geofence UI** | Wire `#admin-geofences` to create/edit/delete zones and link pharmacies |
| **5.6 (Optional) Tile38** | If required by capstone paper (DC2): replace haversine with Tile38 `NEARBY` / geofence queries |

**Suggested order:** 5.1 → 5.2 → 5.3 → 5.4 → 5.5 → 5.6

### Step 6 — Priority 4: POS and extras

- POS: record sales, decrement stock (`transactions`, `transaction_items`)
- User management: list users, assign roles/pharmacy
- Settings persistence, backup/export stubs or real endpoints

### Step 7 — Testing documentation

- ISO/IEC 25010 test cases
- Screenshots and evidence for capstone submission

---

## 10. Troubleshooting

| Issue | Fix |
|-------|-----|
| `vendor/autoload.php` missing | Run `composer install` in `backend/` |
| PHP extension errors | Enable `openssl`, `zip`, `curl`, `fileinfo`, `mbstring`, `pdo_mysql` in `php.ini` |
| Laravel version error | Use PHP 8.3+ |
| UI changes not visible on serve | Copy root HTML/CSS/JS to `backend/public/` |
| Forgot password | Re-seed: `php artisan migrate:fresh --seed` (destroys data) or reset in DB |
| Staff sees wrong pharmacy | Check `users.pharmacy_id` in seeder/DB |

---

*Supersedes Documentation_v1.0.md (Programming2 snapshot).*
