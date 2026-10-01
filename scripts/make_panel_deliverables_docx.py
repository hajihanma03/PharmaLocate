# -*- coding: utf-8 -*-
"""TCP Form 12 — pointer from each recommendation to its deliverable."""
from pathlib import Path

from docx import Document
from docx.enum.text import WD_ALIGN_PARAGRAPH
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor

OUT = Path(__file__).resolve().parents[1] / "Panel_Recommendations_Deliverables.docx"
GREEN = RGBColor(0x0F, 0x6E, 0x56)
DARK = RGBColor(0x1A, 0x26, 0x20)
MUTED = RGBColor(0x4D, 0x6B, 0x60)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
HEADER = "1D9E75"
ALT = "E8F5EE"


def shade(cell, hex_color):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), hex_color)
    shd.set(qn("w:val"), "clear")
    tcPr.append(shd)


def cell_text(cell, text, *, bold=False, size=9, color=DARK, white=False):
    cell.text = ""
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(2)
    p.paragraph_format.space_after = Pt(2)
    r = p.add_run(text)
    r.font.size = Pt(size)
    r.bold = bold
    r.font.color.rgb = WHITE if white else color
    r.font.name = "Calibri"


def add_table(doc, headers, rows):
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.style = "Table Grid"
    for i, h in enumerate(headers):
        c = table.rows[0].cells[i]
        shade(c, HEADER)
        cell_text(c, h, bold=True, size=9, white=True)
    for ri, row in enumerate(rows):
        for ci, val in enumerate(row):
            c = table.rows[ri + 1].cells[ci]
            if ri % 2:
                shade(c, ALT)
            cell_text(c, val, size=9)
    doc.add_paragraph()


def heading(doc, text, level=1):
    p = doc.add_heading(text, level=level)
    for run in p.runs:
        run.font.color.rgb = GREEN


def para(doc, text):
    p = doc.add_paragraph()
    r = p.add_run(text)
    r.font.size = Pt(11)
    r.font.name = "Calibri"
    r.font.color.rgb = DARK
    return p


