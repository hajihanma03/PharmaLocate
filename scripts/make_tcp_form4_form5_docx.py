# -*- coding: utf-8 -*-
"""Fill CCS TCP Form 4 (tech adviser) and Form 5 (subject teacher) progress reports."""
from pathlib import Path

from docx import Document
from docx.enum.table import WD_TABLE_ALIGNMENT
from docx.enum.text import WD_ALIGN_PARAGRAPH, WD_LINE_SPACING
from docx.oxml import OxmlElement
from docx.oxml.ns import qn
from docx.shared import Cm, Pt, RGBColor, Emu

OUT_DIR = Path(__file__).resolve().parents[1]
DOWNLOADS = Path(r"C:\Users\ADMIN\Downloads")
GREEN = RGBColor(0x0F, 0x6E, 0x56)
DARK = RGBColor(0x1A, 0x1A, 0x1A)
MUTED = RGBColor(0x44, 0x44, 0x44)
WHITE = RGBColor(0xFF, 0xFF, 0xFF)
HEADER_BG = "1B5E20"
ALT = "E8F5E9"


def set_run(run, size=11, bold=False, color=DARK, font="Times New Roman"):
    run.font.name = font
    run._element.rPr.rFonts.set(qn("w:eastAsia"), font)
    run.font.size = Pt(size)
    run.bold = bold
    run.font.color.rgb = color


def shade(cell, hex_color):
    tcPr = cell._tc.get_or_add_tcPr()
    shd = OxmlElement("w:shd")
    shd.set(qn("w:fill"), hex_color)
    shd.set(qn("w:val"), "clear")
    tcPr.append(shd)


def set_cell_border(cell):
    tc = cell._tc
    tcPr = tc.get_or_add_tcPr()
    tcBorders = OxmlElement("w:tcBorders")
    for edge in ("top", "left", "bottom", "right"):
        el = OxmlElement(f"w:{edge}")
        el.set(qn("w:val"), "single")
        el.set(qn("w:sz"), "4")
        el.set(qn("w:space"), "0")
        el.set(qn("w:color"), "000000")
        tcBorders.append(el)
    tcPr.append(tcBorders)


def cell_text(cell, text, *, bold=False, size=10, color=DARK, center=False, white=False):
    cell.text = ""
    p = cell.paragraphs[0]
    p.paragraph_format.space_before = Pt(3)
    p.paragraph_format.space_after = Pt(3)
    p.paragraph_format.line_spacing = 1.08
    if center:
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    run = p.add_run(text)
    set_run(run, size=size, bold=bold, color=WHITE if white else color)
    set_cell_border(cell)


def footer_form(doc, form_no, page_note):
    p = doc.add_paragraph()
    p.paragraph_format.space_before = Pt(10)
    run = p.add_run(
        f"Form No.: {form_no}     Revision No.: 02     Effectivity Date: September 01, 2017     {page_note}"
    )
    set_run(run, size=8, color=MUTED)


def letterhead(doc, form_code, form_name):
    for text, size, bold in [
        ("TARLAC STATE UNIVERSITY", 14, True),
        ("COLLEGE OF COMPUTER STUDIES", 12, True),
        (form_code, 11, True),
        (form_name, 16, True),
    ]:
        p = doc.add_paragraph()
        p.alignment = WD_ALIGN_PARAGRAPH.CENTER
        p.paragraph_format.space_after = Pt(0)
        p.paragraph_format.space_before = Pt(0)
        r = p.add_run(text)
        set_run(r, size=size, bold=bold, color=GREEN if size >= 16 else DARK)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("Academic Year 2025–2026  ·  Second Semester")
    set_run(r, size=10, color=MUTED)


def meta_table(doc, rows):
    table = doc.add_table(rows=len(rows), cols=2)
    table.autofit = True
    for i, (k, v) in enumerate(rows):
        cell_text(table.rows[i].cells[0], k, bold=True, size=10)
        cell_text(table.rows[i].cells[1], v, size=10)
        table.rows[i].cells[0].width = Cm(4.5)
        table.rows[i].cells[1].width = Cm(12.5)
    doc.add_paragraph()


