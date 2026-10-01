# PharmaLocate — Documentation v1.1

**Project:** Web-Based Pharmacy Inquiry System with Geofencing  
**Version:** 1.1 (continuation of v1.0)  
**Date:** July 2026  
**Status:** Steps 1–4 complete  

**Prerequisite:** Read [Documentation_v1.0.md](../Programming2/Documentation_v1.0.md) first. That document covers Steps 1–3, project structure, setup, frontend/backend architecture, and the original API reference. **This file documents everything added after v1.0.**

**Frozen snapshot:** `Programming2/` remains the v1.0 baseline. Active development continues in `Programming3/`.

---

## Table of contents

15. [Workspace change (Programming2 → Programming3)](#15-workspace-change-programming2--programming3)  
16. [Step 4 — Admin core (Priority 2)](#16-step-4--admin-core-priority-2)  
17. [Design change — Priority user types removed](#17-design-change--priority-user-types-removed)  
18. [File cleanup](#18-file-cleanup)  
19. [Updated API reference (admin endpoints)](#19-updated-api-reference-admin-endpoints)  
20. [Updated system capabilities](#20-updated-system-capabilities)  
21. [Updated known limits](#21-updated-known-limits)  
22. [Roadmap (Step 5 and beyond)](#22-roadmap-step-5-and-beyond)  
23. [Troubleshooting (additions)](#23-troubleshooting-additions)

---

## 15. Workspace change (Programming2 → Programming3)

After Step 3 was documented in v1.0, the project was duplicated for Step 4 work:

| Folder | Role |
|--------|------|
| `Programming2/` | Frozen v1.0 snapshot (Steps 1–3 only) |
| `Programming3/` | Active development (Steps 4+) |

The folder layout is unchanged from v1.0:

```
Programming3/
├── Documentation_v1.1.md      ← This file
├── README.md
├── PharmaLocateFrontEnd.html
├── styles.css
├── app.js
└── backend/
    └── public/                ← Sync copies after editing root files
```

**Design rule (unchanged):** Edit HTML/CSS/JS at the `Programming3/` root, then copy to `backend/public/` before testing via `php artisan serve`.

---

## 16. Step 4 — Admin core (Priority 2)

**Goal (from v1.0 roadmap):** Connect the admin UI to live backend data — dashboard stats, inquiry replies, and stock management.

**Status:** Complete.

### 16.1 Deliverables checklist

| # | v1.0 roadmap item | Status | Implementation |
|---|-------------------|--------|----------------|
| 1 | Admin login gate (admin/staff only) | Done | Login as `admin` or `*_staff` opens Admin view automatically |
| 2 | Wire `#admin-inq-mgmt` to live inquiries + reply | Done | `GET /api/inquiries`, `PATCH /api/inquiries/{id}` |
| 3 | Wire `#admin-dashboard` to stats API | Done | `GET /api/admin/dashboard` |
| 4 | Wire `#admin-stock` to read/update stock | Done | `GET /api/admin/stock`, `PATCH /api/admin/stock/{pharmacy}/{medicine}` |

### 16.2 Backend files added or changed

| File | Purpose |
|------|---------|
| `app/Http/Middleware/EnsureStaffOrAdmin.php` | Returns 403 unless `role` is `admin` or `staff` |
| `app/Http/Controllers/AdminDashboardController.php` | Aggregates dashboard stats |
| `app/Http/Controllers/StockController.php` | Lists and updates `pharmacy_medicine` rows |
| `routes/api.php` | Admin route group under `/api/admin/*` |

**Route registration (`routes/api.php`):**

```php
Route::middleware(EnsureStaffOrAdmin::class)->prefix('admin')->group(function () {
    Route::get('/dashboard', [AdminDashboardController::class, 'index']);
    Route::get('/stock', [StockController::class, 'index']);
    Route::patch('/stock/{pharmacy}/{medicine}', [StockController::class, 'update']);
});
```

All admin routes require `auth:sanctum` (inherited from the parent middleware group).

### 16.3 Staff scoping

Staff users are limited to their assigned pharmacy (`users.pharmacy_id`):

- **Dashboard** — counts, inquiries, stock alerts, and sales scoped to that pharmacy
- **Inquiries** — only inquiries for that pharmacy (via existing `InquiryController`)
- **Stock** — list and update restricted to their pharmacy; admin sees all pharmacies

Admin (`role = admin`) has no pharmacy filter and sees system-wide data.

### 16.4 Admin dashboard API response

`GET /api/admin/dashboard` returns:

| Field | Description |
|-------|-------------|
| `pharmacies_count` | Active pharmacies (1 for staff) |
| `inquiries_today` | Inquiries created today |
| `pending_inquiries` | Inquiries with `status = pending` |
| `medicines_tracked` | Distinct medicines in stock |
| `low_stock_count` | Rows with `low` or `out_of_stock` status |
| `sales_today` | Sum of `transactions.total_amount` for today |
| `geofences.active` | Total geofence records |
| `geofences.assigned_pharmacies` | Pharmacies linked in `geofence_pharmacy` |
| `users.registered` | Customer account count |
| `recent_activity` | Last 5 inquiries (pending/replied) |
| `low_stock_alerts` | Top 3 low/out-of-stock items |

### 16.5 Stock update logic

`PATCH /api/admin/stock/{pharmacy}/{medicine}` accepts:

```json
{
  "stock_quantity": 25,
  "price": 12.50,
  "availability_status": "available"
}
```

- `stock_quantity` is required (integer ≥ 0)
- If `availability_status` is omitted, it is derived automatically:
  - `0` → `out_of_stock`
  - `1–9` → `low`
  - `10+` → `available`

### 16.6 Frontend wiring (`app.js`)

New section: **Step 4: Admin (live API)**

| Function | Behavior |
|----------|----------|
| `isStaffOrAdmin()` | True when logged-in user has `admin` or `staff` role |
| `loginUser()` | Redirects admin/staff to Admin view after login |
| `loadAdminData()` | Loads dashboard + inquiries when Admin view opens |
| `loadAdminDashboard()` | Fills stat cards and activity feed from `/admin/dashboard` |
| `loadAdminInquiries()` | Populates inquiry management table |
| `openAdminReply(id)` | Opens reply panel for a selected inquiry |
| `submitAdminReply()` | Sends `PATCH /api/inquiries/{id}` with response text |
| `loadAdminStock()` | Loads stock table from `/admin/stock` |
| `saveStockRow(...)` | Sends stock patch per row |

Admin navbar shows an **Admin view** button when logged in as admin or staff.

### 16.7 Demo accounts (unchanged password: `password`)

| Username | Role | Pharmacy scope |
|----------|------|----------------|
| `admin` | admin | All pharmacies |
| `sparx_staff` | staff | SpaRx only |
| `magic8_staff` | staff | Magic 8 only |
| `maria` | customer | — |
| `Test_User1`, `Test_User2` | customer | Registered via signup |

---

## 17. Design change — Priority user types removed

After Step 4, priority user groups (Senior, PWD, Pregnant, Parent) were removed from the system per project decision.

### 17.1 Database migration

**File:** `database/migrations/2026_07_20_000001_remove_user_priority_types.php`

| Table | Column removed |
|-------|----------------|
| `users` | `priority_type` |
| `inquiries` | `is_priority` |

Run with: `php artisan migrate` (already applied in development).

### 17.2 Code and UI changes

| Area | Change |
|------|--------|
| Signup (Auth view) | Removed priority user chips and User type dropdown |
| Inquiry form | Removed User type field and priority info banner |
| Admin inquiries table | Removed Priority column |
| Admin dashboard | Removed priority-related stat |
| `AuthController` | No longer accepts or stores `priority_type` |
| `InquiryController` | No longer sets or sorts by `is_priority` |
| `DatabaseSeeder` | Maria seeded as regular customer |
| `styles.css` | Removed `.priority-info`, `.priority-chips`, `.priority-chip` rules |

**Retained:** User roles (`admin`, `staff`, `customer`) and all Step 4 admin functionality.

---

## 18. File cleanup

July 2026 cleanup after Step 4 and priority removal:

| Action | Details |
|--------|---------|
| Removed dead CSS | Priority-related classes from `styles.css` and `backend/public/css/pharmalocate.css` |
| Updated comments | Controllers, seeder, and legacy migrations note removed columns |
| Fixed settings text | Admin Settings no longer mentions "priority flags" |
| Synced `public/` | Root HTML/CSS/JS copied to `backend/public/` |
| Removed empty folder | `screenshots/` |
| Added `README.md` | Quick-start pointer at `Programming3/` root |
| Updated `backend/README.md` | Current API list without priority fields |

---

## 19. Updated API reference (admin endpoints)

These endpoints were **not in v1.0** or were API-only without UI. All require `Authorization: Bearer <token>` and admin/staff role.

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/admin/dashboard` | Dashboard stats and activity feed |
| GET | `/api/admin/stock` | Stock list (scoped for staff) |
| PATCH | `/api/admin/stock/{pharmacy}/{medicine}` | Update quantity, price, availability |

**Inquiry reply (existed in v1.0 API, now wired in UI):**

| Method | Path | Purpose |
|--------|------|---------|
| PATCH | `/api/inquiries/{id}` | Staff/admin reply; body: `{ "response": "...", "status": "answered" }` |

**Removed from register/login flow:** `priority_type` field on `POST /api/register`.

For the full endpoint list including public routes, see v1.0 Section 8 and add the admin rows above.

---

## 20. Updated system capabilities

### Now working in live mode (added since v1.0)

| Feature | v1.0 | v1.1 |
|---------|------|------|
| Admin dashboard stats | Static HTML | Live from `/api/admin/dashboard` |
| Admin inquiry reply | Static HTML | Live table + reply panel |
| Admin stock management | Static HTML | Live read/update via API |
| Admin login routing | Manual navigation | Auto-opens Admin view for admin/staff |
| Staff pharmacy scope | Not implemented | Staff limited to assigned pharmacy |
| Priority user signup/inquiries | Supported | **Removed** |

### Still customer-facing (unchanged from v1.0)

- Browse medicines and availability  
- View pharmacies (distance sort with lat/lng)  
- Register, login, logout  
- Submit and view own inquiries  

### Demo mode (unchanged)

Double-click `PharmaLocateFrontEnd.html` — static data, no API calls for data loading.

---

## 21. Updated known limits

Items resolved since v1.0:

| v1.0 limit | v1.1 status |
|------------|-------------|
| Admin dashboard stats static | **Resolved** — live API |
| Admin inquiry reply not wired | **Resolved** — live API + UI |
| Admin stock static | **Resolved** — live API + UI |
| Staff reply API only | **Resolved** — admin UI connected |

Remaining prototype areas (unchanged or still pending):

1. **POS** — demo cart only; no transaction API wired  
2. **Pharmacy CRUD** — `#admin-pharmacies` UI not connected  
3. **Geofence CRUD** — `#admin-geofences` UI not connected; `GeofenceController` exists but no route registered  
4. **Map** — SVG placeholder on Pharmacies tab; no Leaflet/GPS  
5. **Geofence filter** — haversine distance sort only; no zone-radius enforcement in UI  
6. **User management, settings, backup** — placeholder screens  
7. **Legacy Blade pages** at `/classic`, `/login` — separate from main prototype at `/`  

---

## 22. Roadmap (Step 5 and beyond)

Step 4 from v1.0 is **complete**. Next work:

### Step 5 — Priority 3 (Geofencing) — **NEXT**

| Task | Details |
|------|---------|
| 5.1 Pharmacy CRUD API | `GET/POST/PATCH/DELETE /api/admin/pharmacies` — wire `#admin-pharmacies` |
| 5.2 Geofence CRUD API | Register `GeofenceController`; manage zones + `geofence_pharmacy` links |
| 5.3 Leaflet map (customer) | Replace SVG on Pharmacies tab; show markers from API |
| 5.4 Geofence filtering | Filter/sort pharmacies inside active zone radius when location known |
| 5.5 Admin geofence UI | Wire `#admin-geofences` create/edit/delete |
| 5.6 Tile38 (optional) | If required by capstone paper (DC2): replace haversine with Tile38 queries |

**Suggested order:** 5.1 → 5.2 → 5.3 → 5.4 → 5.5 → 5.6

### Step 6 — Priority 4 (POS and extras)

1. POS transactions linked to inventory (`transactions`, `transaction_items`)  
2. User management, settings, backup/export  

### Step 7 — Evaluation

1. White-box and black-box testing  
2. ISO/IEC 25010 quality documentation  

---

## 23. Troubleshooting (additions)

| Problem | Fix |
|---------|-----|
| Admin view shows "log in as admin or staff" | Use `admin`, `sparx_staff`, or `magic8_staff` (password: `password`) |
| Staff sees no inquiries/stock | Confirm `users.pharmacy_id` matches the pharmacy in the database |
| Stock update returns 403 | Staff can only update their assigned pharmacy |
| Priority columns error after pull | Run `php artisan migrate` to apply `2026_07_20_000001_remove_user_priority_types` |
| UI changes not visible | Copy root HTML/CSS/JS to `backend/public/` and hard refresh (Ctrl+F5) |

---

## Document history

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | July 2026 | Steps 1–3, setup, architecture, capabilities. See `Programming2/Documentation_v1.0.md`. |
| 1.1 | July 2026 | Programming3 workspace, Step 4 admin core, priority user removal, cleanup, updated capabilities and roadmap. |

---

*Continues from Documentation v1.0 — end of Step 3.*
