# PharmaLocate — Setup Guide

**Project:** Web-Based Pharmacy Inquiry System with Geofencing  
**Document:** setup_guide (living document)  
**Last updated:** July 2026 (Step 6)  
**Current milestone:** Step 7 in progress (testing documentation)  
**Active workspace:** `Programming4/` — do **not** run or test from `Programming3/` (frozen snapshot).

> **Purpose:** This guide helps team members install and run PharmaLocate on their own Windows laptop. It will be updated as development continues — check the **Document history** section at the bottom for the latest milestone.

**Related docs:**

| Document | Contents |
|----------|----------|
| `setup_guide.md` / **setup_guide.docx** | This file — install and test on a new laptop |
| `PharmaLocate_System_Guide.md` / **PharmaLocate_System_Guide.docx** | Non-technical system overview and defense Q&A |
| `Documentation_v1.0.md` / `.docx` | Steps 1–3 (frontend + backend integration) |
| `Documentation_v1.1.md` / `.docx` | Step 4 (admin core) |
| `Documentation_v1.3.md` / `.docx` | Step 7 — ISO/IEC 25010 test plan and cases |
| `Documentation_v2.0.md` | Consolidated overview (may lag behind v1.x series) |

---

## Table of contents

1. [What you are setting up](#1-what-you-are-setting-up)  
2. [Requirements](#2-requirements)  
3. [Get the project files](#3-get-the-project-files)  
3A. [Transferring to a new device (team handoff)](#3a-transferring-to-a-new-device-team-handoff)  
4. [Install XAMPP](#4-install-xampp)  
5. [Upgrade PHP to 8.3+ (required)](#5-upgrade-php-to-833-required)  
6. [Install Composer](#6-install-composer)  
7. [Enable PHP extensions](#7-enable-php-extensions)  
8. [Create the MySQL database](#8-create-the-mysql-database)  
9. [Configure Laravel (.env)](#9-configure-laravel-env)  
10. [Install backend dependencies](#10-install-backend-dependencies)  
11. [Run migrations and seed data](#11-run-migrations-and-seed-data)  
12. [Sync frontend files](#12-sync-frontend-files)  
13. [Start the application (every session)](#13-start-the-application-every-session)  
14. [Verify your setup (checklist)](#14-verify-your-setup-checklist)  
14A. [Step 6 testing walkthrough](#14a-step-6-testing-walkthrough)  
15. [Frontend development workflow](#15-frontend-development-workflow)  
16. [Demo accounts](#16-demo-accounts)  
17. [Tile38 (Sprint 5.6)](#17-tile38-sprint-56)  
18. [Troubleshooting](#18-troubleshooting)  
19. [What's next in development](#19-whats-next-in-development)  
20. [Document history](#20-document-history)

---

## 1. What you are setting up

PharmaLocate has two modes:

| Mode | How to open | Needs server? |
|------|-------------|---------------|
| **Demo** | Double-click `PharmaLocateFrontEnd.html` | No — static data only |
| **Live** | http://127.0.0.1:8000 via `php artisan serve` | Yes — MySQL + Laravel |

For capstone development and testing, use **live mode**.

**Stack:**

- Frontend: HTML + CSS + JavaScript (3 files at repo root)  
- Backend: Laravel 13 + MySQL  
- Map: Leaflet 1.9.4 + OpenStreetMap tiles (CDN — needs internet)  
- Auth: Laravel Sanctum (Bearer token in `localStorage` as `ph_token`)

---

## 2. Requirements

| Tool | Version | Purpose |
|------|---------|---------|
| Windows 10/11 | — | Development OS used by team |
| XAMPP | 8.2.12+ | MySQL, phpMyAdmin |
| PHP | **8.3 or higher** (8.5.x tested) | Laravel 13 requires PHP ^8.3 |
| Composer | 2.x | PHP package manager |
| Browser | Chrome or Edge | Testing UI |
| Internet | — | OSM map tiles, CDN (Leaflet, Tabler icons) |

> **Important:** Stock XAMPP PHP is often 8.2.x. You **must** upgrade `C:\xampp\php\` to PHP 8.3+ before `composer install` will succeed with Laravel 13.

---

## 3. Get the project files

Obtain the project from your team lead (USB, shared drive, Git, or zip).

Expected layout:

```
PharmaLocate/
├── Programming2/          ← Frozen v1.0 snapshot (reference only)
├── Programming3/          ← Frozen Step 5 snapshot (reference only)
└── Programming4/          ← Active development — use this folder
    ├── setup_guide.md     ← This file
    ├── Documentation_v1.2.md
    ├── PharmaLocateFrontEnd.html
    ├── styles.css
    ├── app.js
    └── backend/           ← Laravel application
        ├── .env.example
        ├── app/
        ├── routes/
        ├── database/
        └── public/        ← Served copies of frontend
```

**Rule:** Do all daily work in `Programming4/`. Do not edit `Programming2/` or `Programming3/` unless explicitly asked.

Adjust paths below if your clone lives elsewhere — replace:

`D:\ADMIN\Documents\PharmaLocate\Programming4`

with your actual path.

---

## 3A. Transferring to a new device (team handoff)

Use this section when copying the project to another team member’s laptop via USB, shared drive, zip, or Git.

### What to copy

Copy the entire **`Programming4/`** folder. Minimum contents:

```
Programming4/
├── PharmaLocateFrontEnd.html
├── styles.css
├── app.js
├── setup_guide.md / setup_guide.docx
├── PharmaLocate_System_Guide.docx   ← optional but recommended for non-technical members
└── backend/
    ├── app/
    ├── config/
    ├── database/
    ├── public/          ← should already contain synced HTML/CSS/JS
    ├── routes/
    ├── .env.example
    └── vendor/          ← include if present (avoids re-downloading packages)
```

### What NOT to copy (create fresh on the new device)

| Item | Why |
|------|-----|
| **`backend/.env`** | Contains machine-specific keys and DB settings — recreate from `.env.example` |
| **MySQL data** | Lives in XAMPP, not in the project folder — run `migrate --seed` on the new PC |
| **`Programming3/`** | Frozen old snapshot — not needed for testing Step 6 |
| **`node_modules/`** | Not used by this project |

### New device — fast setup (after XAMPP + PHP 8.3+ + Composer)

Run these in order (adjust the path if your folder is not on `D:`):

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
copy .env.example .env
notepad .env
```

Set `DB_DATABASE=pharmalocate`, `DB_USERNAME=root`, `DB_PASSWORD=` (or your MySQL password).

Then:

```powershell
composer install
php artisan key:generate
php artisan migrate --seed
Copy-Item -Force ..\PharmaLocateFrontEnd.html public\
Copy-Item -Force ..\styles.css public\
Copy-Item -Force ..\app.js public\
php artisan serve
```

Open **http://127.0.0.1:8000** → login **`admin`** / **`password`**.

> **If `vendor/` was copied:** `composer install` still runs quickly and verifies dependencies.

### Updating an existing copy (team pull / new zip)

If the laptop already had an older PharmaLocate install:

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
php artisan migrate
Copy-Item -Force ..\PharmaLocateFrontEnd.html public\
Copy-Item -Force ..\styles.css public\
Copy-Item -Force ..\app.js public\
php artisan serve
```

- **`php artisan migrate`** — applies new tables (e.g. Step 6 `settings`) **without** wiping data.  
- **`php artisan migrate:fresh --seed`** — full reset; use only when you want a clean demo database.

### Critical: run the server from the correct folder

| Folder | Use for daily work? |
|--------|---------------------|
| **`Programming4/backend`** | **Yes** — current system (Steps 1–6) |
| `Programming3/backend` | **No** — frozen Step 5 snapshot only |

If `php artisan serve` was started from `Programming3`, stop it (**Ctrl + C**), `cd` to `Programming4\backend`, and start again.

### Optional: Tile38 on a new device

Tile38 is **optional**. The app works without it (haversine fallback).

1. Download Tile38 for Windows from [https://tile38.com/](https://tile38.com/)  
2. Run `tile38-server.exe`  
3. In `backend/.env`: `TILE38_ENABLED=true`  
4. Run `php artisan tile38:sync`

If you skip Tile38, leave `TILE38_ENABLED=false` in `.env`.

---

## 4. Install XAMPP

1. Download XAMPP from [https://www.apachefriends.org/](https://www.apachefriends.org/)  
2. Install to default location (`C:\xampp`)  
3. Open **XAMPP Control Panel**  
4. Click **Start** on **MySQL** (Apache optional unless using phpMyAdmin)

Verify phpMyAdmin: http://localhost/phpmyadmin

---

## 5. Upgrade PHP to 8.3+ (required)

Laravel 13 will not run on PHP 8.2.

1. Download **VS17 x64 Thread Safe ZIP** from [https://windows.php.net/download/](https://windows.php.net/download/)  
2. **Back up** `C:\xampp\php\` (rename to `php_backup`)  
3. Extract the new PHP ZIP **into** `C:\xampp\php\` (not directly into `C:\xampp\`)  
4. Copy `php.ini-development` → `php.ini` if no `php.ini` exists  
5. Verify:

```powershell
C:\xampp\php\php.exe -v
```

Expected: `PHP 8.3.x` or higher.

**Correct layout:**

```
C:\xampp\php\php.exe
C:\xampp\php\ext\
C:\xampp\php\php.ini
```

---

## 6. Install Composer

1. Download Composer-Setup from [https://getcomposer.org/download/](https://getcomposer.org/download/)  
2. When prompted for PHP path, select `C:\xampp\php\php.exe`  
3. Allow installer to add Composer to PATH  
4. Verify:

```powershell
composer --version
php --version
```

If `php` is not found in a new terminal, add `C:\xampp\php` to your system **PATH** environment variable.

---

## 7. Enable PHP extensions

Edit `C:\xampp\php\php.ini`. Remove the leading `;` from these lines:

```ini
extension=curl
extension=fileinfo
extension=mbstring
extension=openssl
extension=pdo_mysql
extension=zip
```

| Extension | Why |
|-----------|-----|
| openssl | Composer HTTPS downloads |
| zip | Composer package extraction |
| pdo_mysql | Laravel database |
| mbstring, fileinfo, curl | Laravel framework |

Restart any open terminals after saving `php.ini`.

---

## 8. Create the MySQL database

1. Start MySQL in XAMPP  
2. Open http://localhost/phpmyadmin  
3. Run:

```sql
CREATE DATABASE pharmalocate CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Default XAMPP credentials: user `root`, empty password.

---

## 9. Configure Laravel (.env)

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
copy .env.example .env
notepad .env
```

Set database section (adjust if your MySQL differs):

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=pharmalocate
DB_USERNAME=root
DB_PASSWORD=
```

Save and close.

---

## 10. Install backend dependencies

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
composer install
php artisan key:generate
```

If `composer install` fails:

- Confirm PHP ≥ 8.3 (`php -v`)  
- Confirm extensions in Section 7 are enabled  
- Confirm MySQL is not required for `composer install` (only for migrate)

---

## 11. Run migrations and seed data

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
php artisan migrate --seed
```

This creates tables and demo data:

| Data | Details |
|------|---------|
| Pharmacies | SpaRx (15.489, 120.598), Magic 8 (15.482, 120.5905) |
| Geofence | Tarlac Provincial Hospital Zone, 5 km radius, both pharmacies assigned |
| Users | admin, sparx_staff, magic8_staff, maria |
| Medicines | 10 sample medicines with stock |
| Inquiry | One pending inquiry from maria |
| Settings (Step 6) | Low-stock threshold **10**, notification toggles, backup schedule defaults |
| Sample POS sale (Step 6) | One SpaRx transaction (2× Paracetamol) — dashboard **Sales today** may show a non-zero value after seed |

**Step 6 database tables:** `transactions`, `transaction_items`, `settings`, `audit_logs` (audit logs fill as you use POS, settings, and export).

To reset everything (destroys data):

```powershell
php artisan migrate:fresh --seed
```

---

## 12. Sync frontend files

Laravel serves the UI from `backend/public/`. After cloning or before first run:

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
Copy-Item -Force ..\PharmaLocateFrontEnd.html public\
Copy-Item -Force ..\styles.css public\
Copy-Item -Force ..\app.js public\
```

**Always repeat this after editing** root HTML, CSS, or JS.

---

## 13. Start the application (every session)

1. XAMPP Control Panel → **Start MySQL**  
2. Open PowerShell:

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
php artisan serve
```

3. Open **http://127.0.0.1:8000**  
4. Stop server with **Ctrl + C** when done  

> Use `127.0.0.1:8000`, not `file://` — live API and map require HTTP.

---

## 14. Verify your setup (checklist)

Run through this after first install and after pulling team updates.

### Basic

- [ ] http://127.0.0.1:8000 loads the PharmaLocate UI  
- [ ] Guest → Medicines tab shows live medicine list  
- [ ] Login `maria` / `password` → Inquiries tab works  

### Admin (Step 4)

- [ ] Login `admin` / `password` → Admin view opens automatically  
- [ ] Dashboard shows live stats (inquiries, stock alerts)  
- [ ] Inquiry reply and stock update work  

### Geofencing (Step 5)

- [ ] Guest/User → **Pharmacies** tab shows Leaflet map (not blank/grey)  
- [ ] Notice: "Inside Tarlac Provincial Hospital Zone (5 km) — showing 2 assigned pharmacies"  
- [ ] SpaRx ~0.31 km, Magic 8 ~0.81 km in list  
- [ ] Map shows green user dot, geofence circle, pharmacy pins  
- [ ] No large blue/yellow rectangle on map (see Troubleshooting)  
- [ ] Admin → **Pharmacies** → can add/edit pharmacy (admin) or edit own (staff)  
- [ ] API test: http://127.0.0.1:8000/api/geofences returns JSON zone data  

### POS and extras (Step 6)

- [ ] Admin → **POS** → products load from live stock; process sale decrements inventory  
- [ ] Dashboard **Sales today** updates after a POS sale  
- [ ] Admin → **Users** → list loads; can change role / pharmacy assignment  
- [ ] Admin → **Settings** → save low-stock threshold and notification toggles  
- [ ] Admin → **Backup** → download JSON or CSV export  
- [ ] Staff → POS works for assigned pharmacy only; Users/Settings/Export show admin-only notice  

### API health

- [ ] http://127.0.0.1:8000/api/health returns `{"status":"ok",...}`

---

## 14A. Step 6 testing walkthrough

After Section 14, run these manual tests on **http://127.0.0.1:8000** (live mode only).

### POS (admin)

1. Login **`admin`** / **`password`**  
2. Sidebar → **POS**  
3. Select **SpaRx Pharmacy** (admin pharmacy dropdown)  
4. Click a product with stock → **Process sale**  
5. Confirm: success message, **Recent sales** updates, **Stock** quantity drops, **Dashboard → Sales today** increases  

### POS (staff)

1. Logout → login **`sparx_staff`** / **`password`**  
2. **POS** — no pharmacy dropdown; SpaRx products only  
3. Process a sale → stock updates for SpaRx only  

### User management (admin only)

1. Login **`admin`**  
2. Sidebar → **Users** → table loads  
3. **Edit** a user → change role or staff pharmacy → **Save**  

Staff (`sparx_staff`) should see an admin-only notice on **Users**.

### Settings (admin only)

1. Sidebar → **Settings**  
2. Change **Low stock threshold** (default **10** after seed) → **Save settings**  
3. Reload page — values should persist  

**How threshold affects Stock status:**

| Stock qty | Threshold = 10 | Threshold = 5 |
|-----------|----------------|---------------|
| 0 | Out of stock | Out of stock |
| 1–9 | Low stock | Low stock (1–4 only) |
| 5 | Low stock | **In stock** |
| 10+ | In stock | In stock |

Status recalculates when you save a stock row or complete a POS sale. Old labels may remain until one of those actions runs.

### Backup & export (admin only)

1. Sidebar → **Backup**  
2. Scope: **Medicine inventory only** → Format: **CSV** → **Download export**  
3. Repeat with **Full system snapshot** + **JSON**  

---

## 15. Frontend development workflow

**Design rule:** Edit only these files at `Programming4/` root:

| File | Contents |
|------|----------|
| `PharmaLocateFrontEnd.html` | All UI markup |
| `styles.css` | Styles and layout |
| `app.js` | Demo data, API calls, map logic |

Then sync to `backend/public/` (Section 12) and hard-refresh browser (**Ctrl+F5**).

### Live vs demo detection

```javascript
const API_BASE = location.protocol.startsWith('http') ? '/api' : null;
```

- `file://` → demo mode (static `DEMO_MEDICINES`)  
- `http://127.0.0.1:8000` → live mode (MySQL API)

### Auth token

After login, token is stored as:

```javascript
localStorage.getItem('ph_token')
```

Use this key when testing admin APIs in DevTools (not `pharmalocate_token`).

---

## 16. Demo accounts

All passwords: **`password`**

| Username | Role | Notes |
|----------|------|-------|
| `admin` | admin | Full access: POS (all pharmacies), Users, Settings, Backup |
| `sparx_staff` | staff | SpaRx only — POS + stock; read-only geofences |
| `magic8_staff` | staff | Magic 8 only |
| `maria` | customer | Sample customer with one inquiry |

Login accepts **username or email**.

---

## 17. Tile38 (Sprint 5.6)

Tile38 is an optional geospatial engine. When enabled, Laravel uses **Tile38 NEARBY** for zone and pharmacy distance queries. If Tile38 is off or unreachable, the app **automatically falls back** to PHP haversine (Sprints 5.3–5.4 behavior).

### Install and run Tile38 (Windows)

1. Download from [https://tile38.com/](https://tile38.com/) (already at `C:\tile38\tile38-1.38.0-windows-amd64\` on dev machine)
2. Run **`tile38-server.exe`** (keep window open, or minimize)
3. Allow Windows Firewall for private networks if prompted
4. Test: `tile38-cli.exe PING` → should return `"ping":"pong"`

### Enable in Laravel

Edit `backend/.env`:

```env
TILE38_ENABLED=true
TILE38_HOST=127.0.0.1
TILE38_PORT=9851
```

### Sync MySQL data into Tile38

After MySQL seed or after changing pharmacies/geofences in admin:

```powershell
cd D:\ADMIN\Documents\PharmaLocate\Programming4\backend
php artisan tile38:sync
```

Expected output:

```
Tile38 sync complete.
  Geofences: 1
  Pharmacies: 2
```

Admin create/edit/deactivate for pharmacies and geofences also syncs automatically when Tile38 is running.

### Verify Tile38 is active

1. http://127.0.0.1:8000/api/health → `"tile38":"ok"`
2. If server is down → `"tile38":"unavailable"` (app still works via haversine)
3. If disabled in `.env` → `"tile38":"disabled"`

### Daily dev with Tile38

```
[ ] Start MySQL (XAMPP)
[ ] Start tile38-server.exe
[ ] php artisan serve
[ ] (After DB changes) php artisan tile38:sync
```

You can leave Tile38 **off** (`TILE38_ENABLED=false`) during normal UI work — the app behaves exactly as Sprint 5.4.

---

## 18. Troubleshooting

| Problem | Solution |
|---------|----------|
| `vendor/autoload.php` missing | Run `composer install` in `backend/` |
| Laravel requires PHP ^8.3 | Upgrade XAMPP PHP (Section 5) |
| `could not find driver` | Enable `pdo_mysql` in php.ini |
| Composer SSL / openssl errors | Enable `extension=openssl` |
| `Access denied for user` | Check `.env` DB_USERNAME / DB_PASSWORD |
| UI changes not visible | Sync files to `backend/public/`; Ctrl+F5 |
| Admin API returns 401 | Log in first; use `ph_token` from localStorage |
| Map grey / no tiles | Check internet; OSM CDN must be reachable |
| Blue/yellow block on map | Update `styles.css` (Leaflet attribution fix); sync + Ctrl+F5 |
| 0 pharmacies on Pharmacies tab | Default location should show 2; if testing far coords, empty is correct |
| `migrate` fails | Start MySQL in XAMPP |
| Port 8000 in use | Run `php artisan serve --port=8001` |
| SmartScreen blocks Tile38 | Click "More info" → "Run anyway" or Unblock in file Properties |
| **POS / Users / Settings fail to load** | Confirm server runs from **`Programming4/backend`**, not Programming3 |
| **Could not load POS** (404) | Run `php artisan migrate` — Step 6 routes need current code + DB |
| **Settings won’t save** | Must be logged in as **admin** (not staff) |
| **Stock status seems wrong** | Check **Settings → Low stock threshold**; edit stock row or run a POS sale to recalculate |
| **`Nothing to migrate` but Step 6 broken** | Migrations already applied; sync frontend (Section 12) and restart `php artisan serve` from Programming4 |

### DevTools paste blocked

In Chrome/Edge console, type `allow pasting` first, then paste test scripts.

### Reset database

```powershell
php artisan migrate:fresh --seed
```

---

## 19. What's next in development

**Step 6 complete.** Next:

| Sprint / Step | Task |
|---------------|------|
| **Step 7** | ISO/IEC 25010 testing documentation |

See `Documentation_v1.2.md` Section 34 for full roadmap.

**After each sprint:** Update this setup guide's verify checklist and document history.

---

## 20. Document history

| Date | Milestone | Updates to this guide |
|------|-----------|------------------------|
| July 2026 | Initial | Full Windows setup from Steps 1–3 (based on v1.0 procedures) |
| July 2026 | Step 4 complete | Admin verify checklist, demo accounts |
| July 2026 | Step 5.0–5.4 | Leaflet map verify, geofence API, pharmacy CRUD, map CSS fix, Tile38 note |
| July 2026 | Step 5.5–5.6 | Admin geofence UI, staff read-only, Tile38 integration |
| July 2026 | Workspace → Programming4 | Active folder moved from Programming3; Step 5 frozen in Programming3 |
| July 2026 | Step 6 complete | POS, users, settings, export; Section 3A transfer guide; Step 6 test walkthrough |
| August 2026 | Step 7 started | Documentation_v1.3 — ISO/IEC 25010 test plan (80 cases) |
| — | Step 7 execution | *Pending — run tests, screenshots, fill summary* |

---

*Living document — update Section 14 and Section 20 after each development sprint.*