def fill_table(doc, headers, rows, widths):
    table = doc.add_table(rows=1 + len(rows), cols=len(headers))
    table.alignment = WD_TABLE_ALIGNMENT.CENTER
    for i, h in enumerate(headers):
        c = table.rows[0].cells[i]
        shade(c, HEADER_BG)
        cell_text(c, h, bold=True, size=9, white=True, center=True)
        c.width = Cm(widths[i])
    for ri, row in enumerate(rows):
        for ci, val in enumerate(row):
            c = table.rows[ri + 1].cells[ci]
            if ri % 2:
                shade(c, ALT)
            cell_text(c, val, size=9, center=(ci == 0 or ci == len(headers) - 1))
            c.width = Cm(widths[ci])
    doc.add_paragraph()


def sign_block(doc, left_title, left_name, right_title, right_name):
    table = doc.add_table(rows=4, cols=2)
    labels = [
        ("Prepared by the proponents:", "Noted:"),
        ("", ""),
        (left_name, right_name),
        (left_title, right_title),
    ]
    for i, (a, b) in enumerate(labels):
        cell_text(table.rows[i].cells[0], a, bold=(i >= 2), size=10, center=True)
        cell_text(table.rows[i].cells[1], b, bold=(i >= 2), size=10, center=True)
        if i == 1:
            table.rows[i].cells[0].paragraphs[0].add_run("")
    # overwrite row 1 as signature lines
    table.rows[1].cells[0].text = ""
    p = table.rows[1].cells[0].paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("\n______________________________")
    set_run(r, size=10)
    table.rows[1].cells[1].text = ""
    p = table.rows[1].cells[1].paragraphs[0]
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("\n______________________________")
    set_run(r, size=10)


