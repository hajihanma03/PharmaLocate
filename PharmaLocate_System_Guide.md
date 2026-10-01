# PharmaLocate
## Comprehensive System Guide for the Team

**Web-Based Pharmacy Inquiry System with Geofencing**

**Document type:** Non-technical reference and defense preparation  
**Version:** 1.0  
**Date:** July 2026  
**Project status:** Steps 1–5 complete · Step 6 (POS and extras) next · Step 7 (formal testing documentation) planned

---

## Table of Contents

1. [Introduction](#1-introduction)
2. [The Problem We Are Solving](#2-the-problem-we-are-solving)
3. [Project Objectives and Scope](#3-project-objectives-and-scope)
4. [Where Our Ideas Came From](#4-where-our-ideas-came-from)
5. [Who Uses the System](#5-who-uses-the-system)
6. [How the Whole System Works](#6-how-the-whole-system-works)
7. [Main Features Explained](#7-main-features-explained)
8. [How We Built the System from Scratch](#8-how-we-built-the-system-from-scratch)
9. [System Architecture in Plain Language](#9-system-architecture-in-plain-language)
10. [How Data Is Stored and Connected](#10-how-data-is-stored-and-connected)
11. [Geofencing — The Heart of Our Project](#11-geofencing--the-heart-of-our-project)
12. [How We Set Up and Run the System](#12-how-we-set-up-and-run-the-system)
13. [Current Progress Summary](#13-current-progress-summary)
14. [What Is Still Planned](#14-what-is-still-planned)
15. [Possible Questions and Suggested Answers](#15-possible-questions-and-suggested-answers)
16. [Glossary of Terms](#16-glossary-of-terms)

---

## 1. Introduction

**PharmaLocate** is our capstone project: a **web-based pharmacy inquiry system with geofencing**. In simple terms, it helps people find nearby pharmacies, check whether medicines are available, and send questions to pharmacy staff — all through a website that works in a regular web browser.

This document is written for **non-technical team members**, panelists, and anyone who needs to understand *what* we built, *why* we built it that way, and *how* each part connects to form a working whole. It is written as if we developed the system step by step from the beginning, because that is exactly how the project was organized.

By the time this guide was written, the system already supports:

- Customer browsing of medicines and pharmacies  
- User registration and login  
- Inquiry submission and staff replies  
- Admin dashboard with live statistics  
- Stock management for pharmacies  
- Interactive maps with geofence zones  
- Admin management of pharmacies and geofences  
- Optional high-performance location search using Tile38  

What is **not yet fully connected** includes point-of-sale (POS) transactions, full user management screens, settings, and backup/export — these are planned for the next development phase.

---

## 2. The Problem We Are Solving

Many people need medicine quickly but do not know which nearby pharmacy actually has it in stock. Calling multiple pharmacies is slow and inconvenient. At the same time, pharmacy staff receive repeated phone inquiries about the same products, which wastes time they could spend serving customers at the counter.

Our system addresses this gap by providing:

1. **A single place to check medicine availability** across participating pharmacies  
2. **A map-based locator** that shows pharmacies near the user  
3. **A structured inquiry channel** so customers can ask questions online and staff can reply in one organized place  
4. **Admin tools** so pharmacies can manage their inventory, service areas (geofences), and daily operations  

This aligns with real-world trends in **digital health information systems** and **location-based services**, where mobile and web applications help users make faster, better-informed decisions about where to go for care or supplies.

---

## 3. Project Objectives and Scope

Our capstone paper defines two major modules. The table below maps each objective to what the system currently does.

### Admin Module Objectives

| Objective | What it means in practice | Current status |
|-----------|---------------------------|------------------|
| User management | Create and manage accounts, assign roles | Partially done — login/register works; full admin user-management screen not yet wired |
| Dashboard | Overview of inquiries, stock alerts, activity | **Done** — live statistics from database |
| Pharmacy profiles | Store name, address, hours, GPS coordinates | **Done** — admin can create, edit, and deactivate pharmacies |
| Geofence assignment | Link pharmacies to service zones | **Done** — zones can assign multiple pharmacies |
| Geofence CRUD | Create, edit, and deactivate zones | **Done** — admin full access; staff read-only view |
| Settings | System configuration | UI exists; not yet connected to backend |
| Notifications | Alert users or staff | Not yet implemented |
| Backup / export | Save or export system data | UI exists; not yet connected to backend |

### Customer Module Objectives

| Objective | What it means in practice | Current status |
|-----------|---------------------------|------------------|
| Interactive map + GPS | Show user location and nearby pharmacies on a map | **Done** — Leaflet map with OpenStreetMap |
| Inquiry services | Submit and track medicine questions | **Done** — customers submit; staff/admin reply |
| Information access | Browse medicines, prices, availability | **Done** — live data from database |
| Real-time medicine availability | Show stock status per pharmacy | **Done** — available, low, or out of stock |
| Geofence-based pharmacy locator | Only show pharmacies in the user's service zone | **Done** — server filters by geofence when location is known |

### Scope boundaries (important for defense)

To keep the capstone achievable, we intentionally limited scope:

- **10 medicines** in the demo dataset (representative of a real catalog, not an unlimited national database)  
- **2 sample pharmacies** in Tarlac City area (SpaRx Pharmacy and Magic 8 Pharmacy)  
- **1 geofence zone** centered on Tarlac Provincial Hospital with a 5 km radius  
- **Three user roles:** customer, staff, and admin  

These limits let us build and test every feature thoroughly without pretending to be a nationwide pharmacy chain on day one.

---

## 4. Where Our Ideas Came From

This section explains the **concepts, existing systems, and logical building blocks** behind PharmaLocate. Understanding these helps answer "why did you design it this way?" during presentations.

### 4.1 Web-Based Information Systems

**Concept:** Many modern services run in a browser instead of requiring a separate app install. Examples include online banking, school portals, and hospital appointment systems.

**Why we used it:** A web-based approach is accessible on any device with a browser, easy to demonstrate during defense, and matches our capstone requirement for a web system. Our interface is built from standard web technologies: HTML (structure), CSS (design), and JavaScript (behavior).

### 4.2 Client–Server Architecture (Frontend + Backend)

**Concept:** The **frontend** is what the user sees and clicks. The **backend** is the server that stores data securely, applies business rules, and answers requests. They communicate through an **API** (Application Programming Interface) — a set of agreed-upon URLs that return structured data (JSON).

**Why we used it:** Medicine stock, user passwords, and inquiry records must not live only in the browser where anyone could tamper with them. The backend (Laravel framework + MySQL database) is the trusted source of truth. The frontend asks questions like "What medicines are available?" and the backend responds with verified data.

**Existing pattern:** This is the same model used by Facebook, Shopee, and Google Maps — a visible app talking to a hidden server.

### 4.3 REST API Design

**Concept:** REST (Representational State Transfer) is a widely taught standard for web APIs. Each action maps to an HTTP method:

- **GET** — read data (list medicines)  
- **POST** — create something new (register, submit inquiry)  
- **PATCH** — update existing data (staff reply, stock change)  
- **DELETE** — remove or deactivate (soft-delete pharmacy)

**Why we used it:** REST is industry-standard, well-documented in Laravel tutorials, and easy to explain to panelists. Our frontend uses JavaScript `fetch()` calls to these endpoints when running in live mode.

### 4.4 Authentication and Role-Based Access Control (RBAC)

**Concept:** Not every user should see or change everything. **Authentication** proves who you are (login). **Authorization** decides what you are allowed to do based on your **role**.

**Our roles:**

| Role | Who they are | What they can do |
|------|--------------|------------------|
| **Customer** | General public user | Browse, submit inquiries, view own inquiry history |
| **Staff** | Employee of one pharmacy | Reply to inquiries, update stock — **only for their pharmacy** |
| **Admin** | System manager | Everything staff can do, plus manage all pharmacies, geofences, and create new records system-wide |

**Technology used:** Laravel Sanctum issues a secure **token** after login. The browser stores this token and sends it with each protected request. Without a valid token, the server returns "unauthorized."

**Real-world parallel:** Same idea as employee ID badges in a hospital — your badge gets you through certain doors, not every door.

### 4.5 Geofencing

**Concept:** A **geofence** is an invisible boundary on a map, usually a circle around a point. When a user's GPS coordinates fall inside that boundary, the system treats them as "inside the service area."

**Where this idea comes from:** Geofencing is used in ride-hailing apps (Uber, Grab), food delivery (only deliver within radius), retail marketing ("welcome to our store" notifications), and logistics fleet tracking.

**Why it matters for PharmaLocate:** Our capstone specifically requires a **geofence-based pharmacy locator**. Instead of showing every pharmacy in the country, we show only pharmacies **assigned to the zone where the user currently stands**. This models how a regional pharmacy network might operate — each zone has designated partner pharmacies.

**How we implement it:** Each geofence has a center point (latitude/longitude) and a radius in meters. We check whether the user's location is within that radius using distance mathematics (see Section 11).

### 4.6 Haversine Distance Formula

**Concept:** The Earth is round, so simple flat-map math is inaccurate over longer distances. The **Haversine formula** calculates the great-circle distance between two GPS coordinates in kilometers.

**Why we used it:** It is a classic, well-published approach in geospatial computing textbooks and open-source projects. It works without any extra software and serves as our **fallback** when the optional Tile38 engine is turned off.

**Example in our demo:** From the default location near Tarlac Provincial Hospital, SpaRx Pharmacy appears at approximately **0.31 km** and Magic 8 Pharmacy at approximately **0.81 km**.

### 4.7 Tile38 (Optional Geospatial Database)

**Concept:** Tile38 is a specialized in-memory database designed for **fast location queries** — finding objects near a point, inside a fence, or within a radius at scale.

**Why we added it (Sprint 5.6):** Our capstone design mentions Tile38 as a design component (DC2). For a small demo with two pharmacies, Haversine is enough. Tile38 demonstrates that we understand **production-grade geospatial infrastructure** and provides a path to scale if the system grew to hundreds of pharmacies.

**How it behaves in our system:** When Tile38 is enabled and running, pharmacy distance sorting uses Tile38's NEARBY command. When it is disabled or unavailable, the system **automatically falls back** to Haversine — users still get correct results.

### 4.8 Interactive Web Maps (Leaflet + OpenStreetMap)

**Concept:** **Leaflet** is a popular open-source JavaScript library for interactive maps. **OpenStreetMap (OSM)** provides free map tiles (the visual map background).

**Why we used them:** They are free, well-documented, widely used in academic and commercial projects, and do not require a paid Google Maps API key for development. Our customer Pharmacies tab and admin Geofences section both use Leaflet to draw:

- The user's location (green dot)  
- Geofence zones (dashed circles)  
- Pharmacy markers (pins with popups)  

**Existing systems using similar stacks:** Many humanitarian mapping tools, university research projects, and municipal dashboards use Leaflet + OSM for the same reasons.

### 4.9 Inquiry Management Workflow

**Concept:** Instead of unstructured phone calls, inquiries follow a **ticket lifecycle**:

1. Customer submits a question (medicine name, pharmacy, message)  
2. Status is set to **pending**  
3. Staff or admin reads it and writes a **response**  
4. Status changes to **replied**  
5. Customer sees the answer in their inquiry history  

**Real-world parallel:** This mirrors helpdesk systems (IT support tickets), hospital appointment follow-ups, and e-commerce "contact seller" flows.

### 4.10 Inventory and Availability Status

**Concept:** Each pharmacy tracks stock per medicine. We store:

- **Quantity** (how many units)  
- **Price** (per pharmacy — prices can differ)  
- **Availability status** — automatically derived:
  - **0 units** → out of stock  
  - **1–9 units** → low stock  
  - **10+ units** → available  

**Why automatic status:** Reduces human error. Staff update the number; the system sets the label consistently for customers and dashboard alerts.

### 4.11 Single-Page Application (SPA) Style Frontend

**Concept:** Instead of loading a new web page for every click, our app **switches views inside one HTML file** — Guest, Logged-in User, Login/Signup, and Admin. This feels faster and more app-like.

**Why we used it:** Our Step 1 goal was to **lock the UI design** in three files before connecting the backend. A single-page structure made it easy to prototype all capstone screens in one place.

### 4.12 Demo Mode vs Live Mode

**Concept:** The same frontend file can run in two ways:

| Mode | How you open it | Data source |
|------|-----------------|-------------|
| **Demo** | Double-click the HTML file | Built-in sample data in JavaScript — nothing is saved |
| **Live** | Through the Laravel web server | Real MySQL database via API |

**Why both exist:** Demo mode lets anyone preview the interface instantly without installing PHP or MySQL. Live mode is what we demonstrate for capstone evaluation.

### 4.13 ISO/IEC 25010 (Planned for Step 7)

**Concept:** ISO/IEC 25010 is an international standard for **software product quality**. It defines characteristics like functionality, reliability, usability, performance, and maintainability.

**Why we reference it:** Our capstone requires formal testing documentation aligned with this standard. Step 7 will produce test cases and evidence mapped to these quality attributes. The system is being built with testability in mind from the start (health check endpoint, role separation, predictable API responses).

### 4.14 Design Change: Priority User Types Removed

**Original concept (early versions):** The system briefly supported "priority" customer types (seniors, PWD, pregnant women, parents) with faster inquiry handling.

**Why we removed it:** To simplify the scope and focus on core capstone objectives — geofencing, availability, and admin workflows. Roles (customer/staff/admin) remain. Inquiries are ordered by date, not priority. This was a deliberate team decision documented in July 2026.

---

## 5. Who Uses the System

### 5.1 Customer (Example: Maria Santos)

Maria opens the website, browses medicines, checks which pharmacy has Paracetamol in stock, looks at the map to see SpaRx and Magic 8 nearby, and submits an inquiry: "Do you have Biogesic brand in stock today?" She can register an account to track her inquiry history.

### 5.2 Pharmacy Staff (Example: SpaRx Staff)

Staff log in and land directly on the **Admin view**, but with **limited access**. They see dashboard stats, inquiries, and stock **only for SpaRx Pharmacy**. They cannot create new pharmacies, edit geofences, or see Magic 8's data. On the Geofences page, staff can **view** zones in read-only mode with a notice that changes require an admin.

### 5.3 System Administrator

Admin has full control: all pharmacies, all geofences, all inquiries, all stock, dashboard system-wide. Admin creates geofence zones, assigns pharmacies to them, adds new pharmacy locations, and deactivates outdated records.

### 5.4 Guest (Not logged in)

Guests can browse medicines and pharmacies and view the map. To submit an inquiry or see personal history, they must log in or sign up.

---

## 6. How the Whole System Works

Think of PharmaLocate as **four layers** working together:

```
┌─────────────────────────────────────────────────────────┐
│  LAYER 1: USER INTERFACE (Browser)                      │
│  What people see — tabs, forms, maps, buttons           │
└──────────────────────────┬──────────────────────────────┘
                           │ requests data / sends actions
┌──────────────────────────▼──────────────────────────────┐
│  LAYER 2: API (Laravel routes)                          │
│  Rules for who can do what — login checks, validation   │
└──────────────────────────┬──────────────────────────────┘
                           │ reads/writes
┌──────────────────────────▼──────────────────────────────┐
│  LAYER 3: DATABASE (MySQL)                              │
│  Permanent storage — users, stock, inquiries, zones     │
└──────────────────────────┬──────────────────────────────┘
                           │ optional sync
┌──────────────────────────▼──────────────────────────────┐
│  LAYER 4: TILE38 (Optional location engine)             │
│  Fast nearby-pharmacy search when enabled               │
└─────────────────────────────────────────────────────────┘
```

### 6.1 Typical Customer Journey (Live Mode)

1. User opens the website through the local web server  
2. Browser loads HTML, CSS, and JavaScript  
3. JavaScript detects live mode and calls `/api/medicines` and `/api/availability`  
4. Medicines tab fills with real stock data from MySQL  
5. User opens Pharmacies tab → browser asks for GPS permission  
6. If GPS granted, coordinates are sent to `/api/pharmacies?lat=...&lng=...`  
7. Server checks geofences → filters pharmacies → calculates distances → returns JSON  
8. JavaScript draws the Leaflet map and pharmacy list  
9. User logs in → receives a secure token → can submit inquiry via `/api/inquiries`  
10. Inquiry is saved in database with status "pending"  
11. Staff sees it in Admin → Inquiry Management → writes reply → status becomes "replied"  
12. Maria refreshes her Inquiries tab and sees the answer  

### 6.2 Typical Admin Journey

1. Admin logs in with username `admin`  
2. System detects admin role and opens Admin view automatically  
3. Dashboard loads statistics: pending inquiries, low stock alerts, registered users  
4. Admin opens Stock section, changes quantity for a medicine at a pharmacy  
5. Backend updates `pharmacy_medicine` table and recalculates availability status  
6. Customer-facing Medicines tab reflects the new stock on next load  
7. Admin opens Geofences, creates or edits a zone, checks which pharmacies belong to it  
8. Changes sync to Tile38 automatically if Tile38 is enabled  

---

## 7. Main Features Explained

### 7.1 Medicine Browsing

Customers see a list of medicines with brand names. For each medicine, the system shows which pharmacies carry it, the price at each location, and whether it is available, low, or out of stock. Data comes from the join between `medicines` and `pharmacy_medicine` tables.

### 7.2 Pharmacy Locator with Map

The Pharmacies tab combines a **list view** and an **interactive map**. Selecting a pharmacy in the list highlights it on the map. Users can open Google Maps directions in a new tab. A banner explains whether the user is inside a geofence zone and how many pharmacies are shown.

**Default location behavior:** If the user denies GPS permission, the system uses coordinates near **Tarlac Provincial Hospital** — the same center as our demo geofence. This ensures the demo always works during presentations even without location access.

### 7.3 Inquiry System

Customers fill a form: select pharmacy, select or type medicine, add a message. The inquiry is tied to their user account. Staff and admin see all pending inquiries (staff only for their pharmacy). Reply updates the record and changes status.

### 7.4 Admin Dashboard

Live cards show:

- Number of active pharmacies  
- Inquiries today and pending count  
- Medicines being tracked  
- Low stock alerts  
- Sales today (from transaction table — prepared for POS)  
- Geofence and user counts  
- Recent activity feed  

### 7.5 Stock Management

Staff and admin update stock quantity and price per medicine per pharmacy. The system auto-sets availability labels. Dashboard low-stock alerts pull from the same data.

### 7.6 Pharmacy Management

Admin can add new pharmacies with name, address, contact, operating hours, and GPS coordinates. Staff can edit their own pharmacy's details but cannot create or delete pharmacies. "Delete" is a **soft deactivate** — the record is hidden but preserved for audit purposes.

### 7.7 Geofence Management

Admin can create zones with name, description, center coordinates, radius (500 m to 10 km), and assigned pharmacies. Staff see all zones but cannot modify them. The admin Geofences section includes its own Leaflet map showing zones and pharmacy markers.

### 7.8 Health Check

Visiting `/api/health` returns a simple JSON status confirming the API is running and whether Tile38 is ok, unavailable, or disabled. Useful for quick verification before a demo.

---

## 8. How We Built the System from Scratch

We did not build everything at once. The project followed a **step-by-step plan** so each layer was stable before adding the next. This mirrors how professional software teams work: design first, integrate second, specialize third.

### Step 1 — Frontend Lock (UI Design)

**Goal:** Finalize what the app looks like before writing backend code.

**What we did:**

- Designed all four views: Guest, User, Auth (login/signup), Admin  
- Built customer tabs: Home, Pharmacies, Medicines, Inquiries  
- Built admin sidebar: Dashboard, Inquiries, Stock, POS, Pharmacies, Geofences, Settings, Backup  
- Added 10 capstone medicines as demo data  
- Fixed UX issues: back button on auth screen, inquiry form submission, scroll on long signup form  

**Outcome:** A complete visual prototype that could be clicked through in demo mode.

### Step 2 — Backend Planning Map

**Goal:** Connect every screen to a capstone objective and decide build order.

**What we did:**

- Mapped each frontend screen to functional requirements (FR3 inquiries, FR4 staff reply, FR6 availability, etc.)  
- Prioritized work into five phases (P1 through P5)  
- Identified which admin sections were UI-only placeholders for later steps  

**Outcome:** A clear roadmap so the team always knew what to build next.

### Step 3 — Backend Integration (Priority 1)

**Goal:** Connect core customer features to a real database.

**What we did:**

- Set up Laravel 13 with MySQL  
- Implemented registration, login (username or email), logout  
- Built APIs for medicines, pharmacies, availability, inquiries  
- Wired JavaScript to fetch live data when served over HTTP  
- Created demo seed data: 2 pharmacies, 10 medicines, 1 geofence, sample users  
- Documented full Windows setup procedure (XAMPP, PHP 8.3+, Composer)  

**Outcome:** Customers could register, log in, browse real stock, and submit inquiries that persist in the database.

### Step 4 — Admin Core (Priority 2)

**Goal:** Make the admin side functional, not just visual.

**What we did:**

- Admin/staff login automatically opens Admin view  
- Dashboard connected to live statistics API  
- Inquiry reply panel connected to PATCH endpoint  
- Stock table connected to read/update API  
- Staff scoping: each staff user belongs to one pharmacy and sees only that pharmacy's data  
- Middleware ensures only admin/staff can access admin routes  

**Outcome:** A working back-office for daily pharmacy operations (except POS and geofence UI).

### Step 5 — Geofencing (Priority 3)

**Goal:** Deliver the capstone's distinguishing feature — location-based pharmacy discovery.

This step was split into **six sprints**:

| Sprint | Focus | Result |
|--------|-------|--------|
| **5.0** | Baseline verification | Confirmed Steps 1–4 still work before adding maps |
| **5.1** | Pharmacy CRUD | Admin can manage pharmacy records via API and UI |
| **5.2** | Geofence CRUD API | Zones can be created and pharmacies assigned; public API for map |
| **5.3** | Customer Leaflet map | Replaced decorative placeholder with real interactive map |
| **5.4** | Geofence filtering | Server returns only pharmacies in user's zone; distance sorting |
| **5.5** | Admin geofence UI | Full admin map and forms; staff read-only access |
| **5.6** | Tile38 integration | Optional fast geospatial engine with automatic fallback |

**Outcome:** Full geofencing pipeline from admin zone configuration to customer map experience.

### Step 6 — POS and Extras (Next)

**Goal:** Complete remaining admin modules.

**Planned work:**

- POS transactions that decrement stock  
- User management (list users, assign roles and pharmacy)  
- Settings persistence  
- Backup and export  

### Step 7 — Testing Documentation (Planned)

**Goal:** Formal test cases and evidence aligned with ISO/IEC 25010 for capstone submission.

---

## 9. System Architecture in Plain Language

### 9.1 The Three Frontend Files

| File | Role |
|------|------|
| **PharmaLocateFrontEnd.html** | All pages and layout — navigation, tabs, forms, admin panels |
| **styles.css** | Colors, spacing, responsive layout, map styling |
| **app.js** | Logic — switching views, calling APIs, drawing maps, handling login |

When running through Laravel, copies of these three files are placed in the web server's public folder so the browser can load them at the site root.

### 9.2 The Backend (Laravel)

Laravel organizes code into:

- **Routes** — URL definitions (`/api/login`, `/api/pharmacies`, etc.)  
- **Controllers** — Handle each request, validate input, return JSON  
- **Models** — Represent database tables as PHP objects  
- **Middleware** — Check authentication and roles before allowing access  
- **Migrations** — Version-controlled database schema definitions  
- **Seeders** — Insert demo data for testing  

### 9.3 Authentication Flow

1. User submits username and password  
2. Server verifies credentials against hashed password in database  
3. Server creates a Sanctum token  
4. Browser stores token in local storage under key `ph_token`  
5. Every protected request includes header: `Authorization: Bearer <token>`  
6. Logout revokes the token on the server  

### 9.4 Why MySQL?

Relational databases excel at connected data: users linked to inquiries, pharmacies linked to medicines through stock rows, geofences linked to pharmacies through a junction table. MySQL is free, ships with XAMPP, and is widely taught in IT programs.

---

## 10. How Data Is Stored and Connected

### 10.1 Main Tables

| Table | Stores |
|-------|--------|
| **users** | Accounts — name, username, email, password, role, pharmacy assignment for staff |
| **pharmacies** | Pharmacy details and GPS coordinates |
| **medicines** | Medicine name and brand |
| **pharmacy_medicine** | Stock quantity, price, availability per pharmacy |
| **geofences** | Zone name, center coordinates, radius, active flag |
| **geofence_pharmacy** | Which pharmacies belong to which zone |
| **inquiries** | Customer questions, staff responses, status |
| **transactions** | POS sales header (schema ready for Step 6) |
| **transaction_items** | Line items per sale (schema ready for Step 6) |
| **audit_logs** | Activity log (schema ready) |

### 10.2 Demo Data Snapshot

After running database seed:

| Item | Details |
|------|---------|
| **Pharmacies** | SpaRx Pharmacy (Romulo Blvd), Magic 8 Pharmacy (Maliwalo) |
| **Geofence** | Tarlac Provincial Hospital Zone — 5 km radius, both pharmacies assigned |
| **Medicines** | 10 common medicines (Paracetamol, Amoxicillin, Ibuprofen, etc.) |
| **Users** | admin, sparx_staff, magic8_staff, maria |
| **Sample inquiry** | Maria asked SpaRx about medicine availability — status pending until replied |

### 10.3 How a Stock Update Flows

```
Staff changes quantity in Admin Stock screen
        ↓
JavaScript sends PATCH to /api/admin/stock/{pharmacy}/{medicine}
        ↓
Server validates role and pharmacy access
        ↓
MySQL row updated in pharmacy_medicine
        ↓
Availability status recalculated (available / low / out_of_stock)
        ↓
Customer Medicines tab shows new status on next page load
        ↓
Dashboard low-stock count updates accordingly
```

---

## 11. Geofencing — The Heart of Our Project

Geofencing deserves its own section because it is our capstone's defining technical and conceptual feature.

### 11.1 What the User Experiences

- Open Pharmacies tab  
- See a map centered on your location (or default hospital area)  
- A dashed circle shows the geofence zone boundary  
- Green dot = you  
- Pins = pharmacies inside the zone  
- Banner text: *"Inside Tarlac Provincial Hospital Zone (5 km) — showing 2 assigned pharmacies"*  
- If you simulate being far away (e.g., Manila coordinates), the list is empty with a message explaining you are outside all service zones  

### 11.2 What Happens on the Server

When latitude and longitude are provided:

1. Load all active geofences from database  
2. For each geofence, check: is the user point within `radius_meters` of the center?  
3. Collect IDs of matching geofences  
4. If none match → return empty pharmacy list (user is outside service area)  
5. If some match → find pharmacies linked to those geofences via `geofence_pharmacy`  
6. Calculate distance from user to each pharmacy  
7. Sort nearest first  
8. Return JSON to frontend  

### 11.3 Admin Side

Admin defines zones — where service is offered — and assigns which pharmacies serve each zone. A pharmacy can belong to multiple zones. Deactivating a zone hides it from public API without deleting historical data.

### 11.4 Tile38 Enhancement

When Tile38 is running:

- Geofences and pharmacy coordinates are synced as POINT objects  
- NEARBY queries return distances efficiently  
- Admin changes trigger automatic re-sync  
- Command `php artisan tile38:sync` manually refreshes all location data  

When Tile38 is off, steps 1–7 above still work using PHP and Haversine math — no functionality is lost for the demo scale.

---

## 12. How We Set Up and Run the System

This section describes setup **without assuming a specific folder name** on your computer. Adjust paths to wherever you stored the project files.

### 12.1 Tools Required

| Tool | Purpose |
|------|---------|
| **Windows 10 or 11** | Development environment used by the team |
| **XAMPP** | Provides MySQL database and phpMyAdmin |
| **PHP 8.3 or higher** | Required by Laravel 13 |
| **Composer** | Installs PHP libraries |
| **Modern browser** | Chrome or Edge for testing |
| **Internet connection** | Map tiles and CDN libraries load from the web |
| **Tile38 (optional)** | Enhanced geospatial queries |

> **Important note:** Standard XAMPP often includes PHP 8.2. Laravel 13 requires PHP 8.3+, so the team upgraded the PHP folder inside XAMPP using the official Windows PHP ZIP package.

### 12.2 One-Time Installation Steps

1. Install XAMPP and start MySQL  
2. Upgrade PHP to 8.3+ if needed  
3. Install Composer and point it to the PHP executable  
4. Enable PHP extensions: curl, fileinfo, mbstring, openssl, pdo_mysql, zip  
5. Create a MySQL database named `pharmalocate`  
6. Copy environment template to `.env` and set database credentials  
7. Run `composer install` in the backend folder  
8. Run `php artisan key:generate`  
9. Run `php artisan migrate --seed` to create tables and demo data  
10. Copy the three frontend files into the backend public web folder  
11. (Optional) Install Tile38, set `TILE38_ENABLED=true` in `.env`, run `php artisan tile38:sync`  

### 12.3 Every Development Session

1. Start MySQL in XAMPP Control Panel  
2. (Optional) Start Tile38 server  
3. Open a terminal in the backend folder  
4. Run `php artisan serve`  
5. Open http://127.0.0.1:8000 in the browser  
6. Use Ctrl+F5 (hard refresh) after frontend changes  
7. Stop the server with Ctrl+C when finished  

### 12.4 Demo Accounts for Testing

All demo passwords: **`password`**

| Username | Role | Notes |
|----------|------|-------|
| admin | Administrator | Full system access |
| sparx_staff | Staff | SpaRx Pharmacy only |
| magic8_staff | Staff | Magic 8 Pharmacy only |
| maria | Customer | Sample customer with one inquiry |

Login accepts **username or email**.

### 12.5 Verification Checklist Before a Presentation

- [ ] Website loads at http://127.0.0.1:8000  
- [ ] Medicines tab shows live stock from database  
- [ ] Pharmacies tab shows Leaflet map with geofence circle and 2 pharmacies (at default location)  
- [ ] Login as maria → submit or view inquiry  
- [ ] Login as admin → dashboard shows live numbers  
- [ ] Admin can reply to inquiry and update stock  
- [ ] Login as sparx_staff → sees only SpaRx data; geofences are read-only  
- [ ] `/api/health` returns status ok  

---

## 13. Current Progress Summary

### Completed (Steps 1–5)

| Area | Status |
|------|--------|
| UI design (all views and admin sections) | Complete |
| Customer medicine and availability browsing | Complete |
| User registration, login, logout | Complete |
| Inquiry submit and staff reply | Complete |
| Admin dashboard with live stats | Complete |
| Stock management with auto availability labels | Complete |
| Staff pharmacy scoping | Complete |
| Pharmacy CRUD (admin full, staff limited) | Complete |
| Geofence CRUD API | Complete |
| Customer interactive map (Leaflet + OSM) | Complete |
| Geofence-based pharmacy filtering | Complete |
| Admin geofence UI with map | Complete |
| Staff read-only geofence access | Complete |
| Tile38 integration with fallback | Complete |
| Windows setup documentation | Complete |

### Partially Done / UI Only

| Area | Status |
|------|--------|
| POS (Point of Sale) | Screen exists; transaction API not wired |
| User management | Screen exists; not connected to backend |
| Settings | Screen exists; not connected to backend |
| Backup / export | Screen exists; not connected to backend |
| Notifications | Not implemented |

---

## 14. What Is Still Planned

### Step 6 — POS and Extras

- Record sales transactions and line items  
- Automatically reduce stock when a sale is completed  
- Admin user management: view users, change roles, assign staff to pharmacies  
- Save system settings to database  
- Backup and export functionality  

### Step 7 — Formal Testing

- White-box and black-box test cases  
- Documentation mapped to ISO/IEC 25010 quality characteristics  
- Screenshots and test evidence for capstone binder  

---

## 15. Possible Questions and Suggested Answers

This section helps the team prepare for defense, panel Q&A, and stakeholder demos.

### General Project Questions

**Q: What is PharmaLocate in one sentence?**  
A: PharmaLocate is a web-based pharmacy inquiry system that lets customers check medicine availability, find nearby pharmacies using geofencing, and send inquiries to pharmacy staff online.

**Q: What problem does it solve?**  
A: It reduces the need for repeated phone calls to pharmacies by giving customers one place to see stock and location information, while giving staff a structured way to respond to inquiries.

**Q: Is this a mobile app?**  
A: No. It is a responsive web application that runs in a browser. It can be accessed on phones through the browser without installing an app from an app store.

**Q: Why a capstone project and not a commercial product?**  
A: It is an academic demonstration of concepts — web systems, databases, APIs, geofencing, and role-based administration — scoped to be complete and testable within the program timeline.

---

### Technical Concept Questions (Explained Simply)

**Q: What is geofencing?**  
A: Geofencing creates an invisible boundary on a map. When a user's location is inside that boundary, the system knows they are in a service area and shows only the pharmacies assigned to that zone.

**Q: How do you know how far a pharmacy is?**  
A: We use GPS coordinates for the user and each pharmacy. The system calculates distance in kilometers using the Haversine formula, or Tile38 when enabled for faster queries.

**Q: What is an API?**  
A: An API is how the website's visible part asks the server for data. For example, when you open the Medicines tab, JavaScript calls `/api/medicines` and the server sends back a list in JSON format.

**Q: What is Laravel?**  
A: Laravel is a PHP framework — a structured toolkit for building secure web backends. It handles routing, database access, authentication, and validation so we do not reinvent those from scratch.

**Q: What is MySQL?**  
A: MySQL is a relational database that permanently stores users, medicines, stock, inquiries, and geofences in organized tables.

**Q: What is Tile38 and do we need it?**  
A: Tile38 is an optional specialized database for location queries. Our system works without it using built-in distance math. We added Tile38 to demonstrate advanced geospatial capability and align with our design documentation.

**Q: What happens if Tile38 is not running?**  
A: The system automatically uses Haversine distance calculation in PHP. Users still see correct pharmacy lists and distances for our demo scale.

**Q: What is Leaflet?**  
A: Leaflet is a free JavaScript library for interactive maps. We use it with OpenStreetMap tiles to show geofence circles, user location, and pharmacy pins.

---

### Security and Access Questions

**Q: How do you protect passwords?**  
A: Passwords are never stored as plain text. Laravel hashes them before saving. Login compares the hash, not the raw password.

**Q: How do you prevent staff from seeing another pharmacy's data?**  
A: Each staff account has a `pharmacy_id` in the database. Middleware and controller logic filter inquiries, stock, and dashboard stats to that pharmacy only.

**Q: Can a customer access the admin panel?**  
A: No. Admin API routes require a valid token and a role of admin or staff. Customers receive an unauthorized response if they try.

**Q: Why can staff view geofences but not edit them?**  
A: Geofence zones define the service area for the whole network. Changing them affects all assigned pharmacies, so only administrators have write access. Staff need read access to understand which zone their pharmacy belongs to.

---

### Feature and Design Questions

**Q: Why only 10 medicines?**  
A: The capstone scope focuses on proving the system works correctly, not on maintaining a national drug database. Ten medicines are enough to test availability, stock updates, and inquiries thoroughly.

**Q: Why Tarlac Provincial Hospital as the default location?**  
A: Our demo geofence is centered there with a 5 km radius, and both sample pharmacies are in Tarlac City. Using the same center as default ensures the demo always shows meaningful results even when GPS is denied.

**Q: What if the user is outside all geofences?**  
A: The pharmacy list is empty and a clear message explains that no pharmacies are available in their current area. This is intentional — geofencing means service is zone-based, not worldwide.

**Q: Did you remove priority users (seniors, PWD)?**  
A: Yes. Early designs included priority types for faster inquiry handling. We removed this to simplify scope and focus on geofencing and core workflows. All inquiries are handled in chronological order.

**Q: What is the difference between demo mode and live mode?**  
A: Demo mode opens the HTML file directly and uses fake built-in data — nothing saves. Live mode runs through the Laravel server and uses the real MySQL database.

**Q: Is POS working?**  
A: Not yet. The POS screen exists in the admin UI and the database tables are ready, but recording sales and updating stock through POS is planned for Step 6.

---

### Setup and Demo Questions

**Q: What do you need to run a live demo?**  
A: MySQL running, Laravel server running (`php artisan serve`), and a browser pointed to http://127.0.0.1:8000. Internet is needed for map tiles.

**Q: What if the map is blank or grey?**  
A: Check internet connection — map tiles load from OpenStreetMap's CDN. Also hard-refresh the browser (Ctrl+F5) after CSS updates.

**Q: How do you reset demo data?**  
A: Run `php artisan migrate:fresh --seed` in the backend. This wipes and recreates all tables with fresh demo data. Warning: this destroys any test data you added.

**Q: How do you know the system is healthy before presenting?**  
A: Visit `/api/health` — it should return `"status":"ok"`. Also run through the verification checklist in Section 12.5.

---

### Process and Methodology Questions

**Q: Why build the frontend before the backend?**  
A: Step 1 locked the UI so the team agreed on screens and user flows before investing in server code. This prevents rework and gives panelists a visual anchor early.

**Q: How did you organize development?**  
A: We used priority phases: P1 customer core, P2 admin core, P3 geofencing, P4 POS/extras, P5 testing docs. Within Step 5, we used sprints (5.0 through 5.6) for incremental delivery.

**Q: How will you test the system formally?**  
A: Step 7 will document test cases aligned with ISO/IEC 25010 — covering functionality, usability, reliability, and other quality attributes with screenshots and pass/fail evidence.

**Q: What were the biggest challenges?**  
A: (Team can personalize) Common ones included: upgrading PHP for Laravel 13 compatibility, syncing frontend files to the web server after edits, map CSS conflicts with Leaflet attribution icons, and ensuring staff role scoping worked consistently across all admin sections.

---

### Comparison Questions

**Q: How is this different from Google Maps alone?**  
A: Google Maps shows locations. PharmaLocate adds **medicine availability**, **inquiry management**, **role-based admin tools**, and **geofence business rules** — which pharmacies serve which zone — on top of mapping.

**Q: How is this different from calling a pharmacy?**  
A: Customers see structured availability data and can submit written inquiries tracked by status. Staff respond in one system instead of scattered phone notes.

**Q: Could this scale to a real city?**  
A: The architecture supports it: more pharmacies and zones can be added through admin tools, and Tile38 enables efficient location queries at larger scale. Production deployment would also need hosting, HTTPS, and stronger security review beyond capstone scope.

---

## 16. Glossary of Terms

| Term | Simple definition |
|------|-------------------|
| **API** | A defined way for programs to request and exchange data over the web |
| **Availability status** | Label showing if a medicine is available, low stock, or out of stock |
| **Backend** | Server-side code and database that store and protect data |
| **Bearer token** | A secret string sent with requests to prove the user is logged in |
| **CRUD** | Create, Read, Update, Delete — the four basic data operations |
| **Frontend** | The visual part of the app running in the browser |
| **Geofence** | A virtual geographic boundary used to trigger location-based rules |
| **GPS** | Global Positioning System — provides latitude and longitude coordinates |
| **Haversine formula** | Mathematical method to calculate distance between two Earth coordinates |
| **JSON** | A text format for structured data exchanged between frontend and backend |
| **Latitude / Longitude** | Coordinates that pinpoint a location on Earth |
| **Leaflet** | JavaScript library for interactive web maps |
| **Laravel** | PHP web framework used for our backend |
| **Middleware** | Code that runs before a request reaches the controller — often checks login |
| **Migration** | A version-controlled file that defines or changes database table structure |
| **MySQL** | Relational database management system |
| **OpenStreetMap (OSM)** | Free, community-built map data used as our map background |
| **POS** | Point of Sale — system for recording in-store purchases |
| **REST** | Architectural style for web APIs using standard HTTP methods |
| **Sanctum** | Laravel package for API token authentication |
| **Seeder** | Script that inserts demo or initial data into the database |
| **Soft delete / deactivate** | Hiding a record from active use without permanently erasing it |
| **SPA (Single-Page Application)** | Web app that updates content without full page reloads |
| **Staff scoping** | Restricting staff users to data for their assigned pharmacy only |
| **Tile38** | Optional geospatial database for fast location-based queries |
| **Token** | Digital key issued at login, used instead of sending password every request |
| **XAMPP** | Package bundling Apache, MySQL, and PHP for local development |

---

## Document History

| Version | Date | Description |
|---------|------|-------------|
| 1.0 | July 2026 | Initial comprehensive team guide covering Steps 1–5, concepts, setup, and defense Q&A |

---

*This document describes the PharmaLocate system as developed by the capstone team. It is intended for internal team reference, panel preparation, and stakeholder orientation. For technical change logs during development, refer to the versioned Documentation files maintained alongside the project.*
