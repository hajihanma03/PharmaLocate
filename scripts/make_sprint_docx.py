# -*- coding: utf-8 -*-
"""Generate PharmaLocate_Sprint_Documentation.docx"""
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

OUT = Path(__file__).resolve().parents[1] / "PharmaLocate_Sprint_Documentation.docx"
GREEN = RGBColor(0x0F, 0x6E, 0x56)
DARK = RGBColor(0x1A, 0x26, 0x20)
MUTED = RGBColor(0x4D, 0x6B, 0x60)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
HEADER_BG = "1D9E75"
ROW_ALT = "E8F5EE"


def set_run(run, size=11, bold=False, color=DARK, font="Calibri"):
    run.font.name = font
    run._element.rPr.rFonts.set(qn("w:eastAsia"), font)
    run.font.size = Pt(size)
    run.bold = bold
    run.font.color.rgb = color


def shade_cell(cell, hex_color):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    from docx.oxml import OxmlElement

    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), hex_color)
    shd.set(qn("w:val"), "clear")
    tcPr.append(shd)


def set_cell_text(cell, text, *, bold=False, size=10, color=DARK, center=False):
    cell.text = ""
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(2)
    if center:
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run(text)
    set_run(run, size=size, bold=bold, color=color)


def add_table(doc, headers, rows, col_widths=None):
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = "Table Grid"
    table.autofit = True
    for i, h in enumerate(headers):
        cell = table.rows[0].cells[i]
        shade_cell(cell, HEADER_BG)
        set_cell_text(cell, h, bold=True, size=10, color=WHITE, center=True)
        if col_widths:
            cell.width = Cm(col_widths[i])
    for r_i, row in enumerate(rows):
        for c_i, val in enumerate(row):
            cell = table.rows[r_i + 1].cells[c_i]
            if r_i % 2 == 1:
                shade_cell(cell, ROW_ALT)
            set_cell_text(cell, str(val), size=10)
            if col_widths:
                cell.width = Cm(col_widths[c_i])
    doc.add_paragraph()
    return table


def heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    for run in p.runs:
        run.font.color.rgb = GREEN
    return p


def para(doc, text, *, bold=False, italic=False, size=11):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
    run = p.add_run(text)
    set_run(run, size=size, bold=bold)
    run.italic = italic
    return p


def bullet(doc, text):
    p = doc.add_paragraph(text, style="List Bullet")
    p.paragraph_format.space_after = Pt(3)
    for run in p.runs:
        set_run(run, size=11)
    return p


def numbered(doc, text):
    p = doc.add_paragraph(text, style="List Number")
    p.paragraph_format.space_after = Pt(3)
    for run in p.runs:
        set_run(run, size=11)
    return p


def sprint_block(doc, code, title, status, goal, process, items, deliverables, evidence):
    heading(doc, f"{code} — {title}", 2)
    para(doc, f"Status: {status}", bold=True)
    para(doc, f"Goal: {goal}")
    para(doc, "Development process", bold=True)
    for p in process:
        para(doc, p)
    para(doc, "Work completed", bold=True)
    for item in items:
        bullet(doc, item)
    para(doc, "Deliverables", bold=True)
    for item in deliverables:
        bullet(doc, item)
    para(doc, f"Evidence / how to verify: {evidence}")


