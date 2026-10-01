# -*- coding: utf-8 -*-
"""Generate PharmaLocate_Progress_Report.docx — whole development since Sprint 1."""
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

OUT = Path(__file__).resolve().parents[1] / "PharmaLocate_Progress_Report.docx"
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
    tcPr = cell._tc.get_or_add_tcPr()
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


def para(doc, text, *, bold=False, size=11):
    p = doc.add_paragraph()
    p.paragraph_format.space_after = Pt(8)
    p.paragraph_format.line_spacing_rule = WD_LINE_SPACING.SINGLE
    run = p.add_run(text)
    set_run(run, size=size, bold=bold)
    return p


def bullet(doc, text):
    p = doc.add_paragraph(text, style="List Bullet")
    p.paragraph_format.space_after = Pt(3)
    for run in p.runs:
        set_run(run, size=11)
    return p


def main():
    doc = Document()
    for section in doc.sections:
        section.top_margin = Cm(2)
        section.bottom_margin = Cm(2)
        section.left_margin = Cm(2.2)
        section.right_margin = Cm(2.2)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    t.paragraph_format.space_before = Pt(36)
    r = t.add_run("PHARMALOCATE")
    set_run(r, size=14, bold=True, color=GREEN)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("Progress Report")
    set_run(r, size=28, bold=True, color=DARK)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("Web-Based Pharmacy Inquiry System with Geofencing")
    set_run(r, size=13, color=MUTED)
    r.italic = True

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("Covering development from Step 1 (first sprint) through Sprint 8")
    set_run(r, size=12, color=MUTED)

    add_table(
        doc,
        ["Field", "Value"],
        [
            ["Document type", "Capstone progress report (whole development period)"],
            ["Period covered", "July 2026 — 1 October 2026"],
            ["Report date", "1 October 2026"],
            ["Version", "1.0"],
            ["Active workspace", "Programming4 (Laravel 13 + 3-file frontend + MySQL)"],
            ["Related documents", "Sprint Documentation v1.1; Documentation v1.0–v1.3; Sprint 7 execution report"],
        ],
    )

    heading(doc, "1. Executive summary", 1)
    para(
        doc,
        "PharmaLocate is a capstone web system that helps people around Tarlac Provincial "
        "Hospital find nearby pharmacies, see medicine stock, and send inquiries to staff. "
        "Development started by locking a three-file user interface (Step 1) and has now "
        "produced a live Laravel and MySQL application with geofencing, POS, role-based admin "
        "tools, ISO/IEC 25010 test cases, and a rule-based Help assistant.",
    )
    para(
        doc,
        "Implementation of the product (Steps 1–6 and UI polish) is complete for the agreed "
        "scope: two demo pharmacies, one 5 km geofence, ten medicines, and three roles "
        "(customer, staff, admin). Formal evaluation is largely automated (29/29 PHPUnit tests) "
        "but still needs remaining black-box screenshots for the printed binder. Help-assistant "
        "answers are unofficial until the validated question list is substituted.",
    )
    para(doc, "Headline status", bold=True)
    add_table(
        doc,
        ["Area", "Status", "Estimate"],
        [
            ["Customer module (browse, map, inquiries, auth)", "Complete", "100%"],
            ["Admin / staff module (dashboard, stock, POS, users, settings, export)", "Complete", "100%"],
            ["Geofencing (zones, filter, maps, Tile38 fallback)", "Complete", "100%"],
            ["ISO/IEC 25010 testing & evidence", "In progress", "~78% (62/80 cases verified)"],
            ["UI polish & Help assistant", "Complete (draft FAQs)", "~95%"],
            ["Overall capstone delivery (product + evaluation + binder)", "Near close-out", "~90%"],
        ],
    )

    heading(doc, "2. Project identification and method", 1)
    para(
        doc,
        "The team built in named sprints mapped to capstone Steps 1–7. “Step” is a phase of "
        "the paper; “Sprint” is a slice inside a phase (especially 5.0–5.6 and 7.0–7.4). "
        "Work was front-to-back: freeze screens, plan APIs, connect customers, then staff, "
        "then location, then POS, then tests, then polish. Finished steps were frozen in "
        "Programming2 and Programming3; Programming4 is the living tree.",
    )
    para(doc, "Standing engineering rule", bold=True)
    bullet(doc, "Change the smallest vertical slice (API and/or UI).")
    bullet(doc, "Copy PharmaLocateFrontEnd.html, styles.css, and app.js to backend/public/.")
    bullet(doc, "Hard-refresh the browser (Ctrl+F5).")
    bullet(doc, "Verify as the role that owns the feature (guest, maria, staff, or admin).")
    para(
        doc,
        "Two run modes were kept throughout: demo (open the HTML file; static data) and live "
        "(php artisan serve at http://127.0.0.1:8000; MySQL via /api).",
    )

    heading(doc, "3. Scope of this report", 1)
    para(doc, "In scope: all development from the first sprint (frontend lock) to 1 October 2026, including Help FAQs and polish.")
    para(doc, "Out of scope for the product (unchanged paper limits): nationwide catalog, push notifications, production load tests, generative AI chatbot, unlimited pharmacies.")
    para(doc, "Demo dataset used for all progress claims: SpaRx Pharmacy, Magic 8 Pharmacy, Tarlac Provincial Hospital Zone (5 km), 10 medicines.")

    heading(doc, "4. Objectives versus accomplishment", 1)
    para(doc, "Customer module (Chapter objectives)", bold=True)
    add_table(
        doc,
        ["Objective", "Result as of 1 Oct 2026"],
        [
            ["Interactive map + GPS", "Done — Leaflet/OSM; GPS optional; hospital default"],
            ["Inquiry services", "Done — customer submit/track; staff/admin reply"],
            ["Information access", "Done — medicines, pharmacies, hours, directions"],
            ["Real-time availability", "Done — available / low / out of stock from DB"],
            ["Geofence-based locator", "Done — server filters by containing zone"],
        ],
    )
    para(doc, "Admin module (Chapter objectives)", bold=True)
    add_table(
        doc,
        ["Objective", "Result as of 1 Oct 2026"],
        [
            ["User management", "Done — list/edit role and staff pharmacy; last-admin protected"],
            ["Dashboard", "Done — live counts, activity, geofence stats"],
            ["Pharmacy profiles", "Done — create, edit, deactivate; GPS and hours"],
            ["Geofence assignment and CRUD", "Done — admin UI + API; staff read-only"],
            ["Settings", "Done — threshold, notification flags, backup schedule fields"],
            ["Notifications (push/email send)", "Not implemented — flags stored only"],
            ["Backup / export", "Done — JSON/CSV export by scope (scheduled backup is setting only)"],
        ],
    )
    para(
        doc,
        "Design change during Step 4: priority user types (senior, PWD, pregnant, parent) were "
        "removed from signup and inquiry sorting. Roles admin, staff, and customer remain. "
        "Help assistant was added in Sprint 8 as a rule-based FAQ (advisor: no AI).",
    )

    heading(doc, "5. Chronological progress since the first sprint", 1)
    para(
        doc,
        "The table is the period history. Percentages are for that sprint’s own goal, not the whole project.",
    )
    add_table(
        doc,
        ["When", "Sprint / step", "What progressed", "Close-out"],
        [
            ["Jul 2026", "Step 1 — Frontend lock", "Guest, Auth, Admin UI; 10-medicine demo; tabs and sidebar", "100% — HTML/CSS/JS contract"],
            ["Jul 2026", "Step 2 — Backend map", "Screen → FR → API plan; P1–P5 order agreed", "100% — planning document"],
            ["Jul 2026", "Step 3 — Live API (P1)", "Laravel, MySQL, Sanctum, availability, pharmacies, inquiries", "100% — live guest browse + login"],
            ["Jul 2026", "Step 4 — Admin core (P2)", "Dashboard, replies, stock; staff scope; priority types removed", "100% — admin/staff auto-open"],
            ["Jul 2026", "Sprint 5.0", "Baseline: XAMPP, serve, login, live tabs", "100% — no code; verification"],
            ["Jul 2026", "Sprint 5.1", "Pharmacy CRUD API + admin form", "100%"],
            ["Jul 2026", "Sprint 5.2", "Geofence API + public zones + seed hospital zone", "100%"],
            ["Jul 2026", "Sprint 5.3", "Leaflet customer map; GPS fallback; CSS map fix", "100%"],
            ["Jul 2026", "Sprint 5.4", "Server geofence filter; empty-zone notice", "100% — research core"],
            ["2026", "Sprint 5.5", "Admin geofence UI and admin map", "100%"],
            ["2026", "Sprint 5.6", "Optional Tile38; haversine fallback", "100% with fallback"],
            ["2026", "Step 6", "POS stock decrement, users, settings, export", "100%"],
            ["Aug 2026", "Sprint 7.0–7.4", "ISO plan, PHPUnit 29/29, execution report", "~78% cases; photos left"],
            ["Sep 2026", "Sprint 8", "Loader, tab motion, Help EN/FIL, polish bugs", "~95% — validated FAQs pending"],
        ],
    )

    heading(doc, "6. Narrative of progress (readable history)", 1)
    heading(doc, "6.1 Foundation (Steps 1–3)", 2)
    para(
        doc,
        "Step 1 froze what users would see so later coding would not chase a moving layout. "
        "Step 2 refused to write APIs until every screen had a named objective and a priority. "
        "Customer live data was Priority 1; geofencing was Priority 3. Step 3 connected the "
        "frozen UI to Laravel: guests could already see stock without an account; maria could "
        "log in and inquire. Dual demo/live mode and the copy-to-public workflow started here "
        "and never stopped.",
    )
    heading(doc, "6.2 Operations for staff (Step 4)", 2)
    para(
        doc,
        "Programming2 was frozen as the v1.0 snapshot. Admin work in Programming3/4 made "
        "dashboard numbers, inquiry replies, and stock edits real. Middleware blocked customers "
        "from /api/admin. Staff can only touch their assigned pharmacy. Automatic Admin view "
        "after login reduced demo friction for advisors.",
    )
    heading(doc, "6.3 Geofencing (Step 5)", 2)
    para(
        doc,
        "The team did not draw a map until pharmacies and zones existed (5.1–5.2), then replaced "
        "the SVG with Leaflet (5.3), then enforced zones on the server (5.4). That order avoided "
        "a pretty map with untrustworthy lists. Admin zone editing (5.5) reused the APIs. Tile38 "
        "(5.6) was kept optional so a classroom without Redis still demos two pharmacies at the "
        "hospital default (15.4870, 120.5960).",
    )
    heading(doc, "6.4 POS and extras (Step 6)", 2)
    para(
        doc,
        "Sales were implemented before settings because a POS line item changes the same stock "
        "customers browse. Oversell is rejected. Admin may choose a pharmacy at the register; "
        "staff cannot. User management, settings, and CSV/JSON export turned Step 1 placeholder "
        "sidebar items into working screens. Push notification delivery remains out of scope.",
    )
    heading(doc, "6.5 Evaluation (Sprint 7)", 2)
    para(
        doc,
        "Testing was a planned step, not leftover work. Documentation v1.3 mapped ISO/IEC 25010 "
        "characteristics to BB, WB, and API IDs. PHPUnit covers health, auth, geofence in/out, "
        "inquiries, POS decrement, staff 403s, settings, and export (29 tests, 0 fail, August 2026 "
        "re-run). About 16 black-box screenshots remain for the binder. SQLite drivers were "
        "enabled for artisan test; live demos still use MySQL.",
    )
    heading(doc, "6.6 Close-out polish (Sprint 8)", 2)
    para(
        doc,
        "A Help assistant was required without AI: first an open/close shell, then 18 unofficial "
        "bilingual presets, later a swap of CHATBOT_FAQS when validated copy arrives. Loaders, "
        "tab animation, and deferred GPS improved first paint. A bug-fix pass removed undefined "
        "navbar names, fake home/inquiry samples, and Help/Close toggle issues. The system is "
        "in a state suitable for remaining binder screenshots and defense rehearsal.",
    )

    heading(doc, "7. What the system can do today", 1)
    para(doc, "Guest / customer", bold=True)
    bullet(doc, "Home, Pharmacies (map + hours + directions), Medicines (search/filter), Inquiries.")
    bullet(doc, "Register and log in (username or email); logout clears the token.")
    bullet(doc, "See only pharmacies assigned to a geofence that contains the user (or hospital default).")
    bullet(doc, "Help assistant: pick EN/FIL preset questions; jump to Pharmacies or Inquiries.")
    para(doc, "Staff", bold=True)
    bullet(doc, "Dashboard, reply to inquiries, stock and POS for the assigned pharmacy only.")
    bullet(doc, "View geofences; cannot create users, change system settings, or export.")
    para(doc, "Admin", bold=True)
    bullet(doc, "All staff functions across pharmacies; pharmacy and geofence CRUD.")
    bullet(doc, "User roles; settings; JSON/CSV export.")
    para(doc, "Demo accounts (password: password)", bold=True)
    add_table(
        doc,
        ["Username", "Role", "Use in progress demos"],
        [
            ["maria", "Customer", "Inquiries and logged-in customer chrome"],
            ["admin", "Administrator", "Full sidebar; geofences; export"],
            ["sparx_staff", "Staff (SpaRx)", "Scoped POS and stock"],
            ["magic8_staff", "Staff (Magic 8)", "Second-pharmacy staff path"],
        ],
    )

    heading(doc, "8. Metrics and evidence", 1)
    add_table(
        doc,
        ["Metric", "Value"],
        [
            ["PHPUnit (Sprint 7 filter)", "29 passed / 29 (0 failed)"],
            ["Mapped test cases verified", "62 of 80 (~77.5%)"],
            ["API cases API-01–API-20", "20/20 automated pass"],
            ["Seed pharmacies / medicines / zones", "2 / 10 / 1 (5 km)"],
            ["User roles implemented", "3 (customer, staff, admin)"],
            ["Frontend source files", "3 (+ copies in backend/public)"],
            ["Live health check", "GET /api/health → status ok (Tile38 may be unavailable)"],
        ],
    )
    para(
        doc,
        "Primary written evidence: Documentation v1.0 (Steps 1–3), v1.1 (Step 4), v1.2 (Sprints 5.0–5.4), "
        "v1.3 (test plan), Sprint7_Test_Execution_Report.md, PharmaLocate_Sprint_Documentation.docx v1.1, "
        "this progress report. Documentation v2.0 still lags later steps and should not be cited as current status.",
    )

    heading(doc, "9. Issues, risks, and how they were handled", 1)
    add_table(
        doc,
        ["Issue / risk", "Impact", "Handling"],
        [
            ["PHP 8.2 vs Laravel 8.3+ / teammate machines", "Serve or tests fail", "setup_guide; PHP 8.3+ required"],
            ["pdo_sqlite missing", "PHPUnit cannot run", "Enabled in php.ini for Sprint 7"],
            ["MySQL / XAMPP down", "Live UI falls back or errors", "5.0 checklist; start MySQL before demos"],
            ["Tile38 not installed", "Paper DC2 optional", "Haversine fallback; health shows unavailable"],
            ["Leaflet CSS overflow", "Broken map chrome", "CSS fix + public sync + Ctrl+F5"],
            ["Loader never finishing", "Blank first paint", "revealApp + 4s failsafe; GPS deferred"],
            ["Navbar “undefined”", "Login looked broken", "displayName; session parse; demo user object"],
            ["Priority types vs paper", "Scope conflict", "Removed in Step 4; roles kept"],
            ["Help FAQs unofficial", "Content risk", "Draft copy; swap CHATBOT_FAQS later"],
            ["Doc v2.0 stale", "Advisor confusion", "Cite v1.3 + this report + sprint DOCX"],
            ["Inquiry status “replied”", "API validation fail", "Use resolved; UI still says Replied"],
        ],
    )

    heading(doc, "10. Work remaining (next reporting period)", 1)
    para(doc, "Required for binder / defense close-out", bold=True)
    numbered = [
        "Capture remaining Sprint 7 black-box screenshots (see execution report Section 4).",
        "Replace unofficial Help questions with the validated bilingual set (CHATBOT_FAQS only).",
        "Update or retire Documentation v2.0 so it matches Programming4.",
        "Confirm teammate laptops: PHP 8.3+, XAMPP MySQL, copy-public workflow, Ctrl+F5.",
    ]
    for i, text in enumerate(numbered, 1):
        p = doc.add_paragraph(f"{i}. {text}")
        p.paragraph_format.space_after = Pt(3)
        for run in p.runs:
            set_run(run, size=11)
    para(doc, "Optional / nice-to-have", bold=True)
    bullet(doc, "Defense demo video (guest map → maria inquiry → admin reply → POS stock drop).")
    bullet(doc, "Tile38 on for a side-by-side reliability slide.")
    bullet(doc, "Scheduled backup runner (UI flag exists; file dump is still manual export).")
    bullet(doc, "Actual email/push notifications (not in current API).")

    heading(doc, "11. Planned demonstration path", 1)
    para(
        doc,
        "Suggested 8-minute path for advisors, using the progress described above:",
    )
    bullet(doc, "Guest Home and Medicines (live stock) — Step 3.")
    bullet(doc, "Pharmacies map, zone notice, two pins, directions — Steps 5.3–5.4.")
    bullet(doc, "Help: one preset, EN/FIL, open Inquiries — Sprint 8.")
    bullet(doc, "Log in maria; submit inquiry — Steps 3–4.")
    bullet(doc, "Log in admin; dashboard; reply — Step 4.")
    bullet(doc, "POS sale; Medicines tab quantity drops — Step 6.")
    bullet(doc, "Mention PHPUnit 29/29 and remaining screenshots — Sprint 7.")

    heading(doc, "12. Conclusion", 1)
    para(
        doc,
        "From the first sprint to 1 October 2026, PharmaLocate moved from a static three-file "
        "prototype to a role-based, geofenced pharmacy inquiry system with inventory-linked POS "
        "and documented tests. The remaining gap is evidence packaging (screenshots, validated "
        "FAQs, documentation alignment), not a missing core module. The team is ready for "
        "binder completion and defense rehearsal on the Programming4 live server.",
    )

    heading(doc, "13. Document history and sign-off", 1)
    add_table(
        doc,
        ["Version", "Date", "Notes"],
        [["1.0", "1 Oct 2026", "First whole-period progress report since Step 1."]],
    )
    add_table(
        doc,
        ["Role", "Name", "Signature", "Date"],
        [
            ["Prepared by", "", "", "1 Oct 2026"],
            ["Team member review", "", "", ""],
            ["Adviser noted", "", "", ""],
        ],
    )

    fp = doc.sections[0].footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    fr = fp.add_run("PharmaLocate · Progress Report v1.0 · July 2026 – 1 October 2026")
    set_run(fr, size=8, color=MUTED)

    doc.save(OUT)
    print(f"Wrote {OUT}")


if __name__ == "__main__":
    main()
