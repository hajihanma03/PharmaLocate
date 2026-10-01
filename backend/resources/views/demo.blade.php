<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PharmaLocate API — Demo</title>
    <style>
        :root { --green:#0f9d58; --amber:#f4b400; --red:#db4437; --ink:#1f2933; --muted:#616e7c; }
        * { box-sizing: border-box; }
        body { font-family: system-ui, -apple-system, Segoe UI, Roboto, sans-serif; margin:0; background:#f5f7fa; color:var(--ink); }
        header { background:#0b7285; color:#fff; padding:24px 20px; }
        header h1 { margin:0 0 4px; font-size:22px; }
        header p { margin:0; opacity:.9; font-size:14px; }
        .wrap { max-width:960px; margin:0 auto; padding:20px; }
        .badge { display:inline-block; padding:2px 8px; border-radius:999px; font-size:12px; font-weight:600; color:#fff; }
        .available { background:var(--green); } .low { background:var(--amber); color:#3b3000; } .out_of_stock { background:var(--red); }
        .card { background:#fff; border:1px solid #e4e7eb; border-radius:12px; padding:16px 18px; margin-bottom:16px; box-shadow:0 1px 2px rgba(0,0,0,.04); }
        .card h2 { margin:0 0 2px; font-size:18px; }
        .muted { color:var(--muted); font-size:13px; }
        table { width:100%; border-collapse:collapse; margin-top:10px; font-size:14px; }
        th, td { text-align:left; padding:8px 6px; border-bottom:1px solid #eef1f4; }
        th { color:var(--muted); font-weight:600; font-size:12px; text-transform:uppercase; letter-spacing:.03em; }
        .pill { font-size:12px; color:var(--muted); }
        .status { font-size:13px; }
        .ok { color:var(--green); font-weight:600; }
        code { background:#eef1f4; padding:1px 5px; border-radius:4px; font-size:12px; }
    </style>
</head>
<body>
    <header>
        <h1>PharmaLocate &mdash; Backend API</h1>
        <p>Laravel + MySQL backend. This page pulls <strong>live data from the database</strong> via the API to prove it works.</p>
    </header>
    <div class="wrap">
        <div class="card">
            API status: <span id="health" class="status">checking…</span>
            <span class="muted">&nbsp;&bull;&nbsp; Try it: <code>GET /api/pharmacies</code>, <code>GET /api/medicines</code></span>
        </div>
        <h3 style="margin:6px 2px 10px;">Pharmacies &amp; real-time medicine availability</h3>
        <div id="pharmacies"><p class="muted">Loading pharmacies…</p></div>
    </div>

    <script>
        const badge = (s) => `<span class="badge ${s}">${s.replace(/_/g,' ')}</span>`;

        async function loadHealth() {
            try {
                const r = await fetch('/api/health');
                const d = await r.json();
                document.getElementById('health').innerHTML =
                    `<span class="ok">&#10003; ${d.status.toUpperCase()}</span> &mdash; ${d.app}`;
            } catch (e) {
                document.getElementById('health').textContent = 'API unreachable';
            }
        }

        async function loadPharmacies() {
            // Centered on Tarlac Provincial Hospital so results come back nearest-first.
            const list = await (await fetch('/api/pharmacies?lat=15.4870&lng=120.5960')).json();
            const container = document.getElementById('pharmacies');
            container.innerHTML = '';
            for (const p of list) {
                const detail = await (await fetch(`/api/pharmacies/${p.id}`)).json();
                const rows = detail.medicines.map(m => `
                    <tr>
                        <td>${m.name} <span class="pill">${m.brand ?? ''}</span></td>
                        <td>${m.pivot.stock_quantity}</td>
                        <td>&#8369;${Number(m.pivot.price).toFixed(2)}</td>
                        <td>${badge(m.pivot.availability_status)}</td>
                    </tr>`).join('');
                container.insertAdjacentHTML('beforeend', `
                    <div class="card">
                        <h2>${p.name} <span class="muted">&nbsp;~${p.distance_km} km away</span></h2>
                        <div class="muted">${p.address} &bull; ${p.operating_hours}</div>
                        <table>
                            <thead><tr><th>Medicine</th><th>Stock</th><th>Price</th><th>Status</th></tr></thead>
                            <tbody>${rows}</tbody>
                        </table>
                    </div>`);
            }
        }

        loadHealth();
        loadPharmacies();
    </script>
</body>
</html>