def main():
    doc = Document()
    for s in doc.sections:
        s.top_margin = Cm(1.8)
        s.bottom_margin = Cm(1.8)
        s.left_margin = Cm(1.6)
        s.right_margin = Cm(1.6)

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("PHARMALOCATE")
    r.bold = True
    r.font.size = Pt(12)
    r.font.color.rgb = GREEN

    t = doc.add_paragraph()
    t.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = t.add_run("TCP Form 12 — Deliverables per recommendation")
    r.bold = True
    r.font.size = Pt(20)
    r.font.color.rgb = DARK

    para(
        doc,
        "This sheet points the panel from each CCS TCP Form 12 recommendation to the exact "
        "deliverable to open. Live app: http://127.0.0.1:8000 after php artisan serve. "
        "Workspace: Programming4. Source of recommendations: CCS TCP FORM12 – recoss ng panels.pdf.",
    )

    heading(doc, "1. How to use this sheet in defense", 1)
    para(
        doc,
        "Column “Open this deliverable” is the click path or file. Column “Show the panel” is "
        "the one thing to highlight. Manuscript items are files the team still edits in the thesis; "
        "they are listed so nothing is lost.",
    )

    heading(doc, "2. Title", 1)
    add_table(
        doc,
        ["Recommendation (Nicolas Z. Diaz Jr.)", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "Include POS in the title; use: A Web-Based Pharmacy Inquiry System with Geofencing for Real-Time Drug Availability, Sale & Inventory Checking",
                "System UI + thesis title page",
                "Browser tab title at http://127.0.0.1:8000\nNavbar subtitle under “PharmaLocate”\nLogin card subtitle\nFile: PharmaLocateFrontEnd.html (lines: <title>, .nav-brand-sub, .auth-sub)",
                "Full recommended wording in the tab title; navbar: “Availability, sale & inventory · geofencing”; POS is named in the title, not only in the sidebar.",
            ],
        ],
    )

    heading(doc, "3. Chapter 1 — Introduction", 1)
    add_table(
        doc,
        ["Recommendation", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "Project context: stronger problem; connect to SDG (Jiane Diamzon)",
                "Manuscript",
                "Capstone paper — Chapter 1 Project Context",
                "Problem statement tied to SDG (health / access). Not a code file.",
            ],
            [
                "Introduction too long (Jonathan Alug)",
                "Manuscript",
                "Capstone paper — Chapter 1 Introduction (short version)",
                "Shorter intro; vital facts kept. System title is already shortened in the UI subtitle.",
            ],
            [
                "Purpose: add scope; do not include pharmacies inside TPH",
                "System UI + API + DB",
                "Home tab — Scope banner under the hero\nAdmin → Manage pharmacies → checkbox “Inside Tarlac Provincial Hospital”\nAPI: GET /api/pharmacies and GET /api/availability (omit inside_tph)\nFiles: Pharmacy.php scopePublicLocator(); PharmacyController.php; AvailabilityController.php; migration 2026_10_01_000001_add_inside_tph_to_pharmacies_table.php\nTest: php artisan test --filter test_panel_inside_tph",
                "Banner: community pharmacies around TPH, not inside TPH. Seeded SpaRx and Magic 8 stay visible; a TPH-compound store would not appear to users.",
            ],
            [
                "Objectives: proper format for Objective 2; include inventory management in 1.3",
                "System UI + manuscript 1.3",
                "Admin sidebar after login as admin / password\n  • Inventory management (was Stock)\n  • POS — sales\nFiles: PharmaLocateFrontEnd.html sidebar + #admin-stock / #admin-pos headings\nPaper: Chapter 1 section 1.3 objectives list",
                "Click Inventory management (stock table) then POS — sales (cart / Process sale). Put the same labels in 1.3 of the paper.",
            ],
            [
                "Scope and limitation: White Box Testing in table form",
                "Documentation (already built) + manuscript table",
                "Programming4/Documentation_v1.3.md — Section 40 white-box cases\nProgramming4/backend/tests/Unit/Sprint7WhiteBoxTest.php\nProgramming4/Sprint7_Test_Execution_Report.md\nCopy the WB-01–WB-12 table into Chapter 1 Scope and Limitation as Form 12 asked",
                "Printed/PDF table of WB parameters. Running proof: php artisan test --filter Sprint7",
            ],
        ],
    )

    heading(doc, "4. Chapter 2 — Related literature", 1)
    add_table(
        doc,
        ["Recommendation", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "Remove your own study from LLM (local literature)",
                "Manuscript",
                "Chapter 2 — Local literature (revised)",
                "No self-citation of this capstone inside LLM.",
            ],
            [
                "Revise conceptual framework (IPOO)",
                "Manuscript figure",
                "Chapter 2 — Conceptual framework figure (Input–Process–Output–Outcome)",
                "Named figure (panel also asked figure names). Software IPO is: browser → Laravel /api → MySQL → UI.",
            ],
            [
                "Add definitions of the features",
                "Manuscript + live labels",
                "Chapter 2 — Definition of Terms\nMatch terms to UI: Inquiries, Pharmacies (geofence locator), Medicines (availability), Inventory management, POS — sales, Geofences, Users, Help assistant",
                "Each defined feature has a screen in Programming4 (see Section 8 storyboard path).",
            ],
        ],
    )

    heading(doc, "5. Chapter 3 — Technical background", 1)
    add_table(
        doc,
        ["Recommendation", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "Block diagram system architecture (make it per icon)",
                "Manuscript figure + running stack",
                "Chapter 3 architecture figure (one icon per block)\nLive stack to label: Chrome/Edge → PharmaLocateFrontEnd.html + styles.css + app.js → Laravel 13 (backend/) → MySQL pharmalocate → optional Tile38",
                "Icons for User device, Web UI, API, Database, Geofence/map. GET /api/health proves API + tile38 status.",
            ],
            [
                "Sources of data — connect to the objectives",
                "Manuscript + field",
                "Chapter 3 Sources of Data table mapped to 1.3 objectives\nParticipating stores in seed/UI: SpaRx Pharmacy, Magic 8 Pharmacy",
                "Each data source row cites which objective it supports (availability, inquiry, geofence, inventory/POS).",
            ],
        ],
    )

    heading(doc, "6. Chapter 4 — Methodology", 1)
    add_table(
        doc,
        ["Recommendation", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "SDLC: add backlog to the development",
                "Project documents",
                "Programming4/PharmaLocate_Sprint_Documentation.docx (v1.1 process + sprint backlog)\nProgramming4/PharmaLocate_Progress_Report.docx (whole-period progress)\nProgramming4/scripts/make_sprint_docx.py and make_progress_report_docx.py (regenerate)",
                "Master sprint board (Steps 1–8) is the development backlog Form 12 asked for.",
            ],
            [
                "Storyboard: add desktop view; add all features",
                "System (desktop UI) + manuscript storyboard figures",
                "Open http://127.0.0.1:8000 at desktop width (≥1100 px)\nGuest: Home, Pharmacies (map + geofence parameters), Medicines, Inquiries, Help\nAuth: Log in / Sign up\nAdmin (admin / password): Dashboard, inquiries, Inventory, POS, pharmacies, geofences, Users, settings, backup\nFiles: PharmaLocateFrontEnd.html, styles.css (desktop + @media max-width 800px)\nPaper: storyboard figures named per screen (desktop)",
                "Walk every tab and every sidebar item on a laptop/desktop browser — that is the live storyboard.",
            ],
        ],
    )

    heading(doc, "7. Others (Diamzon & Alug)", 1)
    add_table(
        doc,
        ["Recommendation", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "Remove the border in the references",
                "Manuscript",
                "Chapter References list (no boxed/bordered style)",
                "Formatting in the thesis file only.",
            ],
            [
                "Add a figure name for each figure",
                "Manuscript",
                "All Chapter figures: Figure x.x Title",
                "Every screenshot/diagram captioned.",
            ],
            [
                "Specify the parameter of the geofencing",
                "System UI",
                "Guest → Pharmacies → card “Geofencing parameters” (#geofence-params)\nAdmin → Geofences → notice + form (center lat/lng, radius 500–10000 m, default 5000)\nConstants: app.js TARLAC_CENTER 15.487, 120.596\nLogic: PharmacyController + Geofence::containsPoint / haversine; optional Tile38",
                "Read aloud: circular zone, default 5 km around TPH, admin range 500–10,000 m, TPH-internal pharmacies excluded.",
            ],
            [
                "Make it general instead of Senior Citizens, Pregnant women, etc.",
                "System UI (already + copy)",
                "Home hero: “all users” / “Users (general public)”\nHelp welcome copy in app.js chatbotCopy()\nHistorical removal: migration 2026_07_20_000001_remove_user_priority_types.php\nDocumentation_v1.1.md §17",
                "No priority chips on Sign up. Roles are customer, staff, admin only.",
            ],
            [
                "Consider the concept of the devices",
                "System CSS + Home pills",
                "Home scope pill: “Desktop & mobile browsers”\nstyles.css @media (max-width: 800px) for stacked map/inquiry/POS\n<meta name=\"viewport\"> in PharmaLocateFrontEnd.html",
                "Resize the browser: desktop storyboard vs stacked mobile. Target users use ordinary browsers, not a kiosk-only UI.",
            ],
        ],
    )

    heading(doc, "8. Respondents of the study (Jonathan Alug)", 1)
    add_table(
        doc,
        ["Recommendation", "Kind", "Open this deliverable", "Show the panel"],
        [
            [
                "Breakdown and add table",
                "Manuscript",
                "Chapter Respondents — breakdown table (who / how many / role)",
                "Tabulate respondents; do not leave as a paragraph only.",
            ],
            [
                "Interview should be conducted from the pharmacies mentioned",
                "Field work + paper",
                "Interview notes from SpaRx Pharmacy and Magic 8 Pharmacy (the stores in the system seed and UI)\nAddresses in DatabaseSeeder / Admin → Manage pharmacies",
                "Evidence that interviews happened on those premises, not generic “pharmacies.”",
            ],
            [
                "Insert table showing the evaluation rating of the respondents",
                "Manuscript (+ optional ISO scores from Sprint 7)",
                "Chapter table: Evaluation rating of respondents\nMay cross-walk ISO/IEC 25010 characteristics from Documentation_v1.3.md §37",
                "Numeric/Likert ratings in a table, not only narrative.",
            ],
        ],
    )

    heading(doc, "9. Click-path checklist (system deliverables only)", 1)
    add_table(
        doc,
        ["#", "Deliverable", "Where"],
        [
            ["1", "Recommended system title", "Browser tab + navbar + login subtitle"],
            ["2", "Scope: no pharmacies inside TPH", "Home Scope banner; Admin pharmacy checkbox; GET /api/pharmacies"],
            ["3", "Inventory management (1.3)", "Admin sidebar → Inventory management"],
            ["4", "Sale / POS in the product", "Admin sidebar → POS — sales; title includes Sale"],
            ["5", "Geofence parameters", "Pharmacies tab parameter card; Admin → Geofences form"],
            ["6", "General users", "Home hero / pills; Sign up with no priority types"],
            ["7", "Desktop + other devices", "Desktop window + resize below 800 px"],
            ["8", "All features storyboard", "All guest tabs + all admin sidebar items"],
            ["9", "Development backlog", "PharmaLocate_Sprint_Documentation.docx"],
            ["10", "White-box test evidence", "Documentation_v1.3.md §40; php artisan test --filter Sprint7"],
            ["11", "TPH exclusion automated proof", "php artisan test --filter test_panel_inside_tph"],
        ],
    )

    heading(doc, "10. File index", 1)
    add_table(
        doc,
        ["File / location", "Used for"],
        [
            ["PharmaLocateFrontEnd.html", "Title, scope banner, geofence params, inventory/POS labels, pharmacy TPH checkbox"],
            ["styles.css", "Scope/geofence cards; desktop and mobile layout"],
            ["app.js", "updateGeofenceParams(); inside_tph save; Help copy for all users"],
            ["backend/app/Models/Pharmacy.php", "inside_tph; scopePublicLocator()"],
            ["backend/app/Http/Controllers/PharmacyController.php", "Public list excludes inside TPH"],
            ["backend/app/Http/Controllers/AvailabilityController.php", "Stock grid excludes inside TPH"],
            ["backend/app/Http/Controllers/AdminPharmacyController.php", "Validates inside_tph"],
            ["backend/database/migrations/2026_10_01_000001_add_inside_tph_to_pharmacies_table.php", "Database column"],
            ["backend/tests/Feature/Sprint7Test.php", "test_panel_inside_tph_pharmacies_are_hidden_from_public_locator"],
            ["Documentation_v1.3.md", "White-box / ISO tables to paste into Chapter 1"],
            ["PharmaLocate_Sprint_Documentation.docx", "SDLC backlog"],
            ["PharmaLocate_Progress_Report.docx", "Progress since first sprint"],
            ["Panel_Recommendations_Status.docx", "Satisfied vs manuscript-only (summary)"],
            ["This file", "Pointer from each Form 12 line to a deliverable"],
        ],
    )

    p = doc.add_paragraph()
    r = p.add_run(
        "Live MySQL: from Programming4/backend run php artisan migrate so inside_tph exists. "
        "Then copy HTML/CSS/JS to backend/public/ if you edit them, and Ctrl+F5."
    )
    r.font.size = Pt(10)
    r.font.color.rgb = MUTED

    fp = doc.sections[0].footer.paragraphs[0]
    fp.alignment = WD_ALIGN_PARAGRAPH.CENTER
    fr = fp.add_run("PharmaLocate · TCP Form 12 deliverable pointer · 1 October 2026")
    fr.font.size = Pt(8)
    fr.font.color.rgb = MUTED

    doc.save(OUT)
    print("Wrote", OUT)


if __name__ == "__main__":
    main()