def make_form4():
    doc = Document()
    for s in doc.sections:
        s.top_margin = Cm(1.6)
        s.bottom_margin = Cm(1.6)
        s.left_margin = Cm(1.6)
        s.right_margin = Cm(1.6)

    letterhead(doc, "TCP FORM 4  (Rev. 2)", "PROGRESS REPORT")
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("KWINNO L. PINEDA")
    set_run(r, size=13, bold=True)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("(TECHNICAL ADVISER)")
    set_run(r, size=11)

    meta_table(
        doc,
        [
            ("Project title", "PharmaLocate: A Web-Based Pharmacy Inquiry System with Geofencing for Real-Time Drug Availability, Sale & Inventory Checking"),
            ("Proponents", "Chua, Calvin O.; Aurelio, Lester C.; Mercado, Arji Chesner G.; Lacanlale, Nathalie Angel C."),
            ("Period covered", "July 2026 – 1 October 2026"),
            ("Workspace", "Programming4 (Laravel 13 + 3-file frontend + MySQL)"),
            ("Purpose of this form", "Record of technical-adviser consultations: section of the study, recommendation given, and status in the current system or manuscript."),
        ],
    )

    p = doc.add_paragraph()
    r = p.add_run(
        "Instructions: The DATE is the consultation when the recommendation was given. "
        "SECTION / PART is the chapter or component. RECOMMENDATIONS are the adviser’s instructions. "
        "STATUS is how the team complied as of 1 October 2026. SIGNATURE is for the technical adviser."
    )
    set_run(r, size=9, color=MUTED)

    rows = [
        ["Jul 2026", "Title", "Include POS in the title. Recommended wording: A Web-Based Pharmacy Inquiry System with Geofencing for Real-Time Drug Availability, Sale & Inventory Checking.", "Complied in the live UI (browser title, navbar, login subtitle) and for the manuscript title page. POS is a named module (sales + inventory).", ""],
        ["Jul 2026", "Ch. 1 Project Context", "Make the problem more powerful and connect it to the SDGs.", "For the manuscript: strengthen access-to-medicines / SDG 3 wording. System already operationalizes access via live availability + locator around TPH.", ""],
        ["Jul 2026", "Ch. 1 Introduction", "The introduction is too long; shorten it while keeping vital information.", "For the manuscript: compress Chapter 1. UI uses a short product subtitle instead of the long former tagline.", ""],
        ["Jul 2026", "Ch. 1 Purpose / Scope", "Add the scope. Do not include pharmacies inside Tarlac Provincial Hospital (TPH).", "Complied in the system: Home scope banner; public GET /api/pharmacies and /api/availability hide inside_tph stores; admin checkbox “Inside TPH.” Seeded SpaRx and Magic 8 remain community pharmacies around TPH, not inside the compound. Run php artisan migrate for the column.", ""],
        ["Jul 2026", "Ch. 1 Objectives (1.3)", "Revise Objective 2 to proper format. Include inventory management in 1.3.", "Complied in the system: Admin sidebar “Inventory management” and “POS — sales.” Align the same labels in manuscript 1.3.", ""],
        ["Jul 2026", "Ch. 1 Scope & Limitation", "Present White Box Testing in table form, with parameters broken down.", "Test IDs WB-01–WB-12 are tabulated in Documentation_v1.3.md §40 and executed in PHPUnit (Sprint7WhiteBoxTest). Copy that table into Chapter 1 as required.", ""],
        ["Jul 2026", "Ch. 2 Local literature", "Remove the researchers’ own study from the local literature (LLM).", "Manuscript only: delete self-citation from Chapter 2 LLM.", ""],
        ["Jul 2026", "Ch. 2 Conceptual framework", "Revise the conceptual framework to proper IPOO formatting.", "Manuscript figure: Input–Process–Output–Outcome. Live mapping: user/device → Laravel API + geofence/POS logic → MySQL → UI (availability, tickets, sales).", ""],
        ["Jul 2026", "Ch. 2 Definition of terms", "Add definitions of the system features.", "Define terms to match live labels: Inquiries, Pharmacies/geofence locator, Medicines/availability, Inventory management, POS—sales, Geofences, Users, Help assistant.", ""],
        ["Aug 2026", "Ch. 3 Architecture", "Block-diagram system architecture; make it per icon.", "Manuscript figure with one icon per block. Running stack to label: Browser → HTML/CSS/JS → Laravel 13 → MySQL; optional Tile38; Leaflet/OSM map.", ""],
        ["Aug 2026", "Ch. 3 Sources of data", "Connect sources of data to the objectives.", "Manuscript table: SpaRx and Magic 8 (seeded/participating stores) mapped to 1.3 (availability, inquiry, geofence, inventory/POS).", ""],
        ["Aug 2026", "Ch. 4 SDLC", "Add a backlog to the development.", "Complied: PharmaLocate_Sprint_Documentation.docx (Steps 1–8 backlog) and PharmaLocate_Progress_Report.docx.", ""],
        ["Aug 2026", "Ch. 4 Storyboard", "Add the desktop view. Show all features.", "Complied in the system at desktop width: Guest Home/Pharmacies/Medicines/Inquiries/Help; Auth; Admin dashboard, inquiries, inventory, POS, pharmacies, geofences, users, settings, backup. Paper should caption desktop storyboard figures.", ""],
        ["Sep 2026", "References / figures", "Remove the border in the references. Add a figure name for each figure.", "Manuscript formatting: unboxed references; Figure x.x Title on every diagram and screenshot.", ""],
        ["Sep 2026", "Geofencing", "Specify the parameter of the geofencing.", "Complied: Pharmacies tab “Geofencing parameters” card — circular (haversine), default center TPH 15.4870, 120.5960, default radius 5,000 m, admin range 500–10,000 m. Admin Geofences form repeats the same limits. TPH-internal pharmacies excluded.", ""],
        ["Sep 2026", "Users / priority groups", "Make it general instead of focusing on senior citizens, pregnant women, PWDs, etc.", "Complied: priority_type / is_priority removed (July 2026). Home and Help copy say all users. Inquiries ordered by date, not by special group. Roles remain customer, staff, admin.", ""],
        ["Sep 2026", "Devices", "Consider the concept of the devices.", "Complied: viewport meta; desktop-first layout; stacks at ≤800 px; Home pill “Desktop & mobile browsers.” Ordinary Chrome/Edge; no kiosk-only UI.", ""],
        ["Oct 2026", "Respondents", "Break down respondents in a table. Interviews at the pharmacies mentioned. Insert evaluation-rating table.", "Field + manuscript: interview SpaRx and Magic 8 on-site; tabulate who/how many/role; insert Likert/evaluation table (may map to ISO/IEC 25010 in Doc v1.3).", ""],
        ["Oct 2026", "System close-out (adviser check)", "Verify that recommended features exist in the running prototype before pre-defense.", "Live at http://127.0.0.1:8000. Demo: maria / admin / sparx_staff (password). PHPUnit Sprint 7: 29/29; TPH-exclusion test passed. Remaining: binder screenshots; validated Help FAQs; manuscript tables/figures listed above.", ""],
    ]

    fill_table(
        doc,
        ["DATE", "SECTION / PART", "RECOMMENDATIONS", "STATUS / COMPLIANCE (team)", "SIGNATURE"],
        rows,
        [2.2, 3.2, 5.4, 5.4, 2.0],
    )

    p = doc.add_paragraph()
    r = p.add_run("Overall technical-adviser finding as of 1 October 2026: ")
    set_run(r, size=11, bold=True)
    r = p.add_run(
        "Product implementation (inquiry, geofencing, inventory, POS) is complete for the agreed prototype scope. "
        "Manuscript items (SDG wording, short intro, IPOO figure, LLM cleanup, respondent tables, figure captions) remain for the paper. "
        "The technical adviser may sign the last column after verifying the live demo."
    )
    set_run(r, size=11)

    sign_block(
        doc,
        "Proponents / researchers",
        "Chua, Aurelio, Mercado, Lacanlale",
        "Technical Adviser",
        "Kwinno L. Pineda",
    )
    footer_form(doc, "TSU-CCS-SF-15", "TCP Form 4 Rev. 2")

    path = OUT_DIR / "CCS_TCP_FORM4_Progress_Report_Technical_Adviser.docx"
    doc.save(path)
    try:
        doc.save(DOWNLOADS / "CCS TCP FORM4 rev 2(progress report-tech ad).docx")
    except Exception:
        pass
    return path


