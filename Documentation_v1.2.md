# PharmaLocate — Documentation v1.2

**Project:** Web-Based Pharmacy Inquiry System with Geofencing  
**Version:** 1.2 (continuation of v1.1)  
**Date:** July 2026  
**Status:** Steps 1–4 complete; Step 5 Sprints 5.0–5.4 complete  

**Prerequisite:** Read [Documentation_v1.0.md](../Programming2/Documentation_v1.0.md) (Steps 1–3) and [Documentation_v1.1.md](./Documentation_v1.1.md) (Step 4) first. This file documents everything added after v1.1.

**Frozen snapshots:** `Programming2/` (v1.0 baseline), `Programming3/` (Step 5 complete). Active development continues in `Programming4/`.

**Team setup:** See [setup_guide.md](./setup_guide.md) (also available as `setup_guide.docx`) for full installation instructions on a new laptop.

---

## Table of contents

24. [Step 5 overview — Geofencing (Sprints 5.0–5.4)](#24-step-5-overview--geofencing-sprints-5054)  
25. [Sprint 5.0 — Baseline verification](#25-sprint-50--baseline-verification)  
26. [Sprint 5.1 — Pharmacy CRUD API + admin UI](#26-sprint-51--pharmacy-crud-api--admin-ui)  
27. [Sprint 5.2 — Geofence CRUD API](#27-sprint-52--geofence-crud-api)  
28. [Sprint 5.3 — Leaflet customer map](#28-sprint-53--leaflet-customer-map)  
29. [Sprint 5.4 — Geofence filtering](#29-sprint-54--geofence-filtering)  
30. [Map UI fix — Leaflet attribution flag](#30-map-ui-fix--leaflet-attribution-flag)  
31. [Updated API reference (Step 5 endpoints)](#31-updated-api-reference-step-5-endpoints)  
32. [Updated system capabilities](#32-updated-system-capabilities)  
33. [Updated known limits](#33-updated-known-limits)  
34. [Roadmap (Sprint 5.5 and beyond)](#34-roadmap-sprint-55-and-beyond)  
35. [Troubleshooting (additions)](#35-troubleshooting-additions)

---

## 24. Step 5 overview — Geofencing (Sprints 5.0–5.4)

**Goal (from v1.1 roadmap):** Replace the decorative SVG map with a live Leaflet map, add pharmacy and geofence management APIs, and filter pharmacies by geofence zone when the user's location is known.

**Status at end of this session:** Sprints **5.0 through 5.4 complete**. Sprint **5.5** (admin geofence UI) is the next task when development resumes.

| Sprint | Scope | Status |
|--------|-------|--------|
| 5.0 | Verify Laravel + MySQL baseline before Step 5 | Done |
| 5.1 | Pharmacy CRUD API + wire `#admin-pharmacies` | Done |
| 5.2 | Geofence CRUD API + public `/api/geofences` | Done |
| 5.3 | Leaflet map on customer Pharmacies tab | Done |
| 5.4 | Geofence-based pharmacy filtering (API + UI) | Done |
| 5.5 | Admin geofence UI (`#admin-geofences`) | **Next** |
| 5.6 | Tile38 integration (optional) | Planned |

**Default location:** When the browser does not grant GPS permission, the app uses Tarlac Provincial Hospital coordinates (`15.4870`, `120.5960`) — the same center as the seeded geofence zone.

---

## 25. Sprint 5.0 — Baseline verification

Before starting Step 5, the existing stack was confirmed working:

1. XAMPP MySQL running  
2. `php artisan serve` → http://127.0.0.1:8000  
3. Login as `admin` / `password`  
4. Customer tabs (Medicines, Pharmacies, Inquiries) load live data  
5. Admin dashboard, inquiries, and stock sections work from Step 4  

No code changes were required for 5.0 — it establishes a known-good starting point for geofencing work.

---

## 26. Sprint 5.1 — Pharmacy CRUD API + admin UI

**Goal:** Allow admins to create, edit, and deactivate pharmacies; staff can view and edit their assigned pharmacy only.

### 26.1 Backend — `AdminPharmacyController`

**File:** `backend/app/Http/Controllers/AdminPharmacyController.php`

| Method | Path | Who | Behavior |
|--------|------|-----|----------|
| GET | `/api/admin/pharmacies` | admin/staff | List pharmacies (staff: own only) |
| POST | `/api/admin/pharmacies` | admin only | Create pharmacy |
| PATCH | `/api/admin/pharmacies/{id}` | admin/staff | Update (staff: own only) |
| DELETE | `/api/admin/pharmacies/{id}` | admin only | Soft deactivate (`is_active = false`) |

**Validation fields:** `name`, `address`, `contact_number`, `operating_hours`, `latitude`, `longitude`, `is_active`.

**Staff scoping:** Same pattern as Step 4 stock — staff with `pharmacy_id` set can only read/update that pharmacy. Create and delete are admin-only.

### 26.2 Frontend — `#admin-pharmacies`

**Files:** `PharmaLocateFrontEnd.html`, `app.js`

| Function | Behavior |
|----------|----------|
| `loadAdminPharmacies()` | Fetches list, renders cards; hides Add button for staff |
| `openAdminPharmacyForm(id?)` | Opens create/edit panel with lat/lng defaults near Tarlac |
| `saveAdminPharmacy()` | POST or PATCH to `/api/admin/pharmacies` |
| `deactivateAdminPharmacy(id)` | DELETE (soft deactivate) |

Default coordinates for new pharmacies: `15.4890`, `120.5980` (SpaRx area).

---

## 27. Sprint 5.2 — Geofence CRUD API

**Goal:** Manage geofence zones and assign pharmacies to them. Expose active zones publicly for the customer map.

### 27.1 Public endpoint

**File:** `backend/app/Http/Controllers/GeofenceController.php`

| Method | Path | Auth | Behavior |
|--------|------|------|----------|
| GET | `/api/geofences` | — | Active geofences with assigned pharmacy IDs/coords |

Registered in `routes/api.php` outside the auth group.

### 27.2 Admin endpoints — `AdminGeofenceController`

**File:** `backend/app/Http/Controllers/AdminGeofenceController.php`

| Method | Path | Who | Behavior |
|--------|------|-----|----------|
| GET | `/api/admin/geofences` | admin/staff | All geofences with pharmacies (read-only for staff) |
| POST | `/api/admin/geofences` | admin only | Create zone; optional `pharmacy_ids` array |
| PATCH | `/api/admin/geofences/{id}` | admin only | Update zone; optional `pharmacy_ids` sync |
| DELETE | `/api/admin/geofences/{id}` | admin only | Soft deactivate (`is_active = false`) |
| POST | `/api/admin/geofences/{id}/pharmacies` | admin only | Attach one pharmacy (`pharmacy_id`) |
| DELETE | `/api/admin/geofences/{id}/pharmacies/{pharmacy}` | admin only | Detach pharmacy |

**Validation:** `name`, `description`, `center_latitude`, `center_longitude`, `radius_meters` (500–10000), `is_active`.

### 27.3 Seed data

**File:** `backend/database/seeders/DatabaseSeeder.php`

| Record | Values |
|--------|--------|
| Zone name | Tarlac Provincial Hospital Zone |
| Center | 15.4870, 120.5960 |
| Radius | 5000 m (5 km) |
| Assigned pharmacies | SpaRx Pharmacy, Magic 8 Pharmacy |

---

## 28. Sprint 5.3 — Leaflet customer map

**Goal:** Replace the static SVG on the customer **Pharmacies** tab with an interactive OpenStreetMap via Leaflet 1.9.4.

### 28.1 HTML changes

**File:** `PharmaLocateFrontEnd.html`

- Added Leaflet CSS/JS from unpkg CDN  
- Replaced SVG map with `<div id="guest-leaflet-map"></div>` inside `.geo-map-wrap`  
- Map overlay label updated dynamically (`#pharmacy-map-overlay`)

### 28.2 JavaScript — map functions

**File:** `app.js`

| Function | Behavior |
|----------|----------|
| `getUserLocation()` | Tries GPS; falls back to `TARLAC_CENTER` (15.487, 120.596) |
| `fetchPublicGeofences()` | Loads `GET /api/geofences` |
| `ensureCustomerMap()` | Creates Leaflet map + OSM tile layer once |
| `renderCustomerMapLayers()` | Draws geofence circles, user dot, pharmacy pin markers |
| `selectPharmacyFromList(id)` | Syncs list selection with map pan + popup |
| `openPharmacyDirections()` | Opens Google Maps directions in new tab |
| `updatePharmacyMapOverlay()` | Shows "GPS" or "default hospital area" label |

**Map layers:**

- **Geofence circles** — dashed border, light fill, tooltip with zone name  
- **User location** — green `L.circleMarker`  
- **Pharmacies** — default `L.marker` pins with popups  

When the Pharmacies tab opens, `customerMap.invalidateSize()` runs after 150 ms so the map renders correctly inside the tab panel.

### 28.3 CSS

**File:** `styles.css`

```css
#guest-leaflet-map {
  width: 100%;
  height: 100%;
  min-height: 400px;
}
.geo-map-wrap:has(#guest-leaflet-map) {
  display: block;
}
```

---

## 29. Sprint 5.4 — Geofence filtering

**Goal:** When lat/lng are provided, return only pharmacies assigned to geofence zones that contain the user's point. Show clear empty-state messages when outside all zones.

### 29.1 Backend — `PharmacyController::index`

**File:** `backend/app/Http/Controllers/PharmacyController.php`

Logic when `?lat=&lng=` are numeric:

1. Load all active pharmacies  
2. If active geofences exist:
   - Find zones where the point is inside the radius  
   - If none match → return `[]`  
   - Else filter pharmacies to those linked in `geofence_pharmacy` for matching zones  
3. Compute `distance_km` via haversine and sort nearest-first  

### 29.2 Backend — `Geofence` model helpers

**File:** `backend/app/Models/Geofence.php`

| Method | Purpose |
|--------|---------|
| `containsPoint(lat, lng)` | True if point is within `radius_meters` of center |
| `haversineKm(...)` | Shared distance calculation (also used by `PharmacyController`) |

### 29.3 Frontend — matching and notices

**File:** `app.js`

| Function | Behavior |
|----------|----------|
| `getMatchingGeofences()` | Client-side zone match (for notice text) |
| `isUserInsideAnyGeofence()` | Used for empty-state messaging |
| `getPharmacyListEmptyMessage()` | Context-aware "outside zone" / "no assigned" text |
| `updateGeofenceNotice()` | Banner: e.g. "Inside Tarlac Provincial Hospital Zone (5 km) — showing 2 assigned pharmacies" |

**API call:** `loadPharmacies()` sends `?lat=&lng=` from `userLocation` so filtering happens server-side.

### 29.4 Verified behavior

| Location | Expected result |
|----------|-----------------|
| Default Tarlac (15.487, 120.596) | 2 pharmacies (SpaRx ~0.31 km, Magic 8 ~0.81 km) |
| Outside all zones (e.g. Manila coords) | Empty list + "outside geofences" notice |

---

## 30. Map UI fix — Leaflet attribution flag

**Problem:** A large blue/yellow rectangle appeared in the bottom-right corner of the map.

**Cause:** The CSS rule `.geo-map-wrap svg { width: 100%; height: 100%; }` (intended for the old inline SVG map) also matched Leaflet's attribution flag SVG inside the map container, stretching it to ~200×135 px.

**Fix (`styles.css`):**

```css
/* Legacy inline SVG map only (direct child — not Leaflet attribution icons) */
.geo-map-wrap > svg { width: 100%; height: 100%; }

.leaflet-control-attribution .leaflet-attribution-flag {
  width: 12px;
  height: 8px;
  vertical-align: baseline;
}
.leaflet-control-attribution {
  font-size: 10px;
  line-height: 1.3;
  max-width: calc(100% - 24px);
}
```

After editing CSS, copy to `backend/public/` and hard-refresh (Ctrl+F5).

---

## 31. Updated API reference (Step 5 endpoints)

Endpoints added since v1.1. All admin routes require `Authorization: Bearer <token>` and admin/staff role unless noted.

### Public

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/geofences` | Active geofence zones + assigned pharmacies |

### Admin — pharmacies

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/admin/pharmacies` | List pharmacies |
| POST | `/api/admin/pharmacies` | Create (admin only) |
| PATCH | `/api/admin/pharmacies/{id}` | Update |
| DELETE | `/api/admin/pharmacies/{id}` | Deactivate (admin only) |

### Admin — geofences

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/api/admin/geofences` | List all geofences |
| POST | `/api/admin/geofences` | Create (admin only) |
| PATCH | `/api/admin/geofences/{id}` | Update (admin only) |
| DELETE | `/api/admin/geofences/{id}` | Deactivate (admin only) |
| POST | `/api/admin/geofences/{id}/pharmacies` | Assign pharmacy |
| DELETE | `/api/admin/geofences/{id}/pharmacies/{pharmacy}` | Unassign pharmacy |

### Updated public behavior

| Method | Path | Change |
|--------|------|--------|
| GET | `/api/pharmacies?lat=&lng=` | Filters by geofence assignment when active zones exist |

**Token storage:** Frontend uses `localStorage` key `ph_token` (not `pharmalocate_token`).

---

## 32. Updated system capabilities

### Added since v1.1

| Feature | v1.1 | v1.2 |
|---------|------|------|
| Admin pharmacy CRUD | Static UI | Live API + form panel |
| Geofence API | Controller only, no routes | Full CRUD + public index |
| Customer map | Decorative SVG | Live Leaflet + OSM tiles |
| Geofence visualization | None | Circles on map from `/api/geofences` |
| Zone-based pharmacy filter | Distance sort only | Server-side geofence filter + UI notices |
| GPS location | N/A | Optional; defaults to hospital area |

### Unchanged from v1.1

- Customer medicines, inquiries, auth  
- Admin dashboard, inquiry reply, stock management  
- Demo mode (double-click HTML, static data)  
- Priority user types remain removed  

---

## 33. Updated known limits

Items resolved since v1.1:

| v1.1 limit | v1.2 status |
|------------|-------------|
| Pharmacy CRUD UI not connected | **Resolved** — Sprint 5.1 |
| GeofenceController not registered | **Resolved** — Sprint 5.2 |
| SVG placeholder map | **Resolved** — Sprint 5.3 |
| No zone-radius enforcement | **Resolved** — Sprint 5.4 |

Remaining prototype areas:

1. **Admin geofence UI** — `#admin-geofences` section not wired (API ready) — **Sprint 5.5**  
2. **Admin Leaflet map** — placeholder for geofence drawing/editing in admin view  
3. **POS** — demo cart only; no transaction API  
4. **User management, settings, backup** — placeholder screens  
5. **Tile38** — optional; haversine used instead (Sprint 5.6)  
6. **Legacy Blade pages** at `/classic`, `/login` — separate from main prototype at `/`  

---

## 34. Roadmap (Sprint 5.5 and beyond)

Development **paused at Sprint 5.4** for this session. Resume here:

### Sprint 5.5 — Admin geofence UI (next)

- Wire `#admin-geofences` to `/api/admin/geofences`  
- Create/edit/deactivate zones  
- Assign/unassign pharmacies  
- Optional: admin Leaflet map for picking center + radius  

### Sprint 5.6 — Tile38 (optional)

- Install and run Tile38 (`C:\tile38\` on dev machine)  
- Laravel + predis for `NEARBY` / geofence queries  
- Only if required by capstone paper (DC2)  

### Step 6 — POS and extras

- POS transactions linked to inventory  
- User management, settings, backup/export  

### Step 7 — Evaluation

- White-box and black-box testing  
- ISO/IEC 25010 quality documentation  

---

## 35. Troubleshooting (additions)

| Problem | Fix |
|---------|-----|
| Large blue/yellow block on map | Apply CSS fix in Section 30; sync `styles.css` to `backend/public/`; Ctrl+F5 |
| Map tiles grey / empty | Confirm internet access (OSM CDN); check browser console |
| 401 on admin geofence API test | Use `localStorage.getItem('ph_token')` as Bearer token |
| Pharmacies tab shows 0 results | Check coords — default Tarlac should show 2; Manila coords should show 0 (outside zone) |
| Map not sized correctly | Switch away and back to Pharmacies tab; `invalidateSize` runs on tab open |
| GPS denied | Expected — app uses hospital default; overlay shows "default" label |
| Admin cannot create pharmacy | Only `admin` role can POST; staff can edit own pharmacy only |
| Changes not visible | Copy root HTML/CSS/JS to `backend/public/` and hard refresh |

---

## Document history

| Version | Date | Changes |
|---------|------|---------|
| 1.0 | July 2026 | Steps 1–3. See `Programming2/Documentation_v1.0.md`. |
| 1.1 | July 2026 | Step 4 admin core, priority removal, cleanup. |
| 1.2 | July 2026 | Step 5 Sprints 5.0–5.4: pharmacy/geofence CRUD, Leaflet map, geofence filtering, map CSS fix. Paused before Sprint 5.5. |

---

*Continues from Documentation v1.1 — end of Step 4.*
