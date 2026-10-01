# PharmaLocate (Programming4)

Active development workspace for the PharmaLocate capstone — **Steps 1–6 complete; Step 7 (testing documentation) in progress**.

## Quick start

```powershell
cd backend
composer install
php artisan migrate --seed
php artisan serve
```

Open http://127.0.0.1:8000 — demo login: `admin` / `password`

## Documentation

| Document | Format | Contents |
|----------|--------|----------|
| [setup_guide.md](./setup_guide.md) / **setup_guide.docx** | Team setup (living doc) | Full install on a new laptop |
| [Documentation_v1.3.md](./Documentation_v1.3.md) / **Documentation_v1.3.docx** | Step 7 | ISO/IEC 25010 test plan and cases |
| [PharmaLocate_System_Guide.md](./PharmaLocate_System_Guide.md) / **PharmaLocate_System_Guide.docx** | Team reference | Non-technical system guide |
| [Documentation_v1.1.md](./Documentation_v1.1.md) | Step 4 | Admin core |
| [Documentation_v2.0.md](./Documentation_v2.0.md) | Overview | Consolidated summary (may lag v1.x) |

## Frontend workflow

Edit these at the repo root, then copy to `backend/public/`:

- `PharmaLocateFrontEnd.html`
- `styles.css`
- `app.js`

## Snapshots

| Folder | Purpose |
|--------|---------|
| `Programming4/` | Current work (this folder) |
| `Programming3/` | Frozen Step 5 snapshot — do not edit unless asked |
| `Programming2/` | Frozen v1.0 snapshot — do not edit unless asked |

## Next step

**Step 7 — Testing documentation:** Execute test cases in `Documentation_v1.3.md`, capture screenshots, fill summary table.