def make_form5():
    doc = Document()
    for s in doc.sections:
        s.top_margin = Cm(1.6)
        s.bottom_margin = Cm(1.6)
        s.left_margin = Cm(1.6)
        s.right_margin = Cm(1.6)

    letterhead(doc, "TCP FORM 5  (Rev. 2)", "PROGRESS REPORT")
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("DENNIS VIRTUDAZO")
    set_run(r, size=13, bold=True)
    p = doc.add_paragraph()
    p.alignment = WD_ALIGN_PARAGRAPH.CENTER
    r = p.add_run("(SUBJECT TEACHER)")
    set_run(r, size=11)

    meta_table(
        doc,
        [
            ("Project title", "PharmaLocate: A Web-Based Pharmacy Inquiry System with Geofencing for Real-Time Drug Availability, Sale & Inventory Checking"),
            ("Proponents", "Chua, Calvin O.; Aurelio, Lester C.; Mercado, Arji Chesner G.; Lacanlale, Nathalie Angel C."),
            ("Period covered", "July 2026 – 1 October 2026"),
            ("Purpose of this form", "Chronological deliverables submitted for subject-teacher monitoring, with the teacher’s recommendation or remark on each slice."),
        ],
    )

    p = doc.add_paragraph()
    r = p.add_run(
        "DATE is when the slice was presented as done. DELIVERABLE is what the team produced. "
        "RECOMMENDATIONS are the subject teacher’s action (accepted, revise, or verify in class). "
        "The last column is for the teacher’s signature."
    )
    set_run(r, size=9, color=MUTED)

    rows = [
        ["Jul 2026", "Step 1 — Frontend lock. Three-file UI (HTML/CSS/JS) frozen: Guest, Auth, and Admin screens; 10-medicine demo; tabs and sidebar as the screen contract.", "Accepted as UI lock. Proceed to live API. Do not keep changing layout while connecting the database.", ""],
        ["Jul 2026", "Step 2 — Backend map. Screen → functional requirement → API plan; P1–P5 order agreed (customer live data = P1; geofencing = P3; POS = P4; testing = P5).", "Accepted as planning deliverable. Keep geofencing after stock is live.", ""],
        ["Jul 2026", "Step 3 — Live API (P1). Laravel, MySQL, and Sanctum connected to the frozen UI: availability, pharmacies, inquiries. Guests see live stock without an account; customer maria can log in and send inquiries.", "Accepted. Verify on http://127.0.0.1:8000, not the offline HTML demo file.", ""],
        ["Jul 2026", "Step 4 — Admin core (P2). Live dashboard, inquiry replies, and stock edits. Staff limited to assigned pharmacy; /api/admin blocked for customers; admin/staff view opens after login. Priority user types (senior, PWD, pregnant, parent) removed; roles admin, staff, customer remain.", "Accepted. Align later with panel instruction to treat users generally. Confirm staff cannot edit another store.", ""],
        ["Jul 2026", "Sprint 5.0 — Baseline verification only (no new code): XAMPP, php artisan serve, login, live tabs before map work.", "Accepted as process checkpoint.", ""],
        ["Jul 2026", "Sprint 5.1 — Pharmacy CRUD API and admin form (create, edit, deactivate; GPS and hours).", "Accepted. Profiles must stay community pharmacies around TPH, not inside TPH.", ""],
        ["Jul 2026", "Sprint 5.2 — Geofence API, public zones, seeded Tarlac Provincial Hospital zone (5 km).", "Accepted. Document radius and center for the paper.", ""],
        ["Jul 2026", "Sprint 5.3 — Leaflet/OSM customer map replacing SVG; GPS optional with hospital default; CSS overflow fix.", "Accepted. Demo with GPS denied is valid.", ""],
        ["Jul 2026", "Sprint 5.4 — Server-side geofence filter (research core): only pharmacies in the containing zone; empty-zone notice.", "Accepted as the geofencing contribution. Do not describe it as “pins only.”", ""],
        ["Aug 2026", "Sprint 5.5 — Admin geofence UI (create/edit/assign) and admin map reusing existing APIs.", "Accepted.", ""],
        ["Aug 2026", "Sprint 5.6 — Optional Tile38 with haversine fallback so class demo works without Redis.", "Accepted. /api/health may show tile38 unavailable.", ""],
        ["Aug 2026", "Step 6 — POS and extras. POS decrements stock (oversell rejected; admin may choose pharmacy, staff cannot); user management (last admin protected); settings; JSON/CSV export.", "Accepted. Title and 1.3 must name sale and inventory. Show a sale then the Medicines tab.", ""],
        ["Aug 2026", "Sprints 7.0–7.4 — ISO/IEC 25010 testing. Plan in Documentation v1.3 (BB, WB, API IDs). PHPUnit 29/29 (August re-run). API-01–API-20 all pass. 62 of 80 mapped cases verified (~78%). Execution report issued.", "Accepted automation. Complete remaining black-box screenshots for the binder before final defense.", ""],
        ["Sep 2026", "Sprint 8 — Polish and Help. Boot loader, tab motion, deferred GPS. Rule-based Help (no AI), 18 unofficial bilingual presets. Bug fixes: navbar name, fake inquiry samples, Help/Close toggle.", "Accepted as polish. Replace unofficial FAQs when the validated list is ready. Help is not medical advice.", ""],
        ["Oct 2026", "Panel (TCP Form 12) compliance in the running system: recommended title (availability, sale, inventory); TPH-internal pharmacies excluded; geofence parameters on the Pharmacies tab; inventory/POS labels; general-user copy; desktop and mobile layout. Tracker: Panel_Recommendations_Deliverables.docx.", "For verification in pre-defense. Run php artisan migrate. Subject teacher to confirm live demo against Form 12.", ""],
    ]

    fill_table(
        doc,
        ["DATE", "DELIVERABLE", "RECOMMENDATIONS (subject teacher)", "SIGNATURE"],
        rows,
        [2.3, 6.6, 5.6, 2.3],
    )

    p = doc.add_paragraph()
    r = p.add_run("Summary for the subject teacher: ")
    set_run(r, size=11, bold=True)
    r = p.add_run(
        "Steps 1–6 and UI polish are complete for the prototype scope (2 pharmacies, 10 medicines, 1 geofence). "
        "Testing is largely automated; binder photos and the thesis narrative (SDG, IPOO, respondents) are the remaining academic deliverables. "
        "Recommended in-class check: guest map → maria inquiry → admin reply → POS sale."
    )
    set_run(r, size=11)

    p = doc.add_paragraph()
    r = p.add_run("Demo accounts (password: password): maria (customer), admin (administrator), sparx_staff (SpaRx).")
    set_run(r, size=10, color=MUTED)

    sign_block(
        doc,
        "Proponents / researchers",
        "Chua, Aurelio, Mercado, Lacanlale",
        "Subject Teacher",
        "Dennis Virtudazo",
    )
    footer_form(doc, "TSU-CCS-SF-16", "TCP Form 5 Rev. 2")

    path = OUT_DIR / "CCS_TCP_FORM5_Progress_Report_Subject_Teacher.docx"
    doc.save(path)
    try:
        doc.save(DOWNLOADS / "CCS TCP FORM5-rev2(progress report-subject teacher).docx")
    except Exception:
        pass
    return path


def main():
    a = make_form4()
    b = make_form5()
    print(a)
    print(b)


if __name__ == "__main__":
    main()