def main():
    doc = Document()
    for section in doc.sections:
        section.top_margin = Cm(2)
        section.bottom_margin = Cm(2)
        section.left_margin = Cm(2.2)
        section.right_margin = Cm(2.2)

    # Cover
    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    t.paragraph_format.space_before = Pt(48)
    r = t.add_run("PHARMALOCATE")
    set_run(r, size=14, bold=True, color=GREEN)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("Sprint Documentation")
    set_run(r, size=28, bold=True, color=DARK)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("Web-Based Pharmacy Inquiry System with Geofencing")
    set_run(r, size=13, color=MUTED)
    r.italic = True

    meta = [
        ("Course / subject", "Capstone / Programming 4"),
        ("Active workspace", "Programming4 (Laravel 13 + 3-file frontend)"),
        ("Document type", "Sprint log, process discussion, and team how-to"),
        ("Version", "1.1"),
        ("Date", "September 30, 2026"),
        ("Coverage", "Steps 1–7 plus UI polish — development process per sprint"),
    ]
    doc.add_paragraph()
    add_table(doc, ["Field", "Value"], meta)

    para(
        doc,
        "This file is the team’s official sprint record. Use it in the capstone binder, "
        "weekly stand-ups, and panel defense when asked how the system was built in stages. "
        "It is not a replacement for Documentation v1.0–v1.3; those remain the detailed technical notes.",
    )

    heading(doc, "1. How to create and maintain a sprint document", 1)
    para(
        doc,
        "A sprint document is a living log. Each sprint is a short, named block of work with "
        "a clear goal, a done checklist, and proof (running feature, API, screenshot, or test). "
        "PharmaLocate maps sprints onto capstone Steps 1–7 so advisors can see progress without "
        "reading the entire codebase.",
    )

    heading(doc, "1.1 What to put in every sprint", 2)
    numbered(doc, "Sprint ID — use the same numbers as the paper (Step 1, Sprint 5.1, Sprint 7.1).")
    numbered(doc, "Goal — one sentence: what the user or admin can do when the sprint is done.")
    numbered(doc, "Scope in / out — what you will not do this sprint (stops scope creep).")
    numbered(doc, "Backlog items — small tasks (API, UI, seed data, copy to public/).")
    numbered(doc, "Definition of done — who can log in, which URL, which tab or sidebar item.")
    numbered(doc, "Evidence — screenshot, PHPUnit name, or API path. Binder needs pictures for black-box cases.")
    numbered(doc, "Status — Planned, In progress, Done, or Deferred (with a reason).")
    numbered(doc, "Risks / notes — PHP version, XAMPP, Tile38 optional, unofficial FAQs, etc.")

    heading(doc, "1.2 How we write it in Microsoft Word", 2)
    bullet(doc, "Title page + version table at the top (this document).")
    bullet(doc, "Use Heading 1 for steps and Heading 2 for individual sprints so the Navigation pane works.")
    bullet(doc, "Use tables for status boards; keep cells short (one line if possible).")
    bullet(doc, "Save as .docx (not PDF) while the log is still changing; export PDF only for frozen binder copies.")
    bullet(doc, "After each sprint: update the master table, add a short sprint section, sync frontend to backend/public/, then hard-refresh the browser (Ctrl+F5).")
    bullet(doc, "Do not invent work. If a sprint is only verification (Sprint 5.0), write “no code change” and list the checks.")

    heading(doc, "1.3 Naming we use on this project", 2)
    para(
        doc,
        "“Step” is the capstone phase (1 frontend lock, 2 backend map, 3 live API, 4 admin core, "
        "5 geofencing, 6 POS and extras, 7 testing). “Sprint” is a slice inside a step (5.0–5.6, 7.0–7.4). "
        "Advisors may say “sprint” for a whole step; this document lists both so the team stays aligned.",
    )

    heading(doc, "1.4 Blank template (copy for a new sprint)", 2)
    add_table(
        doc,
        ["Field", "Fill in"],
        [
            ["Sprint ID", "e.g. 8.1"],
            ["Dates", "Start — end"],
            ["Goal", "One sentence"],
            ["In scope", "Bullets"],
            ["Out of scope", "Bullets"],
            ["Tasks", "Who / file / API"],
            ["Done when", "Observable result"],
            ["Evidence", "Screenshot ID or test name"],
            ["Status", "Planned / In progress / Done / Deferred"],
        ],
    )

    heading(doc, "2. Master sprint board", 1)
    para(doc, "Snapshot as of 30 September 2026. Active tree: Programming4.")
    add_table(
        doc,
        ["ID", "Name", "Status", "Main deliverable"],
        [
            ["1", "Frontend lock", "Done", "3-file UI (guest, auth, admin prototype)"],
            ["2", "Backend map", "Done", "Objectives mapped to APIs and priorities"],
            ["3", "Live API (P1)", "Done", "Auth, availability, pharmacies, inquiries"],
            ["4", "Admin core (P2)", "Done", "Dashboard, reply, stock"],
            ["5.0", "Baseline check", "Done", "XAMPP + serve + login confirmed"],
            ["5.1", "Pharmacy CRUD", "Done", "Admin pharmacies live"],
            ["5.2", "Geofence API", "Done", "Admin + public geofence routes"],
            ["5.3", "Leaflet map", "Done", "Customer Pharmacies tab map"],
            ["5.4", "Zone filter", "Done", "lat/lng geofence filter + notices"],
            ["5.5", "Admin geofence UI", "Done", "Zones, assign pharmacies, admin map"],
            ["5.6", "Tile38 (optional)", "Done (fallback)", "Haversine if Tile38 is off"],
            ["6", "POS and extras", "Done", "POS, users, settings, export"],
            ["7.0–7.4", "Testing (ISO 25010)", "Mostly done", "Plan + PHPUnit; screenshots remaining"],
            ["8", "UI polish + Help", "Done (draft FAQs)", "Animations, loader, rule-based chatbot"],
        ],
        col_widths=[2.2, 4.2, 3.2, 6.2],
    )

    heading(doc, "3. Sprint records and development process", 1)
    para(
        doc,
        "PharmaLocate was not built as one big release. Development followed a front-to-back "
        "pipeline: lock the screens, plan the APIs, connect customers first, then staff/admin, "
        "then location, then operations (POS), then prove quality, then polish. Each sprint "
        "ended with something a teammate could click. Folders Programming2 → Programming3 → "
        "Programming4 froze finished steps so later work could not silently rewrite earlier chapters.",
    )
    para(
        doc,
        "Standing process on every coding sprint: (1) change the smallest vertical slice "
        "(API + UI, or UI only if the API already existed); (2) copy the three frontend files "
        "into backend/public/; (3) hard-refresh the browser; (4) check the role that owns the "
        "feature (guest, maria, staff, admin). Planning sprints (Step 2, Sprint 5.0, Sprint 7.0) "
        "are allowed to ship documents instead of code.",
    )

    sprint_block(
        doc,
        "Step 1",
        "Frontend lock",
        "Done (July 2026)",
        "Freeze the customer and admin interface in three files before connecting a database.",
        [
            "The team treated the UI as the contract. Guest, Auth, and Admin were built as one HTML page with CSS and JavaScript so panelists could already see the product, not a wireframe. Demo data (10 medicines) kept the catalog inside the paper’s scope.",
            "Process choice: no Laravel yet. Opening the HTML file was enough to review layout, tabs, and admin sidebar. UX defects (auth Back, inquiry handler, null-safe JS) were fixed here so later API work would not fight a moving interface.",
            "This sprint enabled Step 2: every later API had a screen waiting for it. Risk accepted: some admin sections stayed empty until Steps 4–6.",
        ],
        [
            "Single-page app: Guest/User, Auth, Admin views.",
            "Customer tabs: Home, Pharmacies, Medicines, Inquiries.",
            "Admin sidebar prototype: dashboard, inquiries, stock, POS, pharmacies, geofences, settings, backup.",
            "Demo catalog limited to 10 medicines for capstone scope.",
        ],
        [
            "PharmaLocateFrontEnd.html, styles.css, app.js.",
            "Demo mode: open the HTML file without Laravel.",
        ],
        "Open the HTML file; all tabs and admin sections render with static data.",
    )

    sprint_block(
        doc,
        "Step 2",
        "Backend map and priorities",
        "Done (July 2026)",
        "Map every screen to Chapter 1–4 objectives and decide build order (P1–P5).",
        [
            "This was a design sprint, not a coding sprint. The process was: list Chapter 1.3 objectives, list screens, write one backend requirement per screen, then rank work as Priority 1 (customer live data) through Priority 5 (ISO tests).",
            "The team delayed geofencing and POS on purpose. Building a map before login and stock would have produced a pretty locator with no trustworthy inventory. P1 therefore meant auth, availability, pharmacies, and inquiries.",
            "Output was a table the advisor can still use in defense: screen → FR → API. No production endpoints were required to close the sprint.",
        ],
        [
            "Customer: availability, inquiries, locator.",
            "Admin: dashboard, stock, geofence, later POS and settings.",
            "Priority 1 = auth + live browse; Priority 5 = ISO testing docs.",
        ],
        ["Planning tables in Documentation v1.0 (no production API yet in this step)."],
        "Advisor can see a screen-to-API table; team agrees P1 vs P2 order.",
    )

    sprint_block(
        doc,
        "Step 3",
        "Priority 1 live integration",
        "Done (July 2026)",
        "Serve the locked UI from Laravel and load medicines, pharmacies, and inquiries from MySQL.",
        [
            "Development process switched from static files to client–server. The locked frontend stayed; a Laravel 13 API and MySQL (XAMPP) were placed behind it. Sanctum tokens stored as ph_token kept login simple for a 3-file app.",
            "Work order: database and seed data (admin, two pharmacies, maria, 10 medicines), then public GET routes, then login/register, then inquiry POST for customers. Login accepted username or email so demos would not fail on the wrong field.",
            "A dual-mode rule was set: if the page is opened as a file, stay in demo data; if it is served over HTTP, call /api. That let teammates review UI without XAMPP. After each edit the three files were copied to backend/public/ — still the team’s sync rule.",
            "Closure: php artisan serve shows live stock without login. That proved P1 and unlocked admin work in Step 4.",
        ],
        [
            "Laravel 13 API, Sanctum tokens, register/login (username or email).",
            "GET /api/availability, GET /api/pharmacies, inquiry GET/POST for customers.",
            "Route / serves the prototype; copy HTML/CSS/JS to backend/public/.",
            "Seeded accounts: maria, admin, sparx_staff (password: password).",
        ],
        ["Programming2 then later Programming4 backend; setup_guide for XAMPP/Composer/PHP."],
        "php artisan serve → http://127.0.0.1:8000; guest sees live stock; login works.",
    )

    sprint_block(
        doc,
        "Step 4",
        "Admin core (Priority 2)",
        "Done (July 2026 · Documentation v1.1)",
        "Staff and admin use live dashboard, inquiry reply, and stock update — not static HTML.",
        [
            "Process: freeze Programming2 as v1.0, develop in Programming3. That snapshot habit is part of the development method — later sprints must not rewrite the story of Steps 1–3.",
            "Implementation order: middleware first (only admin/staff hit /api/admin), then dashboard aggregates, then inquiry PATCH with a reply, then stock PATCH. Staff were scoped to pharmacy_id so one drugstore cannot edit another’s inventory.",
            "A product decision landed in this step: priority user types (senior, PWD, etc.) were removed from signup and inquiry sorting. Roles admin/staff/customer stayed. The process was cleanup + migrate, then re-test login and inquiry list.",
            "Routing process: after login, admin/staff skip the guest chrome and open Admin view. That reduced demo friction and became the default for later sprints.",
        ],
        [
            "Middleware: only admin/staff reach /api/admin/*.",
            "Dashboard stats API; PATCH inquiry as resolved with a reply.",
            "Stock list and quantity/price update; customer grid refreshes after stock change.",
            "Login as admin or staff opens Admin view automatically.",
        ],
        [
            "AdminDashboardController, StockController, EnsureStaffOrAdmin.",
            "Documentation_v1.1.md",
        ],
        "Log in as admin / password; dashboard numbers and inquiry table match the database.",
    )

    heading(doc, "Step 5 — Geofencing (Sprints 5.0–5.6)", 2)
    para(
        doc,
        "Whole-step process: do not draw a map until pharmacies and zones exist in the API. "
        "Suggested order 5.1 → 5.2 → 5.3 → 5.4 → 5.5 → 5.6 was followed so filtering could "
        "not run on empty data. Default GPS fallback is Tarlac Provincial Hospital "
        "(15.4870, 120.5960), the same center as the seeded 5 km zone.",
    )

    sprint_block(
        doc,
        "Sprint 5.0",
        "Baseline verification",
        "Done — no code change",
        "Prove the Step 4 stack still runs before map work.",
        [
            "Process: stop and re-run the happy path on a known machine. New map libraries on a broken MySQL would waste the sprint. Checks were operational (XAMPP, serve, admin login, live tabs), not new features.",
            "Closing 5.0 with “no commit” is intentional. The sprint document records verification as real work for capstone process marks.",
        ],
        [
            "XAMPP MySQL up; php artisan serve; admin login; customer tabs load live data.",
        ],
        ["Checklist only (Documentation v1.2 Section 25)."],
        "Same five checks pass on the teammate laptop.",
    )

    sprint_block(
        doc,
        "Sprint 5.1",
        "Pharmacy CRUD + admin UI",
        "Done",
        "Admin creates, edits, and deactivates pharmacies; staff edit only their assigned store.",
        [
            "API-first then UI: AdminPharmacyController and routes were written so Postman/PowerShell could create a pharmacy before the form existed. DELETE deactivates (is_active) so seed history is not destroyed.",
            "Then the existing #admin-pharmacies mock was wired: form panel, list, copy-to-public, refresh customer list. Staff remain limited to their store; only admin POSTs new pharmacies.",
            "This sprint unblocked 5.2: geofences need pharmacies to assign.",
        ],
        [
            "GET/POST/PATCH/DELETE /api/admin/pharmacies (DELETE = is_active false).",
            "Admin pharmacies panel wired; customer list refreshes after save.",
        ],
        ["AdminPharmacyController; #admin-pharmacies form."],
        "Create a pharmacy as admin; it appears on the customer Pharmacies tab when inside the zone.",
    )

    sprint_block(
        doc,
        "Sprint 5.2",
        "Geofence CRUD API",
        "Done",
        "Zones exist in the database and can be listed publicly for the map.",
        [
            "Backend-first again: register geofence routes that did not exist in v1.1, add public GET /api/geofences for the future map, and a pivot so many pharmacies can sit in one zone.",
            "The admin Geofences screen was still a prototype. Shipping API without UI was a process trade-off so Sprint 5.3 could draw circles from real JSON. Seed data created the hospital zone and attached SpaRx and Magic 8.",
        ],
        [
            "Admin geofence CRUD; public GET /api/geofences.",
            "Assign pharmacies to a zone (pivot geofence_pharmacy).",
        ],
        ["AdminGeofenceController; GeofenceController public index."],
        "Seeded zone “Tarlac Provincial Hospital Zone” (5 km) with SpaRx and Magic 8.",
    )

    sprint_block(
        doc,
        "Sprint 5.3",
        "Leaflet customer map",
        "Done",
        "Pharmacies tab shows OpenStreetMap, user marker, and pharmacy pins.",
        [
            "Frontend-heavy sprint. The decorative SVG was replaced with Leaflet and OSM tiles. GPS is requested with a short timeout; deny or fail falls back to hospital coordinates so demos in a classroom still show two pharmacies.",
            "Process issue found in browser: Leaflet attribution/flag CSS overflow. Fix was CSS + sync public + Ctrl+F5 — same frontend workflow as always.",
            "Map without filter still listed by distance only; that was left for 5.4 so 5.3 could be demoed independently.",
        ],
        [
            "Leaflet 1.9; GPS optional with hospital fallback.",
            "CSS fix for Leaflet attribution/flag overflow.",
        ],
        ["#guest-leaflet-map in HTML; map init in app.js."],
        "Pharmacies tab: two pins near Tarlac, overlay explains GPS vs default.",
    )

    sprint_block(
        doc,
        "Sprint 5.4",
        "Geofence-based filtering",
        "Done",
        "If active zones exist, pharmacies outside the user’s zone are not listed.",
        [
            "Server-side filter was required so a client cannot pretend to be inside a zone. PharmacyController uses lat/lng, finds containing geofences, then restricts to assigned pharmacies. Empty JSON plus a UI notice covers “outside all zones.”",
            "Verification process: hospital coords → two rows; coordinates far from Tarlac → empty. Distance labels still use haversine (or Tile38 later). This sprint is the research heart of the paper (geofencing, not just a map pin).",
        ],
        [
            "GET /api/pharmacies?lat=&lng= filters by containing geofence.",
            "Empty list + notice when the user is outside all zones.",
            "Haversine distance when Tile38 is unavailable.",
        ],
        ["PharmacyController; geofence notice on the Pharmacies tab."],
        "Hospital coords → 2 pharmacies; far-away coords → empty list.",
    )

    sprint_block(
        doc,
        "Sprint 5.5",
        "Admin geofence UI",
        "Done (Programming4)",
        "Admin draws/edits zones in the admin Geofences section; staff can view.",
        [
            "UI-last by design: APIs from 5.2 were reused. Development process was wire list/form, radius validation (500–10000 m), pharmacy checkboxes, assign dropdown, then an admin Leaflet map so staff can see coverage without guessing coordinates.",
            "Role process: admin mutates zones; staff get a read-only notice. After save, customer pharmacies refresh so the demo can show cause and effect in one sitting.",
        ],
        [
            "List, create, edit, deactivate; pharmacy checkboxes; assign dropdown.",
            "Admin Leaflet map of zones and assigned stores.",
        ],
        ["#admin-geofences; loadAdminGeofences() in app.js."],
        "Admin sidebar → Geofences; save a radius 500–10000 m; customer map updates.",
    )

    sprint_block(
        doc,
        "Sprint 5.6",
        "Tile38 (optional)",
        "Done with fallback",
        "Use Tile38 for nearby/geofence queries when the service is running; never block the app if it is not.",
        [
            "Paper DC2 allowed Tile38. Process decision: optional infrastructure must not fail the capstone demo. Tile38Service reports status on /api/health; if Redis/Tile38 is down, haversine continues.",
            "Defense talking point: this is ISO reliability / fault tolerance, not an incomplete feature. Teammates are not required to install Tile38 to pass Sprint 7 tests.",
        ],
        [
            "Tile38Service status on GET /api/health (tile38: unavailable is valid).",
            "Haversine fallback documented for defense.",
        ],
        ["backend Tile38 service; health JSON."],
        "Health endpoint returns ok even when Tile38 is down.",
    )

    sprint_block(
        doc,
        "Step 6",
        "POS, users, settings, backup/export",
        "Done (Programming4)",
        "Staff can record a sale that reduces stock; admin can manage users, settings, and exports.",
        [
            "Priority 4 was split inside one step: POS first because it changes inventory (the same numbers customers see), then users, then settings, then export. Each slice reused EnsureStaffOrAdmin and added admin-only middleware where needed.",
            "POS process: load products for the pharmacy, cart in the browser, POST transaction, decrement stock, reject oversell. Admin may pick a pharmacy; staff are locked to pharmacy_id.",
            "Users/settings/export were wired to placeholder screens from Step 1 so the sidebar stopped being fake. Staff see notices instead of 403 pages. Last-admin protection was a white-box rule added so demos cannot lock the team out.",
        ],
        [
            "POS product grid, cart, POST /api/admin/transactions; stock decrement; oversell rejected.",
            "Admin user list and role/pharmacy assignment; cannot remove the last admin.",
            "Settings: low-stock threshold, notification flags, backup schedule fields.",
            "Export JSON/CSV by scope (inventory, transactions, etc.).",
            "Staff stay scoped to their pharmacy for stock and POS.",
        ],
        [
            "AdminTransactionController, AdminUserController, AdminSettingsController, AdminExportController.",
            "Staff notices on users/settings/backup when role is not admin.",
        ],
        "Process a POS sale; stock on Medicines tab drops; staff cannot open user export.",
    )

    heading(doc, "Sprint 7 — Testing and evaluation (ISO/IEC 25010)", 2)
    para(
        doc,
        "Testing was planned as its own development step, not an afterthought. Process: write "
        "the quality model mapping first, then automate what can run in PHPUnit, then leave "
        "browser screenshots for the binder. Live MySQL is for demos; SQLite in-memory is for "
        "CI-style artisan test. Enabling pdo_sqlite was an environment task inside 7.2/7.3.",
    )

    sprint_block(
        doc,
        "Sprint 7.0",
        "Test plan and ISO mapping",
        "Done",
        "Agree what “quality” means before executing cases.",
        [
            "Documentation v1.3 Sections 36–38: environment, ISO/IEC 25010 table, test IDs. No new product features. The process prevents testing random screens that are not in the paper.",
        ],
        ["ISO mapping table; test environment (Windows, Chrome/Edge, PHP 8.3+, XAMPP)."],
        ["Documentation_v1.3.md"],
        "Binder contains a quality-characteristic → test-ID table.",
    )

    sprint_block(
        doc,
        "Sprint 7.1",
        "Black-box execution",
        "Partial — 16 UI screenshots remaining",
        "Exercise the system as a user (no looking at code).",
        [
            "Cases BB-01–BB-50 cover guest browse, auth, inquiries, map, admin, POS, roles. Process: pre-test checklist (MySQL, serve, seed, Ctrl+F5), then step-expected-result tables. PHPUnit later absorbed many BB cases as HTTP tests; remaining work is photographic evidence.",
        ],
        ["Manual UI path + overlapping automated BB cases in Sprint7Test.php."],
        ["Sprint7_Test_Execution_Report.md"],
        "Screenshots labeled BB-xx in the printed binder.",
    )

    sprint_block(
        doc,
        "Sprint 7.2",
        "White-box execution",
        "Automated 10/12; 2 optional inspect",
        "Prove specific code paths (hashing, staff scope, geofence contains-point, last admin).",
        [
            "Unit tests target algorithms and policies, not the look of the page. Process: map WB-01–WB-12 to functions (password hashed, stock status vs threshold, haversine/geofence, POS oversell). Optional WB items (audit log, CSV eyeball) stay manual.",
        ],
        ["Sprint7WhiteBoxTest.php and related Feature assertions."],
        ["php artisan test --filter Sprint7"],
        "29 PHPUnit tests pass on a machine with sqlite drivers.",
    )

    sprint_block(
        doc,
        "Sprint 7.3",
        "API cases",
        "Done — 20/20 automated",
        "Contract-test every public and admin endpoint used in demos.",
        [
            "API-01–API-20 run as Feature tests: health, login, register, availability, geofence in/out, inquiries, dashboard, POS, users, settings, export, staff 403s. Process is repeatable: artisan test, not a one-off Postman collection.",
        ],
        ["Feature/Sprint7Test.php"],
        ["Same filter Sprint7"],
        "All API-xx rows marked Pass in the execution report.",
    )

    sprint_block(
        doc,
        "Sprint 7.4",
        "Summary and binder",
        "Template filled; remaining photos",
        "Turn raw results into advisor-ready evidence.",
        [
            "Fill Section 42 summary, checklist 43, keep the execution report next to v1.3. Process is documentary: testers must not leave Pass/Fail blank. Remaining casualty is screenshot volume, not failed automation.",
        ],
        ["Execution report executive summary (62/80 verified; 29/29 PHPUnit)."],
        ["Sprint7_Test_Execution_Report.md", "Documentation v1.3 §§42–43"],
        "Advisor can read pass rate and know which BB IDs still need photos.",
    )

    sprint_block(
        doc,
        "Sprint 8",
        "UI polish and Help assistant",
        "Done for unofficial FAQs — swap text when validated questions arrive",
        "Make first-load and tab changes feel smooth, and add a rule-based Help panel (no AI/LLM).",
        [
            "Advisor asked for a chatbot without generative AI. Process: ship an open/close shell first, then load unofficial bilingual presets, then replace CHATBOT_FAQS when the validated list arrives — no model, no medical advice.",
            "Polish process ran in parallel while waiting for FAQs: loader that always ends (failsafe + revealApp), tab animations, deferred GPS so the home screen is not blocked, then bugfix pass (undefined navbar name, fake inquiry samples, Help/Close toggle, Enter to login).",
            "Frontend dual-copy remains: edit root files, Copy-Item to public, Ctrl+F5. Help maps paper language (“Find nearest pharmacy”, “Chat with pharmacist”) onto existing tabs Pharmacies and Inquiries.",
        ],
        [
            "Boot loader; tab slide; GPS after first paint.",
            "Help assistant: EN/FIL, 18 unofficial bilingual presets.",
            "Navbar shows a real username or Admin/Staff.",
            "Home medicines and inquiry list use live/empty states.",
        ],
        [
            "CHATBOT_FAQS in app.js; chatbot panel in HTML/CSS.",
            "Copy the three frontend files to backend/public/ after edits.",
        ],
        "Ctrl+F5; Help opens questions; EN/FIL switch; Close hides the panel; guest inquiries say log in.",
    )

    heading(doc, "4. Demo accounts (for sprint demos)", 1)
    add_table(
        doc,
        ["Role", "Username", "Password", "Opens"],
        [
            ["Customer", "maria", "password", "Guest UI with logged-in navbar"],
            ["Admin", "admin", "password", "Admin dashboard automatically"],
            ["Staff", "sparx_staff", "password", "Admin UI, SpaRx-scoped stock/POS"],
        ],
    )
    para(doc, "After migrate --seed. Public browse does not require login.")

    heading(doc, "5. Definition of done (team checklist)", 1)
    bullet(doc, "Feature works on http://127.0.0.1:8000 after copying HTML/CSS/JS to backend/public/.")
    bullet(doc, "Hard refresh (Ctrl+F5) so old app.js is not cached.")
    bullet(doc, "Role rules hold: customer vs staff vs admin.")
    bullet(doc, "No medical-advice claims in Help copy; unofficial FAQs labeled as draft until validated.")
    bullet(doc, "This sprint log updated the same day the sprint is closed.")

    heading(doc, "6. Still open (not a new sprint until assigned)", 1)
    bullet(doc, "Replace unofficial Help questions with the validated set (edit CHATBOT_FAQS only).")
    bullet(doc, "Finish remaining Sprint 7 black-box screenshots for the printed binder.")
    bullet(doc, "Align Documentation v2.0 if it still lags v1.3 / this sprint log.")
    bullet(doc, "Teammate machines: PHP 8.3+, same XAMPP database name, copy-public workflow.")
    bullet(doc, "Optional: demo video, Tile38 running for a performance comparison slide.")

    heading(doc, "7. Document history", 1)
    add_table(
        doc,
        ["Version", "Date", "Notes"],
        [
            ["1.0", "30 Sep 2026", "First sprint DOCX: how-to + Steps 1–7 + polish/Help."],
            ["1.1", "30 Sep 2026", "Added development-process discussion for every sprint."],
        ],
    )

    footer = doc.sections[0].footer
    fp = footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    fr = fp.add_run("PharmaLocate · Sprint Documentation v1.1 · Development process per sprint")
    set_run(fr, size=8, color=MUTED)

    doc.save(OUT)
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    main()
