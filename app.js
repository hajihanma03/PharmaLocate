/** PharmaLocate frontend — database catalog when served from Laravel; demo catalog only for a local file open */

const DEMO_MEDICINES = [
  { name: 'Paracetamol 500mg', type: 'Pain / Fever relief', pharmacy: 'SpaRx Pharmacy', qty: 250, status: 'available' },
  { name: 'Amoxicillin 500mg', type: 'Antibiotic', pharmacy: 'Magic 8 Pharmacy', qty: 180, status: 'available' },
  { name: 'Ibuprofen 400mg', type: 'Anti-inflammatory', pharmacy: 'Magic 8 Pharmacy', qty: 120, status: 'available' },
  { name: 'Cetirizine 10mg', type: 'Antihistamine', pharmacy: 'SpaRx Pharmacy', qty: 200, status: 'available' },
  { name: 'Losartan 50mg', type: 'Hypertension', pharmacy: 'SpaRx Pharmacy', qty: 160, status: 'available' },
  { name: 'Metformin 500mg', type: 'Diabetes management', pharmacy: 'PharmaCare Tarlac', qty: 75, status: 'low' },
  { name: 'Amlodipine 5mg', type: 'Hypertension', pharmacy: 'SpaRx Pharmacy', qty: 90, status: 'available' },
  { name: 'Omeprazole 20mg', type: 'Acid reflux', pharmacy: 'Magic 8 Pharmacy', qty: 60, status: 'low' },
  { name: 'Salbutamol Inhaler', type: 'Asthma / COPD', pharmacy: 'SpaRx Pharmacy', qty: 0, status: 'out' },
  { name: 'Ascorbic Acid 500mg', type: 'Vitamin C supplement', pharmacy: 'City Pharmacy Plus', qty: 300, status: 'available' },
];

const API_BASE = location.protocol.startsWith('http') ? '/api' : null;
const TOKEN_KEY = 'ph_token';
const USER_KEY = 'ph_user';
const GEOFENCE_ZONE_COLORS = ['#1D9E75', '#378ADD', '#854d0e'];
const TARLAC_CENTER = { lat: 15.47474, lng: 120.58669 };

let customerMap = null;
let customerMapMarkers = {};
let customerMapLayers = [];
let customerGeofences = [];
let userLocation = { ...TARLAC_CENTER, fromGps: false };
let selectedPharmacyId = null;

let medicines = isLiveMode() ? [] : normalizeMedicines(DEMO_MEDICINES);
let pharmacies = [];
let adminInquiries = [];
let adminPharmacies = [];
let adminGeofences = [];
let selectedAdminGeofenceId = null;
let adminGeofenceMap = null;
let adminGeofenceMapLayers = [];
let adminPharmacyDraft = null;
let adminStartingDraft = null;
let pendingPharmacyName = null;
let reassignQueue = [];
let pendingNestPharmacy = null;
let insideStartingPointId = null;
let selectedAdminInquiryId = null;
let posCart = [];
let posProducts = [];
let posPharmacyId = null;
let adminUsers = [];
let adminSettings = {};
function readStoredUser() {
  try {
    const parsed = JSON.parse(localStorage.getItem(USER_KEY) || 'null');
    return parsed && typeof parsed === 'object' ? parsed : null;
  } catch (_) {
    localStorage.removeItem(USER_KEY);
    return null;
  }
}

let apiToken = localStorage.getItem(TOKEN_KEY);
let currentUser = readStoredUser();

function isStaffOrAdmin() {
  return currentUser && (currentUser.role === 'admin' || currentUser.role === 'staff' || currentUser.role === 'owner');
}

function isAdminUser() {
  return currentUser?.role === 'admin';
}

function isOwnerUser() {
  return currentUser?.role === 'owner';
}

function isPharmacyScopedUser() {
  return currentUser?.role === 'staff' || currentUser?.role === 'owner';
}

function accountTierLabel(user) {
  if (!user) return 'Account';
  if (user.role === 'admin') return 'Admin';
  if (user.role === 'staff') return 'Staff';
  if (user.role === 'customer') return 'Customer';
  if (user.role === 'owner') {
    const pharmacy = String(user.pharmacy?.name || (typeof user.pharmacy === 'string' ? user.pharmacy : '') || '');
    if (/magic\s*8/i.test(pharmacy)) return 'Magic 8 Owner';
    if (/sparx/i.test(pharmacy)) return 'SpaRx Owner';
    return 'Pharmacy owner';
  }
  return 'Customer';
}

function ownerTierValue(user) {
  if (user?.role !== 'owner') return '';
  const pharmacy = String(user.pharmacy?.name || (typeof user.pharmacy === 'string' ? user.pharmacy : '') || '');
  if (/magic\s*8/i.test(pharmacy)) return 'magic8_owner';
  if (/sparx/i.test(pharmacy)) return 'sparx_owner';
  return '';
}

function isLiveMode() {
  return Boolean(API_BASE);
}

function saveSession(user, token) {
  currentUser = user;
  apiToken = token;
  localStorage.setItem(TOKEN_KEY, token);
  localStorage.setItem(USER_KEY, JSON.stringify(user));
}

function clearSession() {
  currentUser = null;
  apiToken = null;
  localStorage.removeItem(TOKEN_KEY);
  localStorage.removeItem(USER_KEY);
  resetAdminGeofenceUi();
}

function resetAdminGeofenceUi() {
  adminGeofences = [];
  selectedAdminGeofenceId = null;
  closeGeofenceForm();
  const list = document.getElementById('admin-geofence-list');
  if (list) list.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">Loading geofences…</div>';
  applyGeofenceAccessControl();
}

function abortAfter(ms) {
  if (typeof AbortSignal !== 'undefined' && typeof AbortSignal.timeout === 'function') {
    return AbortSignal.timeout(ms);
  }
  const ctrl = new AbortController();
  setTimeout(() => ctrl.abort(), ms);
  return ctrl.signal;
}

async function apiFetch(path, options = {}) {
  const headers = { Accept: 'application/json', ...(options.headers || {}) };
  if (options.body && !(options.body instanceof FormData)) {
    headers['Content-Type'] = 'application/json';
  }
  if (apiToken) headers.Authorization = `Bearer ${apiToken}`;

  const timeoutMs = options.timeoutMs ?? 8000;
  const ctrl = new AbortController();
  const timer = setTimeout(() => ctrl.abort(), timeoutMs);
  const { timeoutMs: _ignored, signal, ...rest } = options;
  try {
    const res = await fetch(`${API_BASE}${path}`, {
      ...rest,
      headers,
      signal: signal || ctrl.signal,
    });
    const data = await res.json().catch(() => ({}));
    if (!res.ok) {
      const msg = data.message || Object.values(data.errors || {}).flat().join(' ') || 'Request failed';
      throw new Error(msg);
    }
    return data;
  } catch (err) {
    if (err.name === 'AbortError') throw new Error('Request timed out');
    throw err;
  } finally {
    clearTimeout(timer);
  }
}

function displayName(user = currentUser) {
  if (!user) return 'Guest';
  const raw = user.username || user.name || user.email || '';
  const text = String(raw).trim();
  if (!text || text === 'undefined' || text === 'null') return 'Account';
  return text;
}

function userInitials(name) {
  const source = String(name || '').trim();
  if (!source || source === 'undefined' || source === 'null') return 'U';
  const parts = source.split(/[\s@._-]+/).filter(Boolean);
  if (!parts.length) return 'U';
  return parts.map(w => w[0]).join('').slice(0, 2).toUpperCase();
}

function normalizeMedicines(rows) {
  if (!Array.isArray(rows)) return [];
  return rows.map((row) => {
    const statusRaw = row.status || row.availability_status;
    const status = statusRaw === 'out' || statusRaw === 'out_of_stock'
      ? 'out'
      : statusRaw === 'low'
        ? 'low'
        : 'available';
    return {
      name: row.name || 'Medicine',
      type: row.type || row.category || row.description || 'Medicine',
      pharmacy: row.pharmacy || 'Pharmacy',
      pharmacy_id: row.pharmacy_id ?? null,
      medicine_id: row.medicine_id ?? row.id ?? null,
      qty: Number(row.qty ?? row.stock_quantity ?? 0),
      status,
    };
  });
}

function medicineCardHtml(m) {
  const badgeClass = m.status === 'available' ? 'badge-green' : m.status === 'low' ? 'badge-amber' : 'badge-red';
  const badgeText = m.status === 'available' ? `Available · ${m.qty} pcs` : m.status === 'low' ? `Low stock · ${m.qty} pcs` : 'Out of stock';
  return `
      <div class="med-card">
        <div class="med-card-top">
          <div class="med-icon"><i class="ti ti-pill" aria-hidden="true"></i></div>
          <span class="badge ${badgeClass}">${escapeHtml(badgeText)}</span>
        </div>
        <div class="med-name">${escapeHtml(m.name)}</div>
        <div class="med-type">${escapeHtml(m.type || '')}</div>
        <div class="med-pharmacy ${pharmacyToneClass(m.pharmacy_id)}"><i class="ti ti-building-store" aria-hidden="true"></i> <span>${escapeHtml(m.pharmacy || '')}</span></div>
      </div>`;
}

function renderMeds(list) {
  const grid = document.getElementById('med-grid');
  if (grid) {
    grid.innerHTML = list.length
      ? list.map(medicineCardHtml).join('')
      : '<div class="text-muted text-sm" style="padding:12px;">No medicines match this search.</div>';
  }
  renderHomePreview();
}

function renderHomePreview() {
  const wrap = document.getElementById('home-med-preview');
  if (!wrap) return;
  const featured = medicines.slice(0, 3);
  wrap.innerHTML = featured.length
    ? featured.map(medicineCardHtml).join('')
    : '<div class="text-muted text-sm">No medicines listed yet.</div>';
}

function pharmacyToneClass(pharmacyId) {
  const tones = ['tone-a', 'tone-b', 'tone-c'];
  const id = Number(pharmacyId);
  if (!Number.isFinite(id)) return 'tone-a';
  return tones[Math.abs(id) % tones.length];
}

function applyMedicineFilters() {
  const query = (document.getElementById('med-search')?.value || '').toLowerCase();
  const pharmacyId = document.getElementById('med-pharmacy-filter')?.value || '';
  renderMeds(medicines.filter((m) => {
    const matchesText = !query || m.name.toLowerCase().includes(query) || (m.type || '').toLowerCase().includes(query);
    const matchesPharmacy = !pharmacyId || String(m.pharmacy_id) === String(pharmacyId);
    return matchesText && matchesPharmacy;
  }));
}

function filterMeds() {
  applyMedicineFilters();
}

function filterMedsByPharmacy() {
  applyMedicineFilters();
}

function haversineKm(lat1, lng1, lat2, lng2) {
  const earthRadius = 6371;
  const dLat = (lat2 - lat1) * Math.PI / 180;
  const dLng = (lng2 - lng1) * Math.PI / 180;
  const a = Math.sin(dLat / 2) ** 2
    + Math.cos(lat1 * Math.PI / 180) * Math.cos(lat2 * Math.PI / 180) * Math.sin(dLng / 2) ** 2;
  return earthRadius * 2 * Math.asin(Math.min(1, Math.sqrt(a)));
}

function getMatchingGeofences() {
  if (!customerGeofences.length) return [];
  return customerGeofences.filter(g =>
    haversineKm(userLocation.lat, userLocation.lng, g.center_latitude, g.center_longitude) * 1000 <= g.radius_meters,
  );
}

function isUserInsideAnyGeofence() {
  return getMatchingGeofences().length > 0;
}

function getPharmacyListEmptyMessage() {
  if (!isLiveMode()) return 'No pharmacies found.';
  if (customerGeofences.length && !isUserInsideAnyGeofence()) {
    return 'You are outside all service geofences. Try the default Tarlac area or move closer to an active zone.';
  }
  if (customerGeofences.length) {
    return 'No pharmacies are assigned to your current geofence zone.';
  }
  return 'No pharmacies found.';
}

function renderPharmacyList(list) {
  const el = document.getElementById('pharmacy-list');
  if (!el) return;

  if (!list.length) {
    el.innerHTML = `<div class="text-muted text-sm" style="padding:12px;">${escapeHtml(getPharmacyListEmptyMessage())}</div>`;
    selectedPharmacyId = null;
    return;
  }

  el.innerHTML = list.map((p, i) => {
    const dist = p.distance_km != null ? `${p.distance_km} km` : '—';
    const distIcon = p.distance_km != null && p.distance_km <= 1.5 ? 'ti-walk' : 'ti-car';
    const hours = (p.operating_hours || '').trim();
    const hoursLabel = hours ? (hours.length > 28 ? `${hours.slice(0, 26)}…` : hours) : 'Hours not listed';
    const active = selectedPharmacyId != null
      ? String(p.id) === String(selectedPharmacyId)
      : i === 0;
    return `
      <div class="pharmacy-item${active ? ' active' : ''}" data-pharmacy-id="${p.id}" onclick="selectPharmacyFromList(${p.id})">
        <div class="pharm-name">${escapeHtml(p.name)}</div>
        <div class="pharm-row">
          <span class="pharm-dist"><i class="ti ${distIcon}"></i> ${dist}</span>
          <span class="badge badge-gray">${escapeHtml(hoursLabel)}</span>
        </div>
        <div class="pharm-addr">${escapeHtml(p.address || '')}</div>
      </div>`;
  }).join('');

  if (selectedPharmacyId == null && list.length) {
    selectedPharmacyId = list[0].id;
  }
}

function fillPharmacySelects(list) {
  const options = list.map(p => `<option value="${p.id}">${escapeHtml(p.name)}</option>`).join('');
  const medFilter = document.getElementById('med-pharmacy-filter');
  const inqSelect = document.getElementById('inq-pharmacy');
  if (medFilter) {
    medFilter.innerHTML = `<option value="">All pharmacies</option>${options}`;
  }
  if (inqSelect) {
    inqSelect.innerHTML = `<option value="">Select a pharmacy…</option>${options}`;
  }
  fillMedicineSuggestions();
}

function fillMedicineSuggestions() {
  const list = document.getElementById('medicine-suggestions');
  if (!list) return;
  const names = [...new Set(medicines.map(m => m.name).filter(Boolean))].sort((a, b) => a.localeCompare(b));
  list.innerHTML = names.map(name => `<option value="${escapeHtml(name)}"></option>`).join('');
}

let catalogReload = null;

async function reloadPublicCatalog() {
  if (!isLiveMode()) return;
  if (catalogReload) return catalogReload;

  catalogReload = (async () => {
    const loc = userLocation;
    const [availability, pharmList, geofenceList] = await Promise.all([
      apiFetch(`/availability`, { timeoutMs: 8000 }),
      apiFetch(`/pharmacies?lat=${loc.lat}&lng=${loc.lng}`, { timeoutMs: 8000 }),
      fetchPublicGeofences(),
    ]);

    medicines = normalizeMedicines(Array.isArray(availability) ? availability : []);
    pharmacies = Array.isArray(pharmList) ? pharmList : [];
    customerGeofences = Array.isArray(geofenceList) ? geofenceList : [];
    if (!pharmacies.some(p => p.id === selectedPharmacyId)) {
      selectedPharmacyId = pharmacies[0]?.id ?? null;
    }

    renderPharmacyList(pharmacies);
    fillPharmacySelects(pharmacies);
    applyMedicineFilters();
    updateGeofenceNotice();
    updateGeofenceParams();
    updatePharmacyMapOverlay();
    if (customerMap) renderCustomerMapLayers();
  })().finally(() => {
    catalogReload = null;
  });

  return catalogReload;
}

function renderInquiries(list) {
  const panel = document.getElementById('inquiry-list');
  if (!panel) return;

  if (!currentUser) {
    panel.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">Log in to send and track your inquiries.</div>';
    return;
  }

  if (!list.length) {
    panel.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No inquiries yet. Submit one using the form.</div>';
    return;
  }

  const mine = list.filter(inq => Number(inq.user_id) === Number(currentUser.id));
  if (!mine.length) {
    panel.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No inquiries yet. Choose a pharmacy and submit one using the form.</div>';
    return;
  }

  panel.innerHTML = mine.map(inq => {
    const statusClass = inq.status === 'resolved' ? 'badge-green' : inq.status === 'pending' ? 'badge-amber' : 'badge-gray';
    const statusLabel = inq.status === 'resolved' ? 'Replied' : inq.status === 'pending' ? 'Pending' : inq.status;
    const med = inq.medicine?.name || 'General inquiry';
    const pharm = inq.pharmacy?.name || 'Pharmacy';
    const question = inq.message ? `<div class="inq-meta">${escapeHtml(inq.message)}</div>` : '';
    const reply = inq.response ? `<div class="inq-reply"><span class="inq-reply-label">Pharmacy reply</span><span class="inq-reply-text">${escapeHtml(inq.response)}</span></div>` : '';
    return `
      <div class="inq-item">
        <div class="inq-item-top">
          <span class="inq-med">${escapeHtml(med)}</span>
          <span class="badge ${statusClass}">${escapeHtml(statusLabel)}</span>
        </div>
        <div class="inq-meta">
          <span class="badge badge-gray text-xs">${escapeHtml(pharm)}</span>
        </div>
        ${question}
        ${reply}
      </div>`;
  }).join('');
}

function updateNavForUser() {
  const navAuth = document.getElementById('nav-auth');
  if (!navAuth || !currentUser) return;
  const initials = userInitials(currentUser.name || displayName());
  const label = displayName();
  navAuth.innerHTML = `
    <button class="btn btn-sm btn-ghost nav-compact" onclick="logoutUser()"><i class="ti ti-logout"></i> Log out</button>
    <div class="nav-user">
      <div class="nav-user-avatar">${escapeHtml(initials)}</div>
      <span class="nav-user-name">${escapeHtml(label)}</span>
    </div>`;
}

function updateAdminNav() {
  const navAuth = document.getElementById('nav-auth');
  if (!navAuth || !currentUser) return;
  const initials = userInitials(currentUser.name || displayName());
  const roleLabel = accountTierLabel(currentUser);
  navAuth.innerHTML = `
    <button class="btn btn-sm btn-ghost nav-compact" onclick="logoutUser()"><i class="ti ti-logout"></i> Log out</button>
    <div class="nav-user">
      <div class="nav-user-avatar">${escapeHtml(initials)}</div>
      <span class="nav-user-name">${escapeHtml(roleLabel)}</span>
    </div>`;
}

function goHome() {
  if (isStaffOrAdmin()) {
    switchView('admin');
    switchAdminSection('dashboard');
    return;
  }
  switchView(currentUser ? 'user' : 'guest');
  switchTab('home');
}

function prefersReducedMotion() {
  return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

function replayEnter(el) {
  if (!el) return;
  el.classList.remove('is-entering');
  if (prefersReducedMotion()) return;
  void el.offsetWidth;
  el.classList.add('is-entering');
}

function revealApp() {
  document.documentElement.classList.remove('is-booting');
  document.documentElement.classList.add('is-ready');
}

function switchView(v) {
  document.querySelectorAll('.view').forEach(el => el.classList.remove('active'));

  const navTabs = document.getElementById('nav-tabs');
  const navAuth = document.getElementById('nav-auth');

  if (v === 'guest') {
    document.getElementById('view-guest').classList.add('active');
    navTabs.style.display = 'flex';
    navAuth.innerHTML = `
      <button class="btn btn-ghost btn-sm nav-compact" onclick="switchView('auth')"><i class="ti ti-login"></i> Log in</button>
      <button class="btn btn-primary btn-sm nav-compact" onclick="switchView('auth')"><i class="ti ti-user-plus"></i> Sign up</button>`;
  } else if (v === 'user') {
    document.getElementById('view-guest').classList.add('active');
    navTabs.style.display = 'flex';
    updateNavForUser();
  } else if (v === 'admin') {
    if (isLiveMode()) {
      if (!apiToken) {
        alert('Please log in as admin or staff first.');
        switchView('auth');
        return;
      }
      if (!isStaffOrAdmin()) {
        alert('Admin access requires an admin or staff account.');
        return;
      }
      loadAdminData();
    }
    document.getElementById('view-admin').classList.add('active');
    navTabs.style.display = 'none';
    updateAdminNav();
    applyGeofenceAccessControl();
  } else if (v === 'auth') {
    document.getElementById('view-auth').classList.add('active');
    navTabs.style.display = 'none';
    navAuth.innerHTML = `
      <button class="btn btn-ghost btn-sm" onclick="switchView('guest')"><i class="ti ti-arrow-left"></i> Back</button>`;
  }
  updateChatbotVisibility(v);
  const activeView = document.querySelector('.view.active');
  replayEnter(activeView);
}

const GUEST_TABS = ['home', 'pharmacies', 'medicines', 'inquiries'];
let currentGuestTab = 'home';

function switchTab(t) {
  if (!GUEST_TABS.includes(t)) return;

  const prev = currentGuestTab;
  const goingForward = GUEST_TABS.indexOf(t) >= GUEST_TABS.indexOf(prev);

  GUEST_TABS.forEach((name) => {
    const btn = document.getElementById('tab-' + name);
    const content = document.getElementById('tab-content-' + name);
    if (btn) btn.classList.toggle('active', name === t);
    if (!content) return;
    content.classList.toggle('hidden', name !== t);
    content.classList.remove('tab-in-forward', 'tab-in-back', 'is-entering');
    if (name === t && prev !== t && !prefersReducedMotion()) {
      void content.offsetWidth;
      content.classList.add(goingForward ? 'tab-in-forward' : 'tab-in-back');
    }
  });
  currentGuestTab = t;

  if (t === 'medicines') applyMedicineFilters();
  if (t === 'inquiries' && currentUser && isLiveMode()) loadInquiries();
  if (isLiveMode() && (t === 'home' || t === 'medicines' || t === 'pharmacies')) {
    reloadPublicCatalog().catch(() => { /* keep the last successful catalog */ });
  }
  if (t === 'pharmacies') {
    updateGeofenceNotice();
    if (isLiveMode()) {
      ensureCustomerMap();
      renderCustomerMapLayers();
      setTimeout(() => customerMap?.invalidateSize(), 380);
    }
  }
}

function getUserLocation() {
  return new Promise(resolve => {
    if (!navigator.geolocation) {
      resolve({ ...TARLAC_CENTER, fromGps: false });
      return;
    }
    navigator.geolocation.getCurrentPosition(
      pos => resolve({
        lat: pos.coords.latitude,
        lng: pos.coords.longitude,
        fromGps: true,
      }),
      () => resolve({ ...TARLAC_CENTER, fromGps: false }),
      { enableHighAccuracy: false, timeout: 4000, maximumAge: 120000 },
    );
  });
}

async function fetchPublicGeofences() {
  if (!isLiveMode()) return [];
  try {
    const res = await fetch(`${API_BASE}/geofences`);
    if (!res.ok) return [];
    return res.json();
  } catch (_) {
    return [];
  }
}

function ensureCustomerMap() {
  if (!isLiveMode() || typeof L === 'undefined') return;
  const el = document.getElementById('guest-leaflet-map');
  if (!el || customerMap) return;

  customerMap = L.map(el).setView([userLocation.lat, userLocation.lng], 14);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(customerMap);
}

function clearCustomerMapLayers() {
  customerMapLayers.forEach(layer => customerMap?.removeLayer(layer));
  customerMapLayers = [];
  customerMapMarkers = {};
}

function renderCustomerMapLayers() {
  if (!customerMap) return;

  clearCustomerMapLayers();

  const zoneColors = GEOFENCE_ZONE_COLORS;

  customerGeofences.forEach((g, i) => {
    const color = zoneColors[i % zoneColors.length];
    const circle = L.circle([g.center_latitude, g.center_longitude], {
      radius: g.radius_meters,
      color,
      weight: 1.5,
      dashArray: '6,5',
      fillColor: color,
      fillOpacity: 0.06,
    }).addTo(customerMap).bindTooltip(g.name);
    customerMapLayers.push(circle);
  });

  const userMarker = L.circleMarker([userLocation.lat, userLocation.lng], {
    radius: 8,
    color: '#fff',
    weight: 2,
    fillColor: '#1D9E75',
    fillOpacity: 1,
  }).addTo(customerMap).bindTooltip(userLocation.fromGps ? 'You are here' : 'Default location (hospital area)');
  customerMapLayers.push(userMarker);

  const bounds = [];

  const preferred = pharmacies.find(p => p.id === selectedPharmacyId) || pharmacies[0];

  pharmacies.forEach(p => {
    if (p.latitude == null || p.longitude == null) return;
    const selected = preferred && p.id === preferred.id;
    const dist = p.distance_km != null ? `~${p.distance_km} km` : '';
    const marker = L.marker([p.latitude, p.longitude], {
      icon: pharmacyPinIcon(selected),
      zIndexOffset: selected ? 500 : 0,
    }).addTo(customerMap)
      .bindPopup(`<strong>${escapeHtml(p.name)}</strong><br>${escapeHtml(p.address || '')}${dist ? `<br>${dist}` : ''}<br><span>Directions start at ${escapeHtml(directionsOriginFor(p).name)}</span>`);
    marker.on('click', () => selectPharmacyFromList(p.id));
    customerMapMarkers[p.id] = marker;
    customerMapLayers.push(marker);
    bounds.push([p.latitude, p.longitude]);
  });

  const guideOrigin = directionsOriginFor(preferred);
  if (preferred?.latitude != null && preferred?.longitude != null) {
    const guide = L.polyline(
      [[guideOrigin.lat, guideOrigin.lng], [preferred.latitude, preferred.longitude]],
      { color: '#0F6E56', weight: 3, opacity: 0.85, dashArray: '8,7' },
    ).addTo(customerMap);
    customerMapLayers.push(guide);
    const originAway = haversineKm(guideOrigin.lat, guideOrigin.lng, userLocation.lat, userLocation.lng) > 0.03;
    if (originAway) {
      const originMarker = L.circleMarker([guideOrigin.lat, guideOrigin.lng], {
        radius: 7,
        color: '#fff',
        weight: 2,
        fillColor: '#185FA5',
        fillOpacity: 1,
      }).addTo(customerMap).bindTooltip(`Directions start: ${guideOrigin.name}`);
      customerMapLayers.push(originMarker);
    }
  }

  bounds.push([userLocation.lat, userLocation.lng]);
  bounds.push([guideOrigin.lat, guideOrigin.lng]);

  if (bounds.length > 1) {
    customerMap.fitBounds(bounds, { padding: [40, 40], maxZoom: 15 });
  } else {
    customerMap.setView([userLocation.lat, userLocation.lng], 14);
  }

  if (selectedPharmacyId && customerMapMarkers[selectedPharmacyId]) {
    const p = pharmacies.find(x => x.id === selectedPharmacyId);
    if (p?.latitude != null && p?.longitude != null) {
      customerMap.setView([p.latitude, p.longitude], 15);
    }
  }
}

function pharmacyPinIcon(selected) {
  return L.divIcon({
    className: `pharmacy-pin${selected ? ' is-selected' : ''}`,
    html: '<span class="pharmacy-pin-dot"></span>',
    iconSize: [22, 22],
    iconAnchor: [11, 20],
    popupAnchor: [0, -18],
  });
}

function preferredPharmacy() {
  return pharmacies.find(p => p.id === selectedPharmacyId) || pharmacies[0] || null;
}

function zoneForPreferredPharmacy() {
  const pharmacy = preferredPharmacy();
  if (!pharmacy) return customerGeofences[0] || null;
  const zones = customerGeofences.filter(g =>
    (g.pharmacies || []).some(p => Number(p.id) === Number(pharmacy.id)),
  );
  if (!zones.length) return customerGeofences[0] || null;
  return [...zones].sort((a, b) => Number(a.radius_meters) - Number(b.radius_meters))[0];
}

function updateGeofenceNotice() {
  const el = document.getElementById('geofence-notice-text');
  if (!el) return;

  if (!isLiveMode()) {
    el.textContent = 'Demo mode — open via http://127.0.0.1:8000 for the live map.';
    return;
  }

  const matching = getMatchingGeofences();

  if (matching.length) {
    const zone = [...matching].sort((a, b) => Number(a.radius_meters) - Number(b.radius_meters))[0];
    const count = pharmacies.length;
    el.innerHTML = `Inside <strong style="margin:0 3px;">${escapeHtml(zone.name)}</strong> (${formatRadiusKm(zone.radius_meters)}) — showing ${count} assigned ${count === 1 ? 'pharmacy' : 'pharmacies'}`;
    return;
  }

  if (customerGeofences.length && pharmacies.length) {
    const count = pharmacies.length;
    el.innerHTML = `You are outside the service area. ${count} pharmacy ${count === 1 ? 'pin is' : 'pins are'} on the map. Select one to get directions.`;
    return;
  }

  if (customerGeofences.length) {
    el.textContent = 'You are outside all active service geofences. No pharmacy locations are set yet.';
    return;
  }

  el.textContent = 'Showing nearest pharmacies from your location.';
}

function updateGeofenceParams() {
  const grid = document.getElementById('geofence-params-grid');
  if (!grid) return;
  const pharmacy = preferredPharmacy();
  const zone = zoneForPreferredPharmacy();
  const centerLat = pharmacy?.latitude ?? zone?.center_latitude ?? TARLAC_CENTER.lat;
  const centerLng = pharmacy?.longitude ?? zone?.center_longitude ?? TARLAC_CENTER.lng;
  const radius = zone?.radius_meters ?? 5000;
  const km = radius / 1000;
  const kmLabel = Number.isInteger(km) ? `${km} km` : `${km.toFixed(1)} km`;
  const name = zone?.name || 'Tarlac Provincial Hospital Zone';
  const pharmacyName = pharmacy?.name || 'None selected';
  grid.innerHTML = `
    <div>Preferred pharmacy: ${escapeHtml(pharmacyName)}</div>
    <div>Zone: ${escapeHtml(name)}</div>
    <div>Pharmacy pin: ${Number(centerLat).toFixed(4)}, ${Number(centerLng).toFixed(4)}</div>
    <div>Radius: ${radius.toLocaleString()} m (${kmLabel})</div>
    <div>Shape: circular (haversine / optional Tile38)</div>
    <div>Directions start at ${escapeHtml(directionsOriginFor(pharmacy).name)}</div>`;
  updateDirectionsButton(pharmacy);
}

function updateDirectionsButton(pharmacy) {
  const btn = document.getElementById('pharmacy-directions-btn');
  if (!btn) return;
  const origin = directionsOriginFor(pharmacy || preferredPharmacy());
  btn.innerHTML = `<i class="ti ti-navigation"></i> Get directions from ${escapeHtml(origin.name)}`;
}

function updatePharmacyMapOverlay() {
  const el = document.getElementById('pharmacy-map-overlay');
  if (!el) return;
  const label = userLocation.fromGps
    ? 'Your location (GPS)'
    : 'Tarlac Provincial Hospital area (default)';
  el.innerHTML = `<i class="ti ti-current-location"></i> ${label}`;
}

function selectPharmacyFromList(id) {
  selectedPharmacyId = id;
  document.querySelectorAll('.pharmacy-item').forEach(el => {
    el.classList.toggle('active', String(el.dataset.pharmacyId) === String(id));
  });
  updateGeofenceParams();

  const p = pharmacies.find(x => x.id === id);
  if (!p || !customerMap || p.latitude == null || p.longitude == null) return;

  renderCustomerMapLayers();
  customerMapMarkers[id]?.openPopup();
}

function openPharmacyDirections() {
  const p = pharmacies.find(x => x.id === selectedPharmacyId) || pharmacies[0];
  if (!p?.latitude || !p?.longitude) {
    alert('Select a pharmacy with map coordinates first.');
    return;
  }
  const originPoint = directionsOriginFor(p);
  const origin = `${originPoint.lat},${originPoint.lng}`;
  window.open(
    `https://www.google.com/maps/dir/?api=1&origin=${origin}&destination=${p.latitude},${p.longitude}`,
    '_blank',
  );
}

function switchAdminSection(s) {
  if (s === 'users' && !isAdminUser()) s = 'dashboard';
  document.querySelectorAll('.admin-section').forEach(el => el.classList.remove('active'));
  document.querySelectorAll('.sidebar-item').forEach(el => el.classList.remove('active'));
  const sec = document.getElementById('admin-' + s);
  if (sec) sec.classList.add('active');
  replayEnter(sec);
  const map = { dashboard: 0, 'inq-mgmt': 1, stock: 2, pos: 3, pharmacies: 4, geofences: 5, users: 6, settings: 7, backup: 8 };
  const items = document.querySelectorAll('.sidebar-item');
  if (map[s] !== undefined && items[map[s]]) items[map[s]].classList.add('active');

  if (isLiveMode() && isStaffOrAdmin()) {
    if (s === 'dashboard') loadAdminDashboard();
    if (s === 'inq-mgmt') loadAdminInquiries();
    if (s === 'stock') loadAdminStock();
    if (s === 'pos') loadAdminPos();
    if (s === 'pharmacies') loadAdminPharmacies();
    if (s === 'geofences') {
      loadAdminGeofences();
      setTimeout(() => adminGeofenceMap?.invalidateSize(), 320);
    }
    if (s === 'users') loadAdminUsers();
    if (s === 'settings') loadAdminSettings();
    if (s === 'backup') loadAdminBackup();
  }
}

function selectPharmacy(el) {
  if (el?.dataset?.pharmacyId) {
    selectPharmacyFromList(Number(el.dataset.pharmacyId));
    return;
  }
  document.querySelectorAll('.pharmacy-item').forEach(p => p.classList.remove('active'));
  el.classList.add('active');
}

function toggleAuthMode(mode) {
  document.getElementById('auth-login-btn').classList.toggle('active', mode === 'login');
  document.getElementById('auth-signup-btn').classList.toggle('active', mode === 'signup');
  const loginForm = document.getElementById('auth-login-form');
  const signupForm = document.getElementById('auth-signup-form');
  loginForm?.classList.toggle('hidden', mode !== 'login');
  signupForm?.classList.toggle('hidden', mode !== 'signup');
  if (mode === 'signup') loadSignupTerms();
  replayEnter(mode === 'login' ? loginForm : signupForm);
}

function syncSignupTerms() {
  const agree = document.getElementById('signup-terms-agree');
  const btn = document.getElementById('signup-submit-btn');
  if (btn) btn.disabled = !agree?.checked;
}

async function loadSignupTerms() {
  const box = document.getElementById('signup-terms');
  if (!box || box.dataset.loaded === '1') return;
  try {
    const res = await fetch('terms-and-agreement.txt');
    if (!res.ok) throw new Error('Terms file missing');
    box.textContent = await res.text();
    box.dataset.loaded = '1';
  } catch (_) {
    box.textContent = 'The terms could not be loaded. Refresh the page before creating an account.';
  }
}

async function loginUser(event) {
  event?.preventDefault?.();
  const login = document.getElementById('login-username')?.value.trim();
  const password = document.getElementById('login-password')?.value;

  if (!login || !password) {
    alert('Enter username/email and password.');
    return;
  }

  if (!isLiveMode()) {
    saveSession({
      name: login,
      username: login,
      email: login.includes('@') ? login : '',
      role: 'customer',
    }, 'demo');
    switchView('user');
    renderInquiries([]);
    return;
  }

  const btn = document.getElementById('login-submit-btn');
  if (btn) btn.disabled = true;
  try {
    const data = await apiFetch('/login', {
      method: 'POST',
      body: JSON.stringify({ login, password }),
    });
    const user = data.user || data;
    saveSession(user, data.token);
    applyGeofenceAccessControl();
    if (isStaffOrAdmin()) {
      switchView('admin');
    } else {
      switchView('user');
      await loadInquiries();
    }
    maybeOfferGuide();
  } catch (err) {
    alert(err.message);
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function registerUser(event) {
  event?.preventDefault?.();
  const username = document.getElementById('signup-username')?.value.trim();
  const email = document.getElementById('signup-email')?.value.trim();
  const password = document.getElementById('signup-password')?.value;

  if (!username || !email || !password) {
    alert('Fill in username, email, and password.');
    return;
  }
  if (password.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }
  if (!document.getElementById('signup-terms-agree')?.checked) {
    alert('Please read and agree to the Terms of Service before creating an account.');
    return;
  }

  if (!isLiveMode()) {
    saveSession({ name: username, username, email, role: 'customer' }, 'demo');
    switchView('user');
    renderInquiries([]);
    return;
  }

  const btn = document.getElementById('signup-submit-btn');
  if (btn) btn.disabled = true;
  try {
    const data = await apiFetch('/register', {
      method: 'POST',
      body: JSON.stringify({
        name: username,
        username,
        email,
        password,
        accepted_terms: true,
      }),
    });
    if (data.verification_required) {
      showSignupCodeStep(email);
      return;
    }
    saveSession(data.user || data, data.token);
    switchView('user');
    await loadInquiries();
  } catch (err) {
    alert(err.message);
  } finally {
    const waitingForCode = document.getElementById('signup-verify') && !document.getElementById('signup-verify').classList.contains('hidden');
    if (btn) {
      btn.disabled = waitingForCode ? true : !document.getElementById('signup-terms-agree')?.checked;
      btn.classList.toggle('hidden', Boolean(waitingForCode));
    }
  }
}

function showSignupCodeStep(email) {
  const note = document.getElementById('signup-verify-note');
  if (note) {
    note.textContent = `A 6-digit code was sent to ${email}. Enter it below to create the account. Check the spam folder if it is not in the inbox.`;
  }
  document.getElementById('signup-submit-btn')?.classList.add('hidden');
  document.getElementById('signup-verify')?.classList.remove('hidden');
  document.getElementById('signup-code')?.focus();
}

async function resendSignupCode() {
  const btn = document.getElementById('signup-resend-btn');
  if (btn) btn.disabled = true;
  try {
    await registerUser({ preventDefault() {} });
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function confirmSignup() {
  const email = document.getElementById('signup-email')?.value.trim();
  const code = document.getElementById('signup-code')?.value.trim();
  if (!email || !code) {
    alert('Enter the 6-digit code sent to your email.');
    return;
  }

  const btn = document.getElementById('signup-confirm-btn');
  if (btn) btn.disabled = true;
  try {
    const data = await apiFetch('/register/confirm', {
      method: 'POST',
      body: JSON.stringify({ email, code }),
    });
    saveSession(data.user || data, data.token);
    switchView('user');
    await loadInquiries();
    maybeOfferGuide();
  } catch (err) {
    alert(err.message);
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function logoutUser() {
  if (isLiveMode() && apiToken && apiToken !== 'demo') {
    try {
      await apiFetch('/logout', { method: 'POST' });
    } catch (_) { /* ignore */ }
  }
  clearSession();
  selectedPharmacyId = pharmacies[0]?.id ?? null;
  renderInquiries([]);
  switchView('guest');
  switchTab('home');
}

async function loadInquiries() {
  if (!isLiveMode() || !apiToken) return;
  try {
    const list = await apiFetch('/inquiries');
    renderInquiries(list);
  } catch (err) {
    console.warn('Could not load inquiries:', err.message);
  }
}

async function submitInquiryDemo() {
  const pharmacyId = document.getElementById('inq-pharmacy')?.value;
  const message = document.getElementById('inq-message')?.value.trim();

  if (!pharmacyId) {
    alert('Choose the pharmacy you want to ask.');
    return;
  }
  if (!message) {
    alert('Please enter a message for your inquiry.');
    return;
  }

  if (!isLiveMode()) {
    alert('Inquiry submitted (demo).\n\nPharmacy staff will respond when the backend is connected.');
    return;
  }

  if (!apiToken) {
    alert('Please log in first to submit an inquiry.');
    switchView('auth');
    return;
  }

  try {
    await apiFetch('/inquiries', {
      method: 'POST',
      body: JSON.stringify({
        pharmacy_id: Number(pharmacyId),
        message,
      }),
    });
    alert('Inquiry submitted successfully.');
    document.getElementById('inq-message').value = '';
    await loadInquiries();
  } catch (err) {
    alert(err.message);
  }
}

async function initLiveData() {
  if (!isLiveMode()) return;

  try {
    const health = await fetch(`${API_BASE}/health`, { signal: abortAfter(4000) });
    if (!health.ok) throw new Error('API unavailable');

    userLocation = { ...TARLAC_CENTER, fromGps: false };
    await reloadPublicCatalog();

    if (apiToken && apiToken !== 'demo') {
      try {
        const me = await apiFetch('/me', { timeoutMs: 4000 });
        saveSession(me.user || me, apiToken);
        applyGeofenceAccessControl();
        if (currentUser && isStaffOrAdmin()) {
          updateAdminNav();
        } else {
          updateNavForUser();
        }
        if (!isStaffOrAdmin()) await loadInquiries();
      } catch (_) {
        clearSession();
      }
    }

    refreshLocationInBackground();
  } catch (err) {
    console.warn('Live data could not be loaded:', err.message);
    medicines = [];
    pharmacies = [];
    customerGeofences = [];
    applyMedicineFilters();
    renderPharmacyList(pharmacies);
    const notice = document.getElementById('geofence-notice-text');
    if (notice) notice.textContent = 'Live data could not be loaded. Confirm MySQL is running, then refresh this page.';
    const preview = document.getElementById('home-med-preview');
    if (preview) preview.innerHTML = '<div class="text-muted text-sm">Medicines could not be loaded from the database.</div>';
  }
}

function refreshLocationInBackground() {
  getUserLocation().then(async (loc) => {
    if (!loc.fromGps) return;
    userLocation = loc;
    const [pharmList, geofenceList] = await Promise.all([
      apiFetch(`/pharmacies?lat=${loc.lat}&lng=${loc.lng}`),
      fetchPublicGeofences(),
    ]);
    pharmacies = pharmList;
    customerGeofences = geofenceList;
    selectedPharmacyId = pharmacies[0]?.id ?? selectedPharmacyId;
    renderPharmacyList(pharmacies);
    fillPharmacySelects(pharmacies);
    updateGeofenceNotice();
    updateGeofenceParams();
    updatePharmacyMapOverlay();
    if (customerMap) {
      customerMap.setView([loc.lat, loc.lng], 14);
      renderCustomerMapLayers();
    }
  }).catch(() => { /* keep hospital default */ });
}

/* ── Step 4: Admin (live API) ── */

async function loadAdminData() {
  await Promise.all([loadAdminDashboard(), loadAdminInquiries()]);
}

function setText(id, text) {
  const el = document.getElementById(id);
  if (el) el.textContent = text;
}

async function loadAdminDashboard() {
  if (!isLiveMode() || !isStaffOrAdmin()) return;
  try {
    const data = await apiFetch('/admin/dashboard');
    setText('dash-pharmacies-count', data.pharmacies_count);
    setText('dash-inquiries-today', data.inquiries_today);
    setText('dash-inquiries-pending', `${data.pending_inquiries} pending`);
    setText('dash-medicines-count', data.medicines_tracked);
    setText('dash-low-stock', `${data.low_stock_count} low stock alerts`);
    setText('dash-sales-today', `₱${Number(data.sales_today).toFixed(2)}`);
    setText('dash-geofences-active', data.geofences.active);
    setText('dash-assigned-pharmacies', data.geofences.assigned_pharmacies);
    setText('dash-coverage-radius', formatCoverageRadius(data.geofences.radius_min_meters, data.geofences.radius_max_meters));
    setText('dash-users-total', data.users.registered);
    setText('admin-pending-badge', data.pending_inquiries);
    setText('admin-inq-header-badge', `${data.pending_inquiries} pending`);

    const activityEl = document.getElementById('dash-activity-list');
    if (activityEl) {
      const items = [...(data.recent_activity || []), ...(data.low_stock_alerts || []).map(a => ({
        type: 'low_stock',
        label: a.name,
        pharmacy: a.pharmacy,
        time: `${a.stock_quantity} pcs · ${a.availability_status}`,
      }))];
      if (!items.length) {
        activityEl.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No recent activity.</div>';
      } else {
        activityEl.innerHTML = items.slice(0, 6).map(item => {
          const icon = item.type === 'low_stock'
            ? { bg: '#FEF9C3', color: '#854d0e', ti: 'ti-alert-triangle' }
            : item.type === 'inquiry_replied'
              ? { bg: '#DCFCE7', color: '#166534', ti: 'ti-message-check' }
              : { bg: 'var(--blue-light)', color: 'var(--blue-dark)', ti: 'ti-message-question' };
          return `
            <div class="activity-item">
              <div class="activity-icon" style="background:${icon.bg};">
                <i class="ti ${icon.ti}" style="color:${icon.color};"></i>
              </div>
              <div class="activity-text">
                ${item.type === 'low_stock' ? 'Low stock' : 'Inquiry'} — <strong>${item.label}</strong>
                <div class="activity-time">${item.time || ''}${item.pharmacy ? ` · ${item.pharmacy}` : ''}</div>
              </div>
            </div>`;
        }).join('');
      }
    }
  } catch (err) {
    console.warn('Dashboard load failed:', err.message);
  }
}

function inquiryStatusBadge(status) {
  if (status === 'resolved') return ['badge-green', 'Replied'];
  if (status === 'in_progress') return ['badge-blue', 'In progress'];
  return ['badge-amber', 'Pending'];
}

async function loadAdminInquiries() {
  if (!isLiveMode() || !isStaffOrAdmin()) return;
  try {
    adminInquiries = await apiFetch('/inquiries');
    renderAdminInquiries();
    const pending = adminInquiries.filter(i => i.status === 'pending').length;
    setText('admin-pending-badge', pending);
    setText('admin-inq-header-badge', `${pending} pending`);
  } catch (err) {
    console.warn('Admin inquiries load failed:', err.message);
  }
}

function inquiryIsReplied(inq) {
  return inq?.status === 'resolved' && String(inq.response || '').trim() !== '';
}

function formatInquiryWhen(iso) {
  if (!iso) return '—';
  const date = new Date(iso);
  if (Number.isNaN(date.getTime())) return '—';
  return date.toLocaleString(undefined, {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
    hour: 'numeric',
    minute: '2-digit',
  });
}

function repliedInquiryGroups() {
  const groups = new Map();

  adminInquiries.forEach((inq) => {
    if (!inquiryIsReplied(inq)) return;
    const id = inq.pharmacy_id || inq.pharmacy?.id || 0;
    const key = String(id);
    if (!groups.has(key)) {
      groups.set(key, {
        id,
        name: inq.pharmacy?.name || (id ? 'Pharmacy' : 'No pharmacy selected'),
        items: [],
      });
    }
    groups.get(key).items.push(inq);
  });

  return [...groups.values()].sort((a, b) => String(a.name).localeCompare(String(b.name)));
}

function renderAdminInquiries() {
  const tbody = document.getElementById('admin-inq-table-body');
  if (!tbody) return;

  const waiting = adminInquiries.filter((inq) => !inquiryIsReplied(inq));

  if (!waiting.length) {
    const empty = isPharmacyScopedUser()
      ? 'No inquiries waiting for a reply at your pharmacy.'
      : 'No inquiries waiting for a reply.';
    tbody.innerHTML = `<tr><td colspan="5" class="text-muted text-sm" style="padding:16px;">${empty}</td></tr>`;
  } else {
    tbody.innerHTML = waiting.map(inq => {
      const [badgeClass, badgeLabel] = inquiryStatusBadge(inq.status);
      const initials = userInitials(inq.user?.name);
      const med = inq.medicine?.name || 'General';
      const pharm = inq.pharmacy?.name || '—';
      return `
        <tr>
          <td>
            <div style="display:flex;align-items:center;gap:8px;">
              <div style="width:30px;height:30px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--green-700);">${initials}</div>
              ${escapeHtml(inq.user?.name || 'Customer')}
            </div>
          </td>
          <td>${escapeHtml(med)}</td>
          <td>${escapeHtml(pharm)}</td>
          <td><span class="badge ${badgeClass}">${badgeLabel}</span></td>
          <td><button class="btn btn-xs btn-primary" type="button" onclick="selectAdminInquiry(${inq.id})"><i class="ti ti-send"></i> Reply</button></td>
        </tr>`;
    }).join('');
  }

  renderRepliedInquiryHistory();
}

function renderRepliedInquiryHistory() {
  const host = document.getElementById('admin-inquiry-history');
  if (!host) return;

  const groups = repliedInquiryGroups();
  if (!groups.length) {
    host.replaceChildren();
    return;
  }

  host.innerHTML = `<div class="inquiry-history">${groups.map((group) => {
    const rows = group.items.map((inq) => {
          const customer = inq.user?.name || 'Customer';
          const med = inq.medicine?.name || 'General';
          const question = inq.message || 'No message';
          const reply = inq.response || '';
          const when = formatInquiryWhen(inq.updated_at || inq.created_at);
          return `
            <tr>
              <td>${escapeHtml(customer)}</td>
              <td>${escapeHtml(med)}</td>
              <td><div class="inquiry-log-text" title="${escapeHtml(question)}">${escapeHtml(question)}</div></td>
              <td><div class="inquiry-log-text" title="${escapeHtml(reply)}">${escapeHtml(reply)}</div></td>
              <td class="text-sm text-muted">${escapeHtml(when)}</td>
              <td><button class="btn btn-xs btn-danger" type="button" onclick="deleteRepliedInquiry(${inq.id})"><i class="ti ti-trash"></i> Delete</button></td>
            </tr>`;
        }).join('');

    return `
      <div class="card inquiry-log">
        <div class="card-p" style="padding-bottom:0;">
          <div class="card-header">
            <span class="card-title"><i class="ti ti-history"></i> Replied inquiries — ${escapeHtml(group.name)}</span>
          </div>
          <p class="inquiry-log-note">Log of inquiries this pharmacy has already answered. Delete is available only after a reply.</p>
        </div>
        <div class="table-scroll">
          <table class="data-table">
            <thead>
              <tr>
                <th>Customer</th>
                <th>Medicine</th>
                <th>Inquiry</th>
                <th>Reply</th>
                <th>Replied</th>
                <th></th>
              </tr>
            </thead>
            <tbody>${rows}</tbody>
          </table>
        </div>
      </div>`;
  }).join('')}</div>`;
}

function selectAdminInquiry(id) {
  selectedAdminInquiryId = id;
  const inq = adminInquiries.find(i => i.id === id);
  if (!inq) return;
  const med = inq.medicine?.name || 'General inquiry';
  const pharm = inq.pharmacy?.name || 'Pharmacy';
  const title = document.getElementById('admin-reply-title');
  const input = document.getElementById('admin-reply-input');
  const body = document.getElementById('admin-reply-body');
  if (title) title.innerHTML = `<i class="ti ti-message-reply"></i> Reply — ${escapeHtml(med)} · ${escapeHtml(pharm)}`;
  if (body) {
    const customer = inq.user?.name || 'Customer';
    body.innerHTML = `<strong>${escapeHtml(customer)}</strong> asked: ${escapeHtml(inq.message || 'No message')}`;
  }
  if (input) input.value = inq.response || '';
  document.getElementById('admin-reply-panel')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function setReplyTemplate(text) {
  const input = document.getElementById('admin-reply-input');
  if (input) input.value = text;
}

function resetAdminReplyPanel() {
  const title = document.getElementById('admin-reply-title');
  const body = document.getElementById('admin-reply-body');
  const input = document.getElementById('admin-reply-input');
  if (title) title.innerHTML = '<i class="ti ti-message-reply"></i> Select an inquiry to reply';
  if (body) body.textContent = "Choose an inquiry to read the customer's question.";
  if (input) input.value = '';
}

async function sendAdminReply() {
  if (!selectedAdminInquiryId) {
    alert('Select an inquiry from the table first.');
    return;
  }
  const response = document.getElementById('admin-reply-input')?.value.trim();
  if (!response) {
    alert('Enter a reply message.');
    return;
  }
  try {
    await apiFetch(`/inquiries/${selectedAdminInquiryId}`, {
      method: 'PATCH',
      body: JSON.stringify({ response, status: 'resolved' }),
    });
    alert('Reply sent.');
    selectedAdminInquiryId = null;
    resetAdminReplyPanel();
    await loadAdminInquiries();
    await loadAdminDashboard();
  } catch (err) {
    alert(err.message);
  }
}

let repliedInquiryDeleteId = null;

async function deleteRepliedInquiry(id) {
  const numericId = Number(id);
  if (repliedInquiryDeleteId === numericId) return;

  const inq = adminInquiries.find((item) => Number(item.id) === numericId);
  if (!inq || !inquiryIsReplied(inq)) {
    alert('Only replied inquiries can be deleted.');
    return;
  }
  const med = inq.medicine?.name || 'this inquiry';
  const pharm = inq.pharmacy?.name || 'this pharmacy';
  if (!window.confirm(`Delete the replied inquiry about ${med} from ${pharm}?`)) return;

  repliedInquiryDeleteId = numericId;
  try {
    await apiFetch(`/inquiries/${numericId}`, { method: 'DELETE' });
    adminInquiries = adminInquiries.filter((item) => Number(item.id) !== numericId);
    if (Number(selectedAdminInquiryId) === numericId) {
      selectedAdminInquiryId = null;
      resetAdminReplyPanel();
    }
    renderAdminInquiries();
    await loadAdminInquiries();
    await loadAdminDashboard();
  } catch (err) {
    alert(err.message);
    await loadAdminInquiries();
  } finally {
    repliedInquiryDeleteId = null;
  }
}

function stockBarClass(qty, status) {
  if (status === 'out_of_stock' || qty === 0) return ['fill-low', 0];
  if (status === 'low') return ['fill-mid', Math.min(100, qty)];
  return ['fill-high', Math.min(100, Math.round((qty / 60) * 100))];
}

function stockBadgeClass(status) {
  if (status === 'out_of_stock') return ['badge-red', 'Out of stock'];
  if (status === 'low') return ['badge-amber', 'Low stock'];
  return ['badge-green', 'In stock'];
}

let adminStockRows = [];
let lowStockSaveTimer = null;

function statusForQuantity(qty, threshold) {
  const quantity = Number(qty);
  const limit = Number(threshold);
  if (quantity <= 0) return 'out_of_stock';
  if (quantity < limit) return 'low';
  return 'available';
}

async function loadAdminStock() {
  if (!isLiveMode() || !isStaffOrAdmin()) return;
  try {
    adminStockRows = await apiFetch('/admin/stock');
    renderAdminStock(adminStockRows);
  } catch (err) {
    console.warn('Stock load failed:', err.message);
  }
}

async function previewLowStockThreshold() {
  const threshold = parseInt(document.getElementById('setting-low-stock-threshold')?.value, 10);
  if (!Number.isInteger(threshold) || threshold < 1 || threshold > 1000) return;

  clearTimeout(lowStockSaveTimer);
  lowStockSaveTimer = setTimeout(() => {
    const latest = parseInt(document.getElementById('setting-low-stock-threshold')?.value, 10);
    if (latest === threshold) persistLowStockThreshold(threshold);
  }, 300);

  if (!adminStockRows.length && isLiveMode() && isStaffOrAdmin()) {
    try {
      adminStockRows = await apiFetch('/admin/stock');
    } catch (_) {
      adminStockRows = [];
    }
  }

  const latest = parseInt(document.getElementById('setting-low-stock-threshold')?.value, 10);
  if (latest !== threshold || !adminStockRows.length) return;

  renderAdminStock(adminStockRows.map((row) => ({
    ...row,
    availability_status: statusForQuantity(row.stock_quantity, threshold),
  })));
}

async function persistLowStockThreshold(threshold) {
  if (!isAdminUser() || !apiToken) return;
  const current = parseInt(adminSettings.low_stock_threshold, 10);
  if (current === threshold) {
    await refreshLowStockSurfaces();
    return;
  }

  try {
    adminSettings = await apiFetch('/admin/settings', {
      method: 'PATCH',
      body: JSON.stringify({ low_stock_threshold: threshold }),
    });
    await refreshLowStockSurfaces();
  } catch (err) {
    console.warn(err.message);
  }
}

async function refreshLowStockSurfaces() {
  await Promise.all([
    loadAdminStock(),
    loadAdminDashboard(),
    reloadPublicCatalog(),
  ]);
}

function renderAdminStock(rows) {
  const tbody = document.getElementById('admin-stock-table-body');
  if (!tbody) return;
  if (!rows.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-sm" style="padding:16px;">No stock records.</td></tr>';
    return;
  }
  tbody.innerHTML = rows.map(row => {
    const [fillClass, width] = stockBarClass(row.stock_quantity, row.availability_status);
    const [badgeClass, badgeLabel] = stockBadgeClass(row.availability_status);
    return `
      <tr>
        <td class="fw-500">${escapeHtml(row.name)}</td>
        <td class="text-muted">${escapeHtml(row.category || '—')}</td>
        <td>${escapeHtml(row.pharmacy)}</td>
        <td>₱${Number(row.price).toFixed(2)}</td>
        <td><div class="stock-row"><span class="stock-qty-num">${row.stock_quantity}</span><div class="stock-bar"><div class="stock-fill ${fillClass}" style="width:${width}%"></div></div></div></td>
        <td><span class="badge ${badgeClass}">${badgeLabel}</span></td>
        <td>
          <div style="display:flex; gap:6px; justify-content:flex-end;">
            <button class="btn btn-xs" type="button" onclick="editStockRow(${row.pharmacy_id}, ${row.medicine_id}, ${row.stock_quantity}, ${row.price})" title="Edit"><i class="ti ti-edit"></i></button>
            <button class="btn btn-xs btn-danger" type="button" onclick="deleteInventoryMedicine(${row.pharmacy_id}, ${row.medicine_id})" title="Delete"><i class="ti ti-trash"></i></button>
          </div>
        </td>
      </tr>`;
  }).join('');
}

async function addInventoryMedicine() {
  const name = window.prompt('Medicine name');
  if (name === null) return;
  const trimmed = name.trim();
  if (!trimmed) {
    alert('Enter a medicine name.');
    return;
  }

  const priceStr = window.prompt('Price (PHP)');
  if (priceStr === null) return;
  const price = parseFloat(priceStr);
  if (Number.isNaN(price) || price < 0) {
    alert('Enter a valid price.');
    return;
  }

  const qtyStr = window.prompt('Amount to add');
  if (qtyStr === null) return;
  const quantity = parseInt(qtyStr, 10);
  if (!Number.isInteger(quantity) || quantity < 1) {
    alert('Enter the amount of medicine to add.');
    return;
  }

  const body = { name: trimmed, price, quantity };
  if (!isPharmacyScopedUser()) {
    if (!adminPharmacies.length) {
      try {
        adminPharmacies = await apiFetch('/admin/pharmacies');
      } catch (err) {
        alert(err.message);
        return;
      }
    }
    const pharmacyName = window.prompt('Pharmacy');
    if (pharmacyName === null) return;
    const match = adminPharmacies.find((pharmacy) => pharmacy.name.toLowerCase() === pharmacyName.trim().toLowerCase());
    if (!match) {
      alert('Enter the exact pharmacy name.');
      return;
    }
    body.pharmacy_id = match.id;
  }

  try {
    const result = await apiFetch('/admin/stock', {
      method: 'POST',
      body: JSON.stringify(body),
    });
    await loadAdminStock();
    await loadAdminDashboard();
    const availability = await apiFetch('/availability');
    if (availability.length) {
      medicines = normalizeMedicines(availability);
      applyMedicineFilters();
    }
    alert(result.added_to_existing
      ? `${result.name} updated. Stock is now ${result.stock_quantity}.`
      : `${result.name} added to inventory.`);
  } catch (err) {
    alert(err.message);
  }
}

async function deleteInventoryMedicine(pharmacyId, medicineId) {
  const row = adminStockRows.find((item) => Number(item.pharmacy_id) === Number(pharmacyId) && Number(item.medicine_id) === Number(medicineId));
  const name = row?.name || 'this medicine';
  const pharmacy = row?.pharmacy || 'this pharmacy';
  if (!window.confirm(`Remove ${name} from ${pharmacy}?`)) return;

  try {
    await apiFetch(`/admin/stock/${pharmacyId}/${medicineId}`, { method: 'DELETE' });
    await loadAdminStock();
    await loadAdminDashboard();
    const availability = await apiFetch('/availability');
    if (Array.isArray(availability)) {
      medicines = normalizeMedicines(availability);
      applyMedicineFilters();
    }
  } catch (err) {
    alert(err.message);
  }
}

async function editStockRow(pharmacyId, medicineId, currentQty, currentPrice) {
  const qtyStr = prompt('Update stock quantity:', String(currentQty));
  if (qtyStr === null) return;
  const qty = parseInt(qtyStr, 10);
  if (Number.isNaN(qty) || qty < 0) {
    alert('Enter a valid quantity.');
    return;
  }
  const priceStr = prompt('Update price (PHP):', String(currentPrice));
  if (priceStr === null) return;
  const price = parseFloat(priceStr);
  if (Number.isNaN(price) || price < 0) {
    alert('Enter a valid price.');
    return;
  }
  try {
    await apiFetch(`/admin/stock/${pharmacyId}/${medicineId}`, {
      method: 'PATCH',
      body: JSON.stringify({ stock_quantity: qty, price }),
    });
    await loadAdminStock();
    await loadAdminDashboard();
    const availability = await apiFetch('/availability');
    if (availability.length) {
      medicines = normalizeMedicines(availability);
      applyMedicineFilters();
    }
  } catch (err) {
    alert(err.message);
  }
}

/* ── Step 5.1: Admin pharmacies (live API) ── */

const PHARM_AVATAR_STYLES = [
  { bg: 'var(--green-light)', color: 'var(--green)' },
  { bg: 'var(--blue-light)', color: 'var(--blue)' },
  { bg: '#FEF9C3', color: '#854d0e' },
];

async function refreshCustomerPharmacies() {
  if (!isLiveMode()) return;
  const loc = userLocation.fromGps ? userLocation : await getUserLocation();
  userLocation = loc;
  const [pharmList, geofenceList] = await Promise.all([
    apiFetch(`/pharmacies?lat=${loc.lat}&lng=${loc.lng}`),
    fetchPublicGeofences(),
  ]);
  pharmacies = pharmList;
  customerGeofences = geofenceList;
  if (!pharmacies.find(p => p.id === selectedPharmacyId)) {
    selectedPharmacyId = pharmacies[0]?.id ?? null;
  }
  renderPharmacyList(pharmacies);
  fillPharmacySelects(pharmacies);
  updateGeofenceNotice();
  updateGeofenceParams();
  updatePharmacyMapOverlay();
  if (customerMap) renderCustomerMapLayers();
}

async function loadAdminPharmacies() {
  if (!isLiveMode() || !isStaffOrAdmin()) return;
  const addBtn = document.getElementById('admin-pharmacy-add-btn');
  if (addBtn) {
    addBtn.classList.toggle('hidden', currentUser?.role !== 'admin');
  }
  try {
    adminPharmacies = await apiFetch('/admin/pharmacies');
    renderAdminPharmacies();
  } catch (err) {
    const list = document.getElementById('admin-pharmacy-list');
    if (list) list.innerHTML = `<div class="text-muted text-sm" style="padding:12px;">Could not load pharmacies: ${err.message}</div>`;
  }
}

function renderAdminPharmacies() {
  const list = document.getElementById('admin-pharmacy-list');
  if (!list) return;

  if (!adminPharmacies.length) {
    list.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No pharmacies found.</div>';
    return;
  }

  const isAdmin = isAdminUser();

  list.innerHTML = adminPharmacies.map((p, i) => {
    const style = PHARM_AVATAR_STYLES[i % PHARM_AVATAR_STYLES.length];
    const geofenceNames = (p.geofences || []).map(g => g.name).join(', ') || 'None assigned';
    const statusBadge = p.is_active
      ? '<span class="badge badge-green">Active</span>'
      : '<span class="badge badge-red">Inactive</span>';
    const tphBadge = p.inside_tph
      ? '<span class="badge badge-amber">Inside TPH (hidden from users)</span>'
      : '';
    const deleteBtn = isAdmin
      ? `<button class="btn btn-sm btn-danger" type="button" onclick="deletePharmacy(${p.id})"><i class="ti ti-trash"></i> Delete</button>`
      : '';

    return `
      <div class="pharm-profile">
        <div class="pharm-profile-top">
          <div class="pharm-profile-left">
            <div class="pharm-profile-avatar" style="background:${style.bg};">
              <i class="ti ti-building-store" style="color:${style.color}; font-size:22px;"></i>
            </div>
            <div>
              <div class="pharm-profile-name">${escapeHtml(p.name)}</div>
              <div class="pharm-profile-loc">${escapeHtml(p.address || 'No address')}</div>
            </div>
          </div>
          <div class="pharm-profile-actions">
            ${statusBadge}
            ${tphBadge}
            <button class="btn btn-sm" type="button" onclick="openPharmacyForm(${p.id})"><i class="ti ti-edit"></i> Edit</button>
            ${deleteBtn}
          </div>
        </div>
        <div class="pharm-profile-meta">
          <div class="pharm-meta-item">Hours: <span>${escapeHtml(p.operating_hours || '—')}</span></div>
          <div class="pharm-meta-item">Contact: <span>${escapeHtml(p.contact_number || '—')}</span></div>
          <div class="pharm-meta-item">Geofence: <span style="color:var(--green);">${escapeHtml(geofenceNames)}</span></div>
          <div class="pharm-meta-item">Latitude: <span>${p.latitude ?? '—'}</span></div>
          <div class="pharm-meta-item">Longitude: <span>${p.longitude ?? '—'}</span></div>
        </div>
      </div>`;
  }).join('');
}

function escapeHtml(text) {
  return String(text)
    .replace(/&/g, '&amp;')
    .replace(/</g, '&lt;')
    .replace(/>/g, '&gt;')
    .replace(/"/g, '&quot;');
}

function openPharmacyForm(id) {
  if (id == null && currentUser?.role !== 'admin') {
    alert('Only admins can add pharmacies.');
    return;
  }

  const panel = document.getElementById('admin-pharmacy-form-panel');
  const title = document.getElementById('admin-pharmacy-form-title');
  const activeWrap = document.getElementById('admin-pharmacy-active-wrap');
  const editId = document.getElementById('admin-pharmacy-edit-id');

  if (id != null) {
    const p = adminPharmacies.find(x => x.id === id);
    if (!p) return;
    editId.value = String(p.id);
    document.getElementById('admin-pharmacy-name').value = p.name || '';
    document.getElementById('admin-pharmacy-address').value = p.address || '';
    document.getElementById('admin-pharmacy-contact').value = p.contact_number || '';
    document.getElementById('admin-pharmacy-hours').value = p.operating_hours || '';
    document.getElementById('admin-pharmacy-lat').value = p.latitude ?? '';
    document.getElementById('admin-pharmacy-lng').value = p.longitude ?? '';
    document.getElementById('admin-pharmacy-active').checked = !!p.is_active;
    const insideTph = document.getElementById('admin-pharmacy-inside-tph');
    if (insideTph) insideTph.checked = !!p.inside_tph;
    if (title) title.innerHTML = '<i class="ti ti-edit"></i> Edit pharmacy';
    if (activeWrap) activeWrap.classList.remove('hidden');
  } else {
    editId.value = '';
    document.getElementById('admin-pharmacy-name').value = '';
    document.getElementById('admin-pharmacy-address').value = '';
    document.getElementById('admin-pharmacy-contact').value = '';
    document.getElementById('admin-pharmacy-hours').value = '';
    document.getElementById('admin-pharmacy-lat').value = '15.4890';
    document.getElementById('admin-pharmacy-lng').value = '120.5980';
    document.getElementById('admin-pharmacy-active').checked = true;
    const insideTph = document.getElementById('admin-pharmacy-inside-tph');
    if (insideTph) insideTph.checked = false;
    if (title) title.innerHTML = '<i class="ti ti-plus"></i> Add pharmacy';
    if (activeWrap) activeWrap.classList.add('hidden');
  }

  panel?.classList.remove('hidden');
  panel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closePharmacyForm() {
  document.getElementById('admin-pharmacy-form-panel')?.classList.add('hidden');
  const editId = document.getElementById('admin-pharmacy-edit-id');
  if (editId) editId.value = '';
}

async function savePharmacy() {
  const id = document.getElementById('admin-pharmacy-edit-id')?.value;
  const name = document.getElementById('admin-pharmacy-name')?.value.trim();
  const address = document.getElementById('admin-pharmacy-address')?.value.trim();
  const contact_number = document.getElementById('admin-pharmacy-contact')?.value.trim();
  const operating_hours = document.getElementById('admin-pharmacy-hours')?.value.trim();
  const latitude = parseFloat(document.getElementById('admin-pharmacy-lat')?.value);
  const longitude = parseFloat(document.getElementById('admin-pharmacy-lng')?.value);

  if (!name) {
    alert('Enter a pharmacy name.');
    return;
  }
  if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
    alert('Enter valid latitude and longitude.');
    return;
  }

  const body = {
    name,
    address: address || null,
    contact_number: contact_number || null,
    operating_hours: operating_hours || null,
    latitude,
    longitude,
    inside_tph: !!document.getElementById('admin-pharmacy-inside-tph')?.checked,
  };

  if (id) {
    body.is_active = document.getElementById('admin-pharmacy-active')?.checked ?? true;
  }

  try {
    if (id) {
      await apiFetch(`/admin/pharmacies/${id}`, { method: 'PATCH', body: JSON.stringify(body) });
    } else {
      await apiFetch('/admin/pharmacies', { method: 'POST', body: JSON.stringify(body) });
    }
    closePharmacyForm();
    await loadAdminPharmacies();
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
  } catch (err) {
    alert(err.message);
  }
}

async function deletePharmacy(id) {
  const pharmacy = adminPharmacies.find(p => p.id === id);
  const name = pharmacy?.name || 'this pharmacy';
  if (!confirm(`Delete ${name}? It will be removed from the map, along with its stock and sales. This cannot be undone.`)) return;
  try {
    await apiFetch(`/admin/pharmacies/${id}`, { method: 'DELETE' });
    closePharmacyForm();
    await Promise.all([
      loadAdminPharmacies(),
      loadAdminGeofences(),
      refreshCustomerPharmacies(),
      refreshCustomerMedicines(),
      loadAdminDashboard(),
    ]);
  } catch (err) {
    alert(err.message);
  }
}

function geofenceZoneColor(index) {
  return GEOFENCE_ZONE_COLORS[index % GEOFENCE_ZONE_COLORS.length];
}

function formatRadiusKm(meters) {
  const value = Number(meters);
  if (!Number.isFinite(value)) return '';
  if (value < 1000) return `${Math.round(value)} m`;
  const km = value / 1000;
  return Number.isInteger(km) ? `${km} km` : `${km.toFixed(1)} km`;
}

async function loadAdminGeofences() {
  applyGeofenceAccessControl();
  if (!isLiveMode() || !isStaffOrAdmin()) return;

  try {
    const [geofenceList, pharmacyList] = await Promise.all([
      apiFetch('/admin/geofences'),
      apiFetch('/admin/pharmacies'),
    ]);
    adminGeofences = geofencesVisibleToCurrentUser(geofenceList);
    adminPharmacies = pharmacyList;
    if (!adminGeofences.find(g => g.id === selectedAdminGeofenceId)) {
      selectedAdminGeofenceId = adminGeofences.find(g => g.is_active)?.id ?? adminGeofences[0]?.id ?? null;
    }
    renderAdminGeofences();
    if (isAdminUser()) fillGeofenceAssignSelects();
    ensureAdminGeofenceMap();
    renderAdminGeofenceMapLayers();
    updateAdminGeofenceMapOverlay();
  } catch (err) {
    const list = document.getElementById('admin-geofence-list');
    if (list) list.innerHTML = `<div class="text-muted text-sm" style="padding:12px;">Could not load geofences: ${escapeHtml(err.message)}</div>`;
  } finally {
    applyGeofenceAccessControl();
  }
}

function applyGeofenceAccessControl() {
  const isAdmin = isAdminUser();
  const seesStaffScreens = isPharmacyScopedUser();

  document.getElementById('admin-geofence-add-btn')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('admin-geofence-admin-notice')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('admin-geofence-staff-notice')?.classList.toggle('hidden', !seesStaffScreens);
  document.getElementById('admin-geofence-owner-notice')?.classList.add('hidden');
  document.getElementById('admin-pharmacy-owner-notice')?.classList.add('hidden');
  const staffZoneBtn = document.getElementById('staff-geofence-add-btn');
  if (staffZoneBtn) {
    const hasZone = staffHasPharmacyZone();
    staffZoneBtn.disabled = false;
    staffZoneBtn.innerHTML = hasZone
      ? '<i class="ti ti-map-pin"></i> Update location'
      : '<i class="ti ti-map-pin"></i> Save location';
  }
  fillStaffPharmacyLocation();

  document.getElementById('sidebar-users')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('settings-users-card')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('admin-users-staff-notice')?.classList.toggle('hidden', true);
  document.getElementById('admin-users-panel')?.classList.toggle('hidden', !isAdmin);
  if (!isAdmin && document.getElementById('admin-users')?.classList.contains('active')) {
    switchAdminSection('dashboard');
  }
  document.getElementById('admin-settings-staff-notice')?.classList.toggle('hidden', isAdmin);
  document.getElementById('setting-save-btn')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('admin-settings-backup-card')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('admin-backup-staff-notice')?.classList.toggle('hidden', !seesStaffScreens);
  document.getElementById('export-admin-fields')?.classList.toggle('hidden', !isAdmin);
  document.getElementById('export-download-btn')?.classList.toggle('hidden', !(isAdmin || seesStaffScreens));

  if (!isAdmin) {
    closeGeofenceForm();
    closeAdminUserForm();
  }

  if (adminGeofences.length && document.getElementById('admin-geofence-list')) {
    renderAdminGeofences();
  }
}

function isHospitalGeofence(zone) {
  return /TPH|Tarlac Provincial/i.test(String(zone?.name || ''));
}

function isStartingPoint(zone) {
  if (!zone || Number(zone.radius_meters) === 50) return false;
  if (zone.is_starting_point === true || zone.is_starting_point === 1 || zone.is_starting_point === '1') return true;
  return isHospitalGeofence(zone);
}

function pointInsideGeofence(lat, lng, zone) {
  if (lat == null || lng == null || zone?.center_latitude == null || zone?.center_longitude == null) return false;
  return haversineKm(Number(lat), Number(lng), Number(zone.center_latitude), Number(zone.center_longitude)) * 1000
    <= Number(zone.radius_meters);
}

function chooseStartingPoint(matches, pharmacy) {
  if (!matches.length) return null;
  const hospital = matches.find(isHospitalGeofence);
  const hospitalLat = hospital?.center_latitude ?? TARLAC_CENTER.lat;
  const hospitalLng = hospital?.center_longitude ?? TARLAC_CENTER.lng;
  const distinct = matches.filter(zone => {
    if (isHospitalGeofence(zone)) return false;
    return haversineKm(Number(zone.center_latitude), Number(zone.center_longitude), hospitalLat, hospitalLng) * 1000 > 200;
  });
  const pool = distinct.length
    ? distinct
    : (hospital ? matches.filter(isHospitalGeofence) : matches);
  pool.sort((a, b) => {
    const radiusDiff = Number(a.radius_meters) - Number(b.radius_meters);
    if (radiusDiff) return radiusDiff;
    return haversineKm(pharmacy.latitude, pharmacy.longitude, a.center_latitude, a.center_longitude)
      - haversineKm(pharmacy.latitude, pharmacy.longitude, b.center_latitude, b.center_longitude);
  });
  return pool[0] || null;
}

function startingPointForPharmacy(pharmacy, zones) {
  if (!pharmacy || pharmacy.latitude == null || pharmacy.longitude == null) return null;
  const source = zones || customerGeofences;
  const assigned = source.filter(zone => {
    if (!isStartingPoint(zone) || zone.is_active === false) return false;
    return source.some(child => String(child.parent_id) === String(zone.id)
      && (child.pharmacies || []).some(item => String(item.id) === String(pharmacy.id)));
  });
  const assignedPoint = chooseStartingPoint(assigned, pharmacy);
  if (assignedPoint) return assignedPoint;
  const matches = source.filter(zone => {
    if (!isStartingPoint(zone) || zone.is_active === false) return false;
    return pointInsideGeofence(pharmacy.latitude, pharmacy.longitude, zone);
  });
  return chooseStartingPoint(matches, pharmacy);
}

function directionsOriginFor(pharmacy) {
  const start = startingPointForPharmacy(pharmacy);
  if (start) {
    return {
      lat: Number(start.center_latitude),
      lng: Number(start.center_longitude),
      name: start.name,
    };
  }
  return { lat: TARLAC_CENTER.lat, lng: TARLAC_CENTER.lng, name: 'Tarlac Provincial Hospital' };
}

function geofencesVisibleToCurrentUser(zones) {
  const list = Array.isArray(zones) ? zones : [];
  if (currentUser?.role !== 'staff' && currentUser?.role !== 'owner') return list;
  const pharmacyId = Number(currentUser.pharmacy_id);
  if (!pharmacyId) return [];
  return list.filter(zone => {
    const ids = (zone.pharmacies || []).map(p => Number(p.id));
    if (!ids.includes(pharmacyId)) return false;
    if (isStartingPoint(zone)) return true;
    return ids.length === 1 && ids[0] === pharmacyId;
  }).map(zone => {
    if (!isStartingPoint(zone)) return zone;
    return {
      ...zone,
      pharmacies: (zone.pharmacies || []).filter(p => Number(p.id) === pharmacyId),
    };
  });
}

function geofenceContainsZone(parent, child) {
  if (!parent || !child || parent.id === child.id) return false;
  if (parent.center_latitude == null || child.center_latitude == null) return false;
  if (Number(parent.radius_meters) <= Number(child.radius_meters)) return false;
  return haversineKm(child.center_latitude, child.center_longitude, parent.center_latitude, parent.center_longitude) * 1000
    <= Number(parent.radius_meters);
}

function nestGeofenceZones(zones) {
  const byId = new Map(zones.map(zone => [String(zone.id), zone]));
  const parentById = new Map();
  zones.forEach(zone => {
    const parent = zone.parent_id != null ? byId.get(String(zone.parent_id)) : null;
    if (parent && String(parent.id) !== String(zone.id)) {
      parentById.set(String(zone.id), String(parent.id));
    }
  });

  const childrenByParent = new Map();
  const roots = [];
  zones.forEach(zone => {
    const parentId = parentById.get(String(zone.id));
    if (parentId == null) {
      roots.push(zone);
      return;
    }
    if (!childrenByParent.has(parentId)) childrenByParent.set(parentId, []);
    childrenByParent.get(parentId).push(zone);
  });

  const byName = (a, b) => String(a.name).localeCompare(String(b.name));
  roots.sort(byName);
  childrenByParent.forEach(list => list.sort(byName));
  return { roots, childrenByParent };
}

function geofenceFenceItem(g, nested) {
  const colorIndex = Math.max(0, adminGeofences.findIndex(zone => zone.id === g.id));
  const color = geofenceZoneColor(colorIndex);
  const pharmCount = (g.pharmacies || []).length;
  const nestedCount = adminGeofences.filter(zone => String(zone.parent_id) === String(g.id)).length;
  const countLabel = isStartingPoint(g)
    ? `${nestedCount} ${nestedCount === 1 ? 'geofence' : 'geofences'}`
    : `${pharmCount} ${pharmCount === 1 ? 'pharmacy' : 'pharmacies'}`;
  const activeClass = String(g.id) === String(selectedAdminGeofenceId) ? ' active' : '';
  const nestedClass = nested ? ' fence-nested' : '';
  const statusNote = g.is_active ? '' : ' · Inactive';
  const startNote = isStartingPoint(g) ? ' · Starting point' : '';
  const isAdmin = isAdminUser();
  const insideBtn = isAdmin && isStartingPoint(g) && g.is_active
    ? `<button class="btn btn-xs" type="button" onclick="event.stopPropagation(); openGeofenceInside(${g.id})" title="Add a pharmacy inside"><i class="ti ti-plus"></i></button>`
    : '';
  const editBtn = isAdmin
    ? `<button class="btn btn-xs" type="button" onclick="event.stopPropagation(); openGeofenceForm(${g.id})" title="Edit"><i class="ti ti-edit"></i></button>`
    : '';
  const deleteBtn = isAdmin
    ? `<button class="btn btn-xs btn-danger" type="button" onclick="event.stopPropagation(); deleteGeofence(${g.id})" title="Delete"><i class="ti ti-trash"></i></button>`
    : '';

  return `
    <div class="fence-item${activeClass}${nestedClass}" data-geofence-id="${g.id}" onclick="selectAdminGeofence(${g.id})">
      <div class="fence-dot" style="background:${color};"></div>
      <div class="fence-info">
        <div class="fence-name">${escapeHtml(g.name)}</div>
        <div class="fence-meta">${countLabel} · ${formatRadiusKm(g.radius_meters)}${startNote}${statusNote}</div>
      </div>
      <div class="fence-actions">${insideBtn}${editBtn}${deleteBtn}</div>
    </div>`;
}

function renderAdminGeofences() {
  const list = document.getElementById('admin-geofence-list');
  if (!list) return;

  if (!adminGeofences.length) {
    list.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No geofence zones yet.</div>';
    return;
  }

  const { roots, childrenByParent } = nestGeofenceZones(adminGeofences);
  const branch = (zone, nested) => {
    const children = childrenByParent.get(String(zone.id)) || [];
    const childHtml = children.length
      ? `<div class="fence-children">${children.map(child => branch(child, true)).join('')}</div>`
      : '';
    return `<div class="fence-group">${geofenceFenceItem(zone, nested)}${childHtml}</div>`;
  };
  list.innerHTML = roots.map(zone => branch(zone, false)).join('');
}

function fillGeofenceAssignSelects() {}

function ensureAdminGeofenceMap() {
  if (!isLiveMode() || typeof L === 'undefined') return;
  const el = document.getElementById('admin-leaflet-map');
  if (!el || adminGeofenceMap) return;

  adminGeofenceMap = L.map(el).setView([TARLAC_CENTER.lat, TARLAC_CENTER.lng], 13);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors',
  }).addTo(adminGeofenceMap);
  adminGeofenceMap.on('click', (event) => {
    const lat = event.latlng.lat;
    const lng = event.latlng.lng;
    if (isPharmacyScopedUser()) {
      const latInput = document.getElementById('staff-pharmacy-lat');
      const lngInput = document.getElementById('staff-pharmacy-lng');
      if (latInput) latInput.value = lat.toFixed(6);
      if (lngInput) lngInput.value = lng.toFixed(6);
      showStaffLocationPreview(lat, lng);
      showGeofencePinReadout(lat, lng, 'Map point');
      return;
    }
    if (!isAdminUser()) return;
    if (creatingStartingPoint()) {
      const latInput = document.getElementById('admin-geofence-lat');
      const lngInput = document.getElementById('admin-geofence-lng');
      if (latInput) latInput.value = lat.toFixed(6);
      if (lngInput) lngInput.value = lng.toFixed(6);
      showStartingPointDraft(lat, lng);
      showGeofencePinReadout(lat, lng, 'Starting point');
      syncNestingLock();
      return;
    }
    if (reassignQueue.length) {
      placeReassignedPharmacy(lat, lng);
      return;
    }
    if (pendingNestPharmacy && pharmacyNestParent()) {
      placeCheckedPharmacy(lat, lng);
      return;
    }
    if (pendingPharmacyName) {
      const parent = pharmacyNestParent();
      const holder = parent || startingPointContaining(lat, lng);
      const readout = document.getElementById('admin-geofence-pin-readout');
      if (!holder) {
        if (readout) readout.textContent = 'Click inside a starting point to place this pharmacy.';
        return;
      }
      if (parent && !pointInsideGeofence(lat, lng, parent)) {
        if (readout) readout.textContent = `Click inside ${parent.name}.`;
        return;
      }
      placePendingPharmacy(lat, lng);
      return;
    }
    if (startingPointFormIsOpen()) {
      const latInput = document.getElementById('admin-geofence-lat');
      const lngInput = document.getElementById('admin-geofence-lng');
      if (latInput) latInput.value = lat.toFixed(6);
      if (lngInput) lngInput.value = lng.toFixed(6);
      showStartingPointDraft(lat, lng);
      showGeofencePinReadout(lat, lng, 'Starting point');
      return;
    }
    if (geofenceFormIsOpen()) {
      const holder = startingPointContaining(lat, lng);
      const readout = document.getElementById('admin-geofence-pin-readout');
      if (!holder) {
        if (readout) readout.textContent = 'Click inside a starting point to move this pin.';
        return;
      }
      const latInput = document.getElementById('admin-geofence-lat');
      const lngInput = document.getElementById('admin-geofence-lng');
      if (latInput) latInput.value = lat.toFixed(6);
      if (lngInput) lngInput.value = lng.toFixed(6);
      showGeofencePinReadout(lat, lng, `Inside ${holder.name}`);
      return;
    }
    showGeofencePinReadout(lat, lng, 'Map point');
  });
}

function clearAdminGeofenceMapLayers() {
  adminGeofenceMapLayers.forEach(layer => adminGeofenceMap?.removeLayer(layer));
  adminGeofenceMapLayers = [];
}

function renderAdminGeofenceMapLayers() {
  if (!adminGeofenceMap) return;
  clearAdminGeofenceMapLayers();

  const bounds = [];
  const drawnPharmacyIds = new Set();

  adminGeofences.forEach((g, i) => {
    if (g.center_latitude == null || g.center_longitude == null) return;
    const color = geofenceZoneColor(i);
    const isSelected = String(g.id) === String(selectedAdminGeofenceId);
    const circle = L.circle([g.center_latitude, g.center_longitude], {
      radius: g.radius_meters,
      color,
      weight: isSelected ? 2.5 : 1.5,
      dashArray: g.is_active ? '6,5' : '2,6',
      fillColor: color,
      fillOpacity: g.is_active ? 0.08 : 0.03,
      opacity: g.is_active ? 1 : 0.45,
    }).addTo(adminGeofenceMap).bindTooltip(g.name);
    adminGeofenceMapLayers.push(circle);
    bounds.push([g.center_latitude, g.center_longitude]);

    (g.pharmacies || []).forEach(p => {
      if (isPharmacyScopedUser() && Number(p.id) !== Number(currentUser.pharmacy_id)) return;
      if (p.latitude == null || p.longitude == null || drawnPharmacyIds.has(p.id)) return;
      const hasNestedZone = adminGeofences.some(zone => Number(zone.radius_meters) === 50
        && (zone.pharmacies || []).some(item => String(item.id) === String(p.id)));
      if (!hasNestedZone) return;
      drawnPharmacyIds.add(p.id);
      const marker = L.marker([p.latitude, p.longitude], {
        icon: pharmacyPinIcon(false),
        zIndexOffset: 400,
      }).addTo(adminGeofenceMap);
      marker.bindPopup(
        `<strong>${escapeHtml(p.name)}</strong><br>Latitude: ${Number(p.latitude).toFixed(6)}<br>Longitude: ${Number(p.longitude).toFixed(6)}`,
      );
      marker.on('click', (event) => {
        L.DomEvent.stopPropagation(event);
        showGeofencePinReadout(p.latitude, p.longitude, p.name);
      });
      adminGeofenceMapLayers.push(marker);
      bounds.push([p.latitude, p.longitude]);
    });
  });

  if (bounds.length > 1) {
    adminGeofenceMap.fitBounds(bounds, { padding: [36, 36], maxZoom: 14 });
  } else if (bounds.length === 1) {
    adminGeofenceMap.setView(bounds[0], 13);
  } else {
    adminGeofenceMap.setView([TARLAC_CENTER.lat, TARLAC_CENTER.lng], 13);
  }

  if (isPharmacyScopedUser()) {
    const hospital = [...adminGeofences]
      .filter(isStartingPoint)
      .sort((a, b) => Number(a.radius_meters) - Number(b.radius_meters))[0];
    if (hospital?.center_latitude != null && hospital?.center_longitude != null) {
      const hospitalBounds = L.circle(
        [hospital.center_latitude, hospital.center_longitude],
        { radius: Number(hospital.radius_meters) || 1000 },
      ).getBounds();
      adminGeofenceMap.fitBounds(hospitalBounds, { padding: [24, 24] });
    } else {
      const pharmacy = staffAssignedPharmacy();
      if (pharmacy?.latitude != null && pharmacy?.longitude != null) {
        adminGeofenceMap.setView([pharmacy.latitude, pharmacy.longitude], 17);
      }
    }
  } else if (selectedAdminGeofenceId) {
    const g = adminGeofences.find(x => x.id === selectedAdminGeofenceId);
    if (g?.center_latitude != null && g?.center_longitude != null) {
      adminGeofenceMap.setView([g.center_latitude, g.center_longitude], 14);
    }
  }

  restoreAdminPharmacyDraftPin();
  syncStartingPointDraft();
}

function clearStartingPointDraft() {
  if (adminStartingDraft) {
    adminGeofenceMap?.removeLayer(adminStartingDraft);
    adminStartingDraft = null;
  }
}

function showStartingPointDraft(lat, lng, options = {}) {
  if (!adminGeofenceMap || lat == null || lng == null) return;
  clearStartingPointDraft();
  const radius = parseInt(document.getElementById('admin-geofence-radius')?.value, 10) || 1000;
  const color = options.color || '#1D9E75';
  adminStartingDraft = L.circle([Number(lat), Number(lng)], {
    radius,
    color,
    weight: 2,
    dashArray: '6,5',
    fillColor: color,
    fillOpacity: 0.08,
  }).addTo(adminGeofenceMap).bindTooltip(options.label || 'New starting point');
}

function syncStartingPointDraft() {
  const panel = document.getElementById('admin-geofence-form-panel');
  if (!panel || panel.classList.contains('hidden')) {
    clearStartingPointDraft();
    return;
  }
  const lat = parseFloat(document.getElementById('admin-geofence-lat')?.value);
  const lng = parseFloat(document.getElementById('admin-geofence-lng')?.value);
  if (Number.isNaN(lat) || Number.isNaN(lng)) {
    clearStartingPointDraft();
    return;
  }
  if (!startingPointFormIsOpen()) {
    clearStartingPointDraft();
    return;
  }
  showStartingPointDraft(lat, lng);
}

function showGeofencePinReadout(lat, lng, label) {
  const el = document.getElementById('admin-geofence-pin-readout');
  if (!el) return;
  el.textContent = `${label}: ${Number(lat).toFixed(6)}, ${Number(lng).toFixed(6)}`;
}

function finalizeGeofenceName(event) {
  if (event.key !== 'Enter') return;
  event.preventDefault();
  const nameInput = document.getElementById('admin-geofence-name');
  const name = nameInput?.value.trim() || '';
  if (!name) {
    alert('Enter a zone name.');
    return;
  }
  if (nameInput) nameInput.value = name;
  const readout = document.getElementById('admin-geofence-pin-readout');
  if (readout && startingPointFormIsOpen()) {
    readout.textContent = `Click the map to place ${name}.`;
  }
  document.getElementById('admin-leaflet-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
  setTimeout(() => adminGeofenceMap?.invalidateSize(), 350);
}

function clearPendingPharmacy() {
  pendingPharmacyName = null;
  document.getElementById('geo-add-pharmacy-btn')?.classList.remove('btn-primary');
  if (adminPharmacyDraft) {
    adminGeofenceMap?.removeLayer(adminPharmacyDraft);
    adminPharmacyDraft = null;
  }
}

function promptAddPharmacy() {
  if (!isAdminUser()) {
    alert('Only admins can add pharmacies.');
    return;
  }
  if (creatingStartingPoint() && !startingPointCenterIsSet()) {
    promptPlaceStartingPointFirst();
    return;
  }
  if (creatingStartingPoint()) {
    alert('Assign this starting point on the map and save it before adding a pharmacy.');
    return;
  }
  const entered = window.prompt('Pharmacy name');
  if (entered == null) return;
  const name = entered.trim();
  if (!name) {
    alert('Enter a pharmacy name.');
    return;
  }
  pendingPharmacyName = name;
  document.getElementById('geo-add-pharmacy-btn')?.classList.add('btn-primary');
  const readout = document.getElementById('admin-geofence-pin-readout');
  const parent = pharmacyNestParent();
  if (readout) {
    readout.textContent = parent
      ? `Click inside ${parent.name} to place ${name}.`
      : `Click the map to place ${name}.`;
  }
  document.getElementById('admin-leaflet-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function showAdminPharmacyDraftPin(lat, lng) {
  if (!adminGeofenceMap || lat == null || lng == null) return;
  if (adminPharmacyDraft) {
    adminGeofenceMap.removeLayer(adminPharmacyDraft);
    adminPharmacyDraft = null;
  }
  adminPharmacyDraft = L.marker([Number(lat), Number(lng)], {
    icon: pharmacyPinIcon(true),
    zIndexOffset: 800,
  }).addTo(adminGeofenceMap).bindTooltip('New pharmacy pin');
}

function restoreAdminPharmacyDraftPin() {
  if (!pendingPharmacyName && adminPharmacyDraft) {
    adminGeofenceMap?.removeLayer(adminPharmacyDraft);
    adminPharmacyDraft = null;
  }
}

function updateAdminGeofenceMapOverlay() {
  const el = document.getElementById('admin-geofence-map-overlay');
  if (!el) return;
  const activeCount = adminGeofences.filter(g => g.is_active).length;
  el.innerHTML = `<i class="ti ti-map-pins"></i> ${activeCount} active ${activeCount === 1 ? 'geofence' : 'geofences'}`;
}

function selectAdminGeofence(id) {
  selectedAdminGeofenceId = id;
  document.querySelectorAll('.fence-item').forEach(el => {
    el.classList.toggle('active', String(el.dataset.geofenceId) === String(id));
  });
  renderAdminGeofenceMapLayers();
}

function renderGeofencePharmacyChecks(selectedIds = []) {
  const wrap = document.getElementById('admin-geofence-pharmacy-checks');
  if (!wrap) return;

  const selected = new Set(selectedIds.map(String));
  const activePharmacies = adminPharmacies.filter(p => p.is_active || selected.has(String(p.id)));

  if (!activePharmacies.length) {
    wrap.innerHTML = '<div class="text-muted text-sm">No pharmacies available.</div>';
    return;
  }

  wrap.innerHTML = activePharmacies.map(p => `
    <div class="geofence-pharmacy-option">
      <label>
        <input type="checkbox" name="admin-geofence-pharmacy" value="${p.id}" ${selected.has(String(p.id)) ? 'checked' : ''} onchange="toggleAssignedPharmacy(this)" />
        <span class="geofence-pharmacy-name">${escapeHtml(p.name)}</span>
      </label>
      <button class="btn btn-xs btn-danger geofence-pharmacy-remove" type="button" title="Remove" onclick="removeAssignedPharmacy(${p.id})">
        <i class="ti ti-x"></i>
      </button>
    </div>`).join('');
  ensureNestingGuard();
  syncNestingLock();
}

async function removeAssignedPharmacy(id) {
  if (!isAdminUser()) {
    alert('Only admins can remove pharmacies.');
    return;
  }

  const pharmacy = adminPharmacies.find(p => String(p.id) === String(id));
  const name = pharmacy?.name || 'this pharmacy';
  if (!confirm(`Remove ${name} from assigned pharmacies? This clears it from the list and the map.`)) return;

  const selected = getSelectedGeofencePharmacyIds().filter(selectedId => String(selectedId) !== String(id));

  try {
    await apiFetch(`/admin/pharmacies/${id}`, { method: 'DELETE' });
    await loadAdminGeofences();
    renderGeofencePharmacyChecks(selected);
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
  } catch (err) {
    alert(err.message);
  }
}

function creatingStartingPoint() {
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  return startingPointFormIsOpen() && !editingId;
}

function startingPointCenterIsSet() {
  const lat = parseFloat(document.getElementById('admin-geofence-lat')?.value);
  const lng = parseFloat(document.getElementById('admin-geofence-lng')?.value);
  return !Number.isNaN(lat) && !Number.isNaN(lng);
}

function promptPlaceStartingPointFirst() {
  const name = document.getElementById('admin-geofence-name')?.value.trim();
  const readout = document.getElementById('admin-geofence-pin-readout');
  if (readout) {
    readout.textContent = name
      ? `Click the map to place ${name} before nesting a geofence.`
      : 'Click the map to place this starting point before nesting a geofence.';
  }
  document.getElementById('admin-leaflet-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function syncNestingLock() {
  const locked = creatingStartingPoint() && !startingPointCenterIsSet();
  document.getElementById('admin-geofence-pharmacy-checks')?.classList.toggle('is-awaiting-placement', locked);
}

function ensureNestingGuard() {
  const wrap = document.getElementById('admin-geofence-pharmacy-checks');
  if (!wrap || wrap.dataset.guarded === '1') return;
  wrap.dataset.guarded = '1';
  wrap.addEventListener('mousedown', (event) => {
    if (!creatingStartingPoint() || startingPointCenterIsSet()) return;
    if (event.target.closest('.geofence-pharmacy-remove')) return;
    event.preventDefault();
    event.stopPropagation();
    promptPlaceStartingPointFirst();
  }, true);
}

function pharmacyNestParent() {
  const explicit = insideStartingPoint();
  if (explicit) return explicit;
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  const editing = editingId ? adminGeofences.find(zone => String(zone.id) === String(editingId)) : null;
  return editing && isStartingPoint(editing) ? editing : null;
}

function insideStartingPoint() {
  if (!insideStartingPointId) return null;
  return adminGeofences.find(zone => String(zone.id) === String(insideStartingPointId)) || null;
}

function startingPointFormIsOpen() {
  const panel = document.getElementById('admin-geofence-form-panel');
  if (!panel || panel.classList.contains('hidden')) return false;
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  const editing = editingId ? adminGeofences.find(zone => String(zone.id) === String(editingId)) : null;
  return !(editing && Number(editing.radius_meters) === 50);
}

function geofenceFormIsOpen() {
  const panel = document.getElementById('admin-geofence-form-panel');
  return !!panel && !panel.classList.contains('hidden');
}

function startingPointContaining(lat, lng, options = {}) {
  return adminGeofences
    .filter(zone => (options.includeInactive || zone.is_active) && isStartingPoint(zone) && pointInsideGeofence(lat, lng, zone))
    .sort((a, b) => Number(a.radius_meters) - Number(b.radius_meters))[0] || null;
}

function applyStartingRadiusLimits() {
  const radiusWrap = document.getElementById('admin-geofence-radius-wrap');
  const radiusInput = document.getElementById('admin-geofence-radius');
  const label = document.querySelector('label[for="admin-geofence-radius"]');
  const hint = document.getElementById('admin-geofence-radius-hint');
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  const editing = editingId ? adminGeofences.find(zone => String(zone.id) === String(editingId)) : null;
  const pinZone = editing && Number(editing.radius_meters) === 50;
  if (radiusWrap) radiusWrap.classList.toggle('hidden', !!pinZone);
  if (!radiusInput || pinZone) {
    syncGeofenceFormSections();
    return;
  }

  radiusInput.disabled = false;
  radiusInput.min = '500';
  radiusInput.max = '1000';
  if (label) label.textContent = 'Radius (meters, 500–1000)';
  const value = parseInt(radiusInput.value, 10);
  if (Number.isNaN(value) || value < 500 || value > 1000) radiusInput.value = '1000';
  if (hint) hint.textContent = 'This circle is the starting point. Choose 500–1,000 meters. Add Pharmacy places a pin inside it.';
  syncGeofenceFormSections();
}

function setStartingPointField() {
  applyStartingRadiusLimits();
}

function openGeofenceInside(parentId) {
  const parent = adminGeofences.find(zone => String(zone.id) === String(parentId));
  if (!parent || !isStartingPoint(parent) || !parent.is_active) {
    alert('Choose an active starting point first.');
    return;
  }

  closeGeofenceForm();
  insideStartingPointId = parent.id;
  const readout = document.getElementById('admin-geofence-pin-readout');
  if (readout) readout.textContent = `Name the pharmacy, then click inside ${parent.name}.`;
  if (adminGeofenceMap && parent.center_latitude != null && parent.center_longitude != null) {
    const focus = L.circle(
      [Number(parent.center_latitude), Number(parent.center_longitude)],
      { radius: Number(parent.radius_meters) || 1000 },
    ).addTo(adminGeofenceMap);
    adminGeofenceMap.fitBounds(focus.getBounds(), { padding: [24, 24] });
    adminGeofenceMap.removeLayer(focus);
  }
  promptAddPharmacy();
  if (!pendingPharmacyName) insideStartingPointId = null;
}

function syncGeofenceFormSections() {
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  const editing = editingId ? adminGeofences.find(zone => String(zone.id) === String(editingId)) : null;
  const pinZone = editing && Number(editing.radius_meters) === 50;
  document.getElementById('admin-geofence-pharmacies-wrap')?.classList.toggle('hidden', !!pinZone);
}

function openGeofenceForm(id, asStartingPoint = false) {
  if (!isAdminUser()) {
    alert('Only admins can create or edit geofences.');
    return;
  }

  insideStartingPointId = null;
  pendingNestPharmacy = null;
  const panel = document.getElementById('admin-geofence-form-panel');
  const title = document.getElementById('admin-geofence-form-title');
  const activeWrap = document.getElementById('admin-geofence-active-wrap');
  const pharmaciesWrap = document.getElementById('admin-geofence-pharmacies-wrap');
  const editId = document.getElementById('admin-geofence-edit-id');

  if (id != null) {
    const g = adminGeofences.find(x => x.id === id);
    if (!g) return;
    editId.value = String(g.id);
    document.getElementById('admin-geofence-name').value = g.name || '';
    document.getElementById('admin-geofence-description').value = g.description || '';
    document.getElementById('admin-geofence-lat').value = g.center_latitude ?? '';
    document.getElementById('admin-geofence-lng').value = g.center_longitude ?? '';
    const radiusInput = document.getElementById('admin-geofence-radius');
    if (radiusInput) radiusInput.value = String(g.radius_meters ?? 1000);
    document.getElementById('admin-geofence-active').checked = !!g.is_active;
    setStartingPointField(g, false);
    renderGeofencePharmacyChecks((g.pharmacies || []).map(p => p.id));
    if (title) {
      title.innerHTML = isStartingPoint(g)
        ? '<i class="ti ti-edit"></i> Edit starting point'
        : '<i class="ti ti-edit"></i> Edit geofence';
    }
    activeWrap?.classList.remove('hidden');
    pharmaciesWrap?.classList.remove('hidden');
  } else {
    editId.value = '';
    document.getElementById('admin-geofence-name').value = '';
    document.getElementById('admin-geofence-description').value = '';
    document.getElementById('admin-geofence-lat').value = '';
    document.getElementById('admin-geofence-lng').value = '';
    const radiusInput = document.getElementById('admin-geofence-radius');
    if (radiusInput) radiusInput.value = '1000';
    document.getElementById('admin-geofence-active').checked = true;
    setStartingPointField();
    renderGeofencePharmacyChecks([]);
    if (title) title.innerHTML = '<i class="ti ti-plus"></i> Starting point';
    const readout = document.getElementById('admin-geofence-pin-readout');
    if (readout) readout.textContent = 'Click the map to place this starting point. Radius is 500–1,000 meters.';
    activeWrap?.classList.add('hidden');
    pharmaciesWrap?.classList.remove('hidden');
  }

  panel?.classList.remove('hidden');
  syncGeofenceFormSections();
  syncStartingPointDraft();
  syncNestingLock();
  panel?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
}

function closeGeofenceForm() {
  document.getElementById('admin-geofence-form-panel')?.classList.add('hidden');
  const editId = document.getElementById('admin-geofence-edit-id');
  if (editId) editId.value = '';
  insideStartingPointId = null;
  pendingNestPharmacy = null;
  clearPendingPharmacy();
  clearStartingPointDraft();
  syncNestingLock();
}

async function placePendingPharmacy(latitude, longitude) {
  if (!isAdminUser() || !pendingPharmacyName || placePendingPharmacy.busy) return;
  const parent = pharmacyNestParent();
  if (!parent || !pointInsideGeofence(latitude, longitude, parent)) {
    alert(parent
      ? `Click inside ${parent.name}.`
      : 'Assign this starting point on the map and save it before adding a pharmacy.');
    return;
  }
  placePendingPharmacy.busy = true;
  const name = pendingPharmacyName;
  showAdminPharmacyDraftPin(latitude, longitude);
  showGeofencePinReadout(latitude, longitude, name);

  const selected = new Set(getSelectedGeofencePharmacyIds());

  try {
    const pharmacy = await apiFetch('/admin/pharmacies', {
      method: 'POST',
      body: JSON.stringify({
        name,
        latitude,
        longitude,
        inside_tph: false,
      }),
    });

    await apiFetch('/admin/geofences', {
      method: 'POST',
      body: JSON.stringify({
        name: `${name} — 50 m`,
        description: `50 meter zone around ${name}`,
        center_latitude: latitude,
        center_longitude: longitude,
        radius_meters: 50,
        pharmacy_ids: [pharmacy.id],
        parent_id: parent.id,
      }),
    });

    await apiFetch(`/admin/geofences/${parent.id}/pharmacies`, {
      method: 'POST',
      body: JSON.stringify({ pharmacy_id: pharmacy.id }),
    });

    selected.add(pharmacy.id);
    clearPendingPharmacy();
    if (!geofenceFormIsOpen()) insideStartingPointId = null;

    await loadAdminGeofences();
    renderGeofencePharmacyChecks([...selected]);
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
    showGeofencePinReadout(latitude, longitude, `${name} added`);
  } catch (err) {
    pendingPharmacyName = name;
    alert(err.message);
  } finally {
    placePendingPharmacy.busy = false;
  }
}

function getSelectedGeofencePharmacyIds() {
  return [...document.querySelectorAll('input[name="admin-geofence-pharmacy"]:checked')]
    .map(el => parseInt(el.value, 10))
    .filter(id => !Number.isNaN(id));
}

function pharmacyPinnedToOtherStartingPoint(pharmacyId) {
  const editingId = document.getElementById('admin-geofence-edit-id')?.value || '';
  const id = String(pharmacyId);
  const onAnotherStart = adminGeofences.some(zone => isStartingPoint(zone)
    && String(zone.id) !== String(editingId)
    && (zone.pharmacies || []).some(pharmacy => String(pharmacy.id) === id));
  if (onAnotherStart) return true;

  const pinZone = adminGeofences.find(zone => Number(zone.radius_meters) === 50
    && zone.parent_id
    && String(zone.parent_id) !== String(editingId)
    && (zone.pharmacies || []).some(pharmacy => String(pharmacy.id) === id));
  if (pinZone) {
    const parent = adminGeofences.find(zone => String(zone.id) === String(pinZone.parent_id));
    if (parent && isStartingPoint(parent)) return true;
  }

  return false;
}

function pharmacyHasNestedPinHere(pharmacyId) {
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  if (!editingId) return false;
  return adminGeofences.some(zone => Number(zone.radius_meters) === 50
    && String(zone.parent_id) === String(editingId)
    && (zone.pharmacies || []).some(pharmacy => String(pharmacy.id) === String(pharmacyId)));
}

function checkedPharmacyNeedingPin() {
  const editingId = document.getElementById('admin-geofence-edit-id')?.value;
  if (!editingId) return null;
  const editing = adminGeofences.find(zone => String(zone.id) === String(editingId));
  if (!editing || !isStartingPoint(editing)) return null;
  const pharmacyId = getSelectedGeofencePharmacyIds().find(id => !pharmacyHasNestedPinHere(id));
  if (pharmacyId == null) return null;
  const pharmacy = adminPharmacies.find(item => String(item.id) === String(pharmacyId));
  return { id: pharmacyId, name: pharmacy?.name || 'this pharmacy' };
}

function promptNestPlacement() {
  if (!pendingNestPharmacy) return;
  const parent = pharmacyNestParent();
  const readout = document.getElementById('admin-geofence-pin-readout');
  if (readout) {
    readout.textContent = parent
      ? `Click inside ${parent.name} to place ${pendingNestPharmacy.name}.`
      : `Click inside this starting point to place ${pendingNestPharmacy.name}.`;
  }
  document.getElementById('admin-leaflet-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

function toggleAssignedPharmacy(input) {
  if (creatingStartingPoint() && !startingPointCenterIsSet()) {
    if (input) input.checked = false;
    promptPlaceStartingPointFirst();
    return;
  }
  if (!input?.checked) {
    if (pendingNestPharmacy && String(pendingNestPharmacy.id) === String(input.value)) pendingNestPharmacy = null;
    return;
  }
  if (pharmacyPinnedToOtherStartingPoint(input.value)) {
    input.checked = false;
    showStartingPointAssignmentNotice();
    return;
  }
  if (pharmacyHasNestedPinHere(input.value)) return;
  if (pendingNestPharmacy && String(pendingNestPharmacy.id) !== String(input.value)) {
    input.checked = false;
    promptNestPlacement();
    return;
  }
  const pharmacy = adminPharmacies.find(item => String(item.id) === String(input.value));
  pendingNestPharmacy = { id: input.value, name: pharmacy?.name || 'this pharmacy' };
  promptNestPlacement();
}

async function placeCheckedPharmacy(latitude, longitude) {
  const next = pendingNestPharmacy;
  const parent = pharmacyNestParent();
  if (!next || !parent || placeCheckedPharmacy.busy) return;
  const readout = document.getElementById('admin-geofence-pin-readout');
  if (!pointInsideGeofence(latitude, longitude, parent)) {
    if (readout) readout.textContent = `Click inside ${parent.name} to place ${next.name}.`;
    return;
  }

  placeCheckedPharmacy.busy = true;
  const selected = new Set(getSelectedGeofencePharmacyIds());
  selected.add(Number(next.id));
  try {
    await apiFetch(`/admin/pharmacies/${next.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ latitude, longitude }),
    });
    await apiFetch('/admin/geofences', {
      method: 'POST',
      body: JSON.stringify({
        name: `${next.name} — 50 m`,
        description: `50 meter zone around ${next.name}`,
        center_latitude: latitude,
        center_longitude: longitude,
        radius_meters: 50,
        pharmacy_ids: [Number(next.id)],
        parent_id: parent.id,
      }),
    });
    await apiFetch(`/admin/geofences/${parent.id}/pharmacies`, {
      method: 'POST',
      body: JSON.stringify({ pharmacy_id: Number(next.id) }),
    });
    pendingNestPharmacy = null;
    await loadAdminGeofences();
    renderGeofencePharmacyChecks([...selected]);
    await refreshCustomerPharmacies();
    showGeofencePinReadout(latitude, longitude, `${next.name} placed`);
  } catch (err) {
    alert(err.message);
    promptNestPlacement();
  } finally {
    placeCheckedPharmacy.busy = false;
  }
}

function showStartingPointAssignmentNotice() {
  let notice = document.getElementById('starting-point-assignment-notice');
  if (!notice) {
    notice = document.createElement('div');
    notice.id = 'starting-point-assignment-notice';
    notice.className = 'starting-point-assignment-notice';
    notice.setAttribute('role', 'status');
    notice.textContent = 'Already assigned to a starting point.';
    document.body.appendChild(notice);
  }
  notice.classList.remove('is-fading');
  void notice.offsetWidth;
  notice.classList.add('is-fading');
}

async function saveGeofence() {
  if (!isAdminUser()) {
    alert('Only admins can save geofences.');
    return;
  }

  const id = document.getElementById('admin-geofence-edit-id')?.value;
  const name = document.getElementById('admin-geofence-name')?.value.trim();
  const description = document.getElementById('admin-geofence-description')?.value.trim();
  const center_latitude = parseFloat(document.getElementById('admin-geofence-lat')?.value);
  const center_longitude = parseFloat(document.getElementById('admin-geofence-lng')?.value);
  const radius_meters = parseInt(document.getElementById('admin-geofence-radius')?.value, 10);
  const editing = id ? adminGeofences.find(g => String(g.id) === String(id)) : null;
  const pinZone = editing && Number(editing.radius_meters) === 50;

  if (!name) {
    alert('Enter a zone name.');
    return;
  }
  if (Number.isNaN(center_latitude) || Number.isNaN(center_longitude)) {
    alert('Click the map to place the center.');
    return;
  }
  if (!pinZone && (Number.isNaN(radius_meters) || radius_meters < 500 || radius_meters > 1000)) {
    alert('A starting point radius must be between 500 and 1000 meters.');
    return;
  }
  if (pinZone && !startingPointContaining(center_latitude, center_longitude)) {
    alert('Click inside a starting point to move this pin.');
    return;
  }
  const needsPin = checkedPharmacyNeedingPin();
  if (needsPin) {
    pendingNestPharmacy = needsPin;
    promptNestPlacement();
    return;
  }

  const body = {
    name,
    description: description || null,
    center_latitude,
    center_longitude,
    radius_meters: pinZone ? 50 : radius_meters,
    is_starting_point: !pinZone,
  };
  if (!pinZone) {
    body.pharmacy_ids = getSelectedGeofencePharmacyIds()
      .filter(pharmacyId => !pharmacyPinnedToOtherStartingPoint(pharmacyId));
  }

  if (id) {
    body.is_active = document.getElementById('admin-geofence-active')?.checked ?? true;
  }

  try {
    if (id) {
      await apiFetch(`/admin/geofences/${id}`, { method: 'PATCH', body: JSON.stringify(body) });
    } else {
      await apiFetch('/admin/geofences', { method: 'POST', body: JSON.stringify(body) });
    }
    closeGeofenceForm();
    await loadAdminGeofences();
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
  } catch (err) {
    alert(err.message);
  }
}

async function deleteGeofence(id) {
  if (!isAdminUser()) {
    alert('Only admins can delete geofences.');
    return;
  }
  const zone = adminGeofences.find(g => String(g.id) === String(id));
  const name = zone?.name || 'this geofence';
  const nested = adminGeofences.filter(child => String(child.parent_id) === String(id));
  const confirmMessage = nested.length
    ? `Delete ${name}? This also removes ${nested.length} nested ${nested.length === 1 ? 'geofence' : 'geofences'} from the map. Those pharmacies can be assigned to another starting point.`
    : `Delete ${name}? This removes the zone and its pin from the map.`;
  if (!confirm(confirmMessage)) return;
  try {
    const result = await apiFetch(`/admin/geofences/${id}`, { method: 'DELETE' });
    closeGeofenceForm();
    await loadAdminGeofences();
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
    const released = Array.isArray(result?.released) ? result.released : [];
    if (released.length) {
      reassignQueue = [released[0]];
      document.getElementById('admin-leaflet-map')?.scrollIntoView({ behavior: 'smooth', block: 'center' });
      showReassignPrompt();
    }
  } catch (err) {
    alert(err.message);
  }
}

function showReassignPrompt() {
  const readout = document.getElementById('admin-geofence-pin-readout');
  const next = reassignQueue[0];
  if (!readout) return;
  if (!next) {
    readout.textContent = 'Click a pin to see its latitude and longitude.';
    return;
  }
  readout.textContent = `Click inside a starting point to re-assign ${next.name}.`;
}

async function placeReassignedPharmacy(latitude, longitude) {
  const next = reassignQueue[0];
  if (!next || placeReassignedPharmacy.busy) return;
  const parent = pharmacyNestParent() || startingPointContaining(latitude, longitude, { includeInactive: true });
  const readout = document.getElementById('admin-geofence-pin-readout');
  if (!parent || !pointInsideGeofence(latitude, longitude, parent)) {
    if (readout) {
      readout.textContent = parent
        ? `Click inside ${parent.name} to re-assign ${next.name}.`
        : `Click inside a starting point to re-assign ${next.name}.`;
    }
    return;
  }

  placeReassignedPharmacy.busy = true;
  try {
    await apiFetch(`/admin/pharmacies/${next.id}`, {
      method: 'PATCH',
      body: JSON.stringify({ latitude, longitude }),
    });
    await apiFetch('/admin/geofences', {
      method: 'POST',
      body: JSON.stringify({
        name: `${next.name} — 50 m`,
        description: `50 meter zone around ${next.name}`,
        center_latitude: latitude,
        center_longitude: longitude,
        radius_meters: 50,
        pharmacy_ids: [next.id],
        parent_id: parent.id,
      }),
    });
    await apiFetch(`/admin/geofences/${parent.id}/pharmacies`, {
      method: 'POST',
      body: JSON.stringify({ pharmacy_id: next.id }),
    });
    reassignQueue.shift();
    reassignQueue = [];
    await loadAdminGeofences();
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
    showGeofencePinReadout(latitude, longitude, `${next.name} re-assigned`);
  } catch (err) {
    alert(err.message);
    showReassignPrompt();
  } finally {
    placeReassignedPharmacy.busy = false;
  }
}

async function assignPharmacyToGeofence() {
  if (!isAdminUser()) {
    alert('Only admins can assign pharmacies to geofences.');
    return;
  }

  const pharmacyId = document.getElementById('admin-geofence-assign-pharmacy')?.value;
  const geofenceId = document.getElementById('admin-geofence-assign-zone')?.value;

  if (!pharmacyId || !geofenceId) {
    alert('Select both a pharmacy and a geofence zone.');
    return;
  }

  try {
    await apiFetch(`/admin/geofences/${geofenceId}/pharmacies`, {
      method: 'POST',
      body: JSON.stringify({ pharmacy_id: parseInt(pharmacyId, 10) }),
    });
    await loadAdminGeofences();
    await refreshCustomerPharmacies();
    await loadAdminDashboard();
    alert('Pharmacy assigned to geofence.');
  } catch (err) {
    alert(err.message);
  }
}

function getPosPharmacyId() {
  if (isPharmacyScopedUser()) return currentUser.pharmacy_id;
  return posPharmacyId;
}

async function loadAdminPos() {
  applyGeofenceAccessControl();
  if (!isLiveMode() || !isStaffOrAdmin()) return;

  const toolbar = document.getElementById('pos-pharmacy-toolbar');
  const select = document.getElementById('pos-pharmacy-select');

  try {
    if (isAdminUser()) {
      toolbar?.classList.remove('hidden');
      if (!adminPharmacies.length) {
        adminPharmacies = await apiFetch('/admin/pharmacies');
      }
      if (select) {
        select.innerHTML = adminPharmacies.map(p =>
          `<option value="${p.id}">${escapeHtml(p.name)}</option>`
        ).join('');
        if (!posPharmacyId && adminPharmacies[0]) posPharmacyId = adminPharmacies[0].id;
        if (posPharmacyId) select.value = String(posPharmacyId);
      }
    } else {
      toolbar?.classList.add('hidden');
      posPharmacyId = currentUser?.pharmacy_id ?? null;
    }

    const pharmacyId = getPosPharmacyId();
    if (!pharmacyId) {
      renderPosProducts([]);
      renderPosRecentSales([]);
      return;
    }

    const params = new URLSearchParams();
    if (isAdminUser() && pharmacyId) params.set('pharmacy_id', String(pharmacyId));
    params.set('limit', '10');
    const query = params.toString() ? `?${params.toString()}` : '';

    const productQuery = isAdminUser() && pharmacyId ? `?pharmacy_id=${pharmacyId}` : '';
    const [products, transactions] = await Promise.all([
      apiFetch(`/admin/pos/products${productQuery}`),
      apiFetch(`/admin/transactions${query}`),
    ]);

    posProducts = products;
    renderPosProducts(products);
    renderPosRecentSales(transactions);
  } catch (err) {
    const grid = document.getElementById('pos-product-grid');
    if (grid) grid.innerHTML = `<div class="text-muted text-sm" style="padding:12px;">Could not load POS: ${escapeHtml(err.message)}</div>`;
  }
}

function onPosPharmacyChange() {
  const select = document.getElementById('pos-pharmacy-select');
  posPharmacyId = select?.value ? parseInt(select.value, 10) : null;
  posClearCart();
  loadAdminPos();
}

function renderPosProducts(products) {
  const grid = document.getElementById('pos-product-grid');
  if (!grid) return;

  if (!products.length) {
    grid.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No products stocked at this pharmacy.</div>';
    return;
  }

  grid.innerHTML = products.map(p => {
    const outClass = p.stock_quantity <= 0 ? ' out' : '';
    const stockLabel = p.stock_quantity <= 0
      ? '<div class="pos-prod-stock empty">Out of stock</div>'
      : `<div class="pos-prod-stock">${p.stock_quantity} pcs in stock</div>`;
    return `
      <div class="pos-product${outClass}" onclick='posAddToCart(${p.medicine_id}, ${JSON.stringify(p.name)}, ${p.price}, ${p.stock_quantity})'>
        <div class="pos-prod-name">${escapeHtml(p.name)}</div>
        <div class="pos-prod-price">₱${Number(p.price).toFixed(2)}</div>
        ${stockLabel}
      </div>`;
  }).join('');
}

function renderPosRecentSales(transactions) {
  const el = document.getElementById('pos-recent-sales');
  if (!el) return;

  if (!transactions.length) {
    el.innerHTML = '<div class="text-muted text-sm" style="padding:12px;">No sales recorded yet.</div>';
    return;
  }

  el.innerHTML = transactions.map(tx => {
    const items = (tx.items || []).map(i => `${i.medicine?.name || 'Item'} x${i.quantity}`).join(', ');
    const when = tx.created_at ? new Date(tx.created_at).toLocaleString() : '—';
    return `
      <div class="activity-item">
        <div class="activity-icon" style="background:#DCFCE7;">
          <i class="ti ti-receipt" style="color:#166534;"></i>
        </div>
        <div class="activity-text">
          <strong>₱${Number(tx.total_amount).toFixed(2)}</strong> · ${escapeHtml(items || 'Sale')}
          <div class="text-hint text-xs">${escapeHtml(tx.pharmacy?.name || 'Pharmacy')} · ${escapeHtml(tx.user?.name || 'Staff')} · ${when}</div>
        </div>
      </div>`;
  }).join('');
}

function posAddToCart(medicineId, name, price, stock) {
  if (stock === 0) return;
  const existing = posCart.find(i => i.medicine_id === medicineId);
  if (existing) {
    if (existing.qty >= stock) {
      alert(`Only ${stock} in stock.`);
      return;
    }
    existing.qty++;
  } else {
    posCart.push({ medicine_id: medicineId, name, price, stock, qty: 1 });
  }
  posRenderCart();
}

function posChangeQty(medicineId, delta) {
  const item = posCart.find(i => i.medicine_id === medicineId);
  if (!item) return;
  item.qty += delta;
  if (item.qty <= 0) posCart = posCart.filter(i => i.medicine_id !== medicineId);
  else if (item.qty > item.stock) item.qty = item.stock;
  posRenderCart();
}

function posRenderCart() {
  const listEl = document.getElementById('pos-cart-list');
  if (!listEl) return;
  if (posCart.length === 0) {
    listEl.innerHTML = '<div class="cart-empty">No items added yet</div>';
    document.getElementById('pos-total-qty').textContent = '0';
    document.getElementById('pos-subtotal').textContent = '₱0.00';
    document.getElementById('pos-total').textContent = '₱0.00';
    return;
  }
  listEl.innerHTML = posCart.map(item => `
    <div class="cart-item">
      <span class="cart-item-name">${escapeHtml(item.name)}</span>
      <div class="qty-ctrl">
        <button class="qty-btn" type="button" onclick="posChangeQty(${item.medicine_id}, -1)">−</button>
        <span class="qty-num">${item.qty}</span>
        <button class="qty-btn" type="button" onclick="posChangeQty(${item.medicine_id}, 1)">+</button>
      </div>
      <span class="cart-item-price">₱${(item.price * item.qty).toFixed(2)}</span>
    </div>
  `).join('');
  const totalQty = posCart.reduce((a, i) => a + i.qty, 0);
  const totalPrice = posCart.reduce((a, i) => a + (i.price * i.qty), 0);
  document.getElementById('pos-total-qty').textContent = totalQty;
  document.getElementById('pos-subtotal').textContent = '₱' + totalPrice.toFixed(2);
  document.getElementById('pos-total').textContent = '₱' + totalPrice.toFixed(2);
}

async function posProcessSale() {
  if (posCart.length === 0) {
    alert('Please add items to the cart first.');
    return;
  }

  if (!isLiveMode()) {
    const total = posCart.reduce((a, i) => a + (i.price * i.qty), 0);
    alert(`Sale processed (demo)!\n\nTotal: ₱${total.toFixed(2)}`);
    posClearCart();
    return;
  }

  const pharmacyId = getPosPharmacyId();
  if (!pharmacyId) {
    alert('Select a pharmacy first.');
    return;
  }

  try {
    const body = {
      items: posCart.map(i => ({ medicine_id: i.medicine_id, quantity: i.qty })),
    };
    if (isAdminUser()) body.pharmacy_id = pharmacyId;

    const tx = await apiFetch('/admin/transactions', {
      method: 'POST',
      body: JSON.stringify(body),
    });

    alert(`Sale recorded!\n\nTransaction #${tx.id}\nTotal: ₱${Number(tx.total_amount).toFixed(2)}`);
    posClearCart();
    await loadAdminPos();
    await loadAdminStock();
    await loadAdminDashboard();
    await refreshCustomerMedicines();
  } catch (err) {
    alert(err.message);
  }
}

function posClearCart() {
  posCart = [];
  posRenderCart();
}

async function loadAdminUsers() {
  applyGeofenceAccessControl();
  if (!isLiveMode() || !isAdminUser()) return;

  try {
    if (!adminPharmacies.length) {
      adminPharmacies = await apiFetch('/admin/pharmacies');
    }
    adminUsers = await apiFetch('/admin/users');
    renderAdminUsers();
  } catch (err) {
    const tbody = document.getElementById('admin-users-tbody');
    if (tbody) tbody.innerHTML = `<tr><td colspan="7" class="text-muted text-sm" style="padding:16px;">Could not load users: ${escapeHtml(err.message)}</td></tr>`;
  }
}

function renderAdminUsers() {
  const tbody = document.getElementById('admin-users-tbody');
  if (!tbody) return;

  if (!adminUsers.length) {
    tbody.innerHTML = '<tr><td colspan="7" class="text-muted text-sm" style="padding:16px;">No users found.</td></tr>';
    return;
  }

  tbody.innerHTML = adminUsers.map(u => {
    const lockedAdmin = u.role === 'admin' || String(u.email || '').toLowerCase() === 'admin@pharmalocate.test';
    const active = u.is_active !== false;
    const status = active
      ? '<span class="badge badge-green">Active</span>'
      : '<span class="badge badge-red">Deactivated</span>';
    const actions = lockedAdmin
      ? `<button class="btn btn-sm" type="button" onclick="openAdminUserForm(${u.id})">View</button>`
      : `<div style="display:flex; gap:6px; justify-content:flex-end;">
          <button class="btn btn-sm" type="button" onclick="openAdminUserForm(${u.id})">Edit</button>
          <button class="btn btn-sm ${active ? 'btn-danger' : ''}" type="button" onclick="setAdminUserActive(${u.id}, ${active ? 'false' : 'true'})">${active ? 'Deactivate' : 'Activate'}</button>
        </div>`;
    return `
    <tr>
      <td>${escapeHtml(u.name)}</td>
      <td>${escapeHtml(u.username || '—')}</td>
      <td>${escapeHtml(u.email)}</td>
      <td><span class="badge badge-gray">${escapeHtml(accountTierLabel(u))}</span></td>
      <td>${escapeHtml(u.pharmacy || '—')}</td>
      <td>${status}</td>
      <td>${actions}</td>
    </tr>`;
  }).join('');
}

function fillAdminUserPharmacySelect(selectedId) {
  const pharmSelect = document.getElementById('admin-user-pharmacy');
  if (!pharmSelect) return;
  pharmSelect.innerHTML = adminPharmacies.map(p =>
    `<option value="${p.id}">${escapeHtml(p.name)}</option>`
  ).join('');
  if (selectedId) pharmSelect.value = String(selectedId);
}

function openAdminUserCreate() {
  if (!isAdminUser()) return;
  document.getElementById('admin-user-edit-id').value = '';
  document.getElementById('admin-user-form-title').innerHTML = '<i class="ti ti-user-plus"></i> Add user';
  document.getElementById('admin-user-role-locked')?.classList.add('hidden');
  document.getElementById('admin-user-fields')?.classList.remove('hidden');
  document.getElementById('admin-user-save-btn')?.classList.remove('hidden');
  document.getElementById('admin-user-name').value = '';
  document.getElementById('admin-user-username').value = '';
  document.getElementById('admin-user-email').value = '';
  document.getElementById('admin-user-phone').value = '';
  document.getElementById('admin-user-password').value = '';
  const hint = document.getElementById('admin-user-password-hint');
  if (hint) hint.textContent = 'At least 8 characters.';
  const roleSelect = document.getElementById('admin-user-role');
  if (roleSelect) roleSelect.value = 'customer';
  fillAdminUserPharmacySelect();
  toggleAdminUserPharmacyField();
  document.getElementById('admin-user-form-panel')?.classList.remove('hidden');
  document.getElementById('admin-user-name')?.focus();
}

function openAdminUserForm(userId) {
  if (!isAdminUser()) return;
  const user = adminUsers.find(u => u.id === userId);
  if (!user) return;

  document.getElementById('admin-user-edit-id').value = user.id;
  document.getElementById('admin-user-form-title').innerHTML = '<i class="ti ti-user-edit"></i> Edit user';
  const lockedAdmin = user.role === 'admin' || String(user.email || '').toLowerCase() === 'admin@pharmalocate.test';
  document.getElementById('admin-user-role-locked')?.classList.toggle('hidden', !lockedAdmin);
  document.getElementById('admin-user-fields')?.classList.toggle('hidden', lockedAdmin);
  document.getElementById('admin-user-save-btn')?.classList.toggle('hidden', lockedAdmin);
  const label = document.getElementById('admin-user-edit-label');
  if (label) label.textContent = `${user.name} (${user.email})`;
  document.getElementById('admin-user-name').value = user.name || '';
  document.getElementById('admin-user-username').value = user.username || '';
  document.getElementById('admin-user-email').value = user.email || '';
  document.getElementById('admin-user-phone').value = user.phone || '';
  document.getElementById('admin-user-password').value = '';
  const hint = document.getElementById('admin-user-password-hint');
  if (hint) hint.textContent = 'Leave blank to keep the current password. A new password must be at least 8 characters.';
  const roleSelect = document.getElementById('admin-user-role');
  if (roleSelect) {
    const tier = ownerTierValue(user);
    roleSelect.value = tier || (user.role === 'staff' ? 'staff' : 'customer');
  }
  fillAdminUserPharmacySelect(user.pharmacy_id);
  toggleAdminUserPharmacyField();
  document.getElementById('admin-user-form-panel')?.classList.remove('hidden');
}

function closeAdminUserForm() {
  document.getElementById('admin-user-form-panel')?.classList.add('hidden');
  document.getElementById('admin-user-edit-id').value = '';
}

function toggleAdminUserPharmacyField() {
  const role = document.getElementById('admin-user-role')?.value;
  document.getElementById('admin-user-pharmacy-wrap')?.classList.toggle('hidden', role !== 'staff');
}

function adminUserFormBody() {
  const role = document.getElementById('admin-user-role')?.value;
  const body = {
    name: document.getElementById('admin-user-name')?.value.trim(),
    username: document.getElementById('admin-user-username')?.value.trim(),
    email: document.getElementById('admin-user-email')?.value.trim(),
    phone: document.getElementById('admin-user-phone')?.value.trim(),
    role,
  };
  const password = document.getElementById('admin-user-password')?.value || '';
  if (password) body.password = password;
  if (role === 'staff') body.pharmacy_id = parseInt(document.getElementById('admin-user-pharmacy')?.value, 10);
  return body;
}

async function saveAdminUser() {
  if (!isAdminUser()) return;

  const userId = document.getElementById('admin-user-edit-id')?.value;
  const creating = !userId;
  const editing = adminUsers.find(u => String(u.id) === String(userId));
  if (editing?.role === 'admin' || String(editing?.email || '').toLowerCase() === 'admin@pharmalocate.test') {
    alert('The administrator account cannot be changed.');
    return;
  }
  const role = document.getElementById('admin-user-role')?.value;
  if (!['customer', 'staff', 'sparx_owner', 'magic8_owner'].includes(role)) {
    alert('Choose Customer, Staff, SpaRx Owner, or Magic 8 Owner.');
    return;
  }
  const body = adminUserFormBody();
  if (!body.name || !body.username || !body.email) {
    alert('Name, username, and email are required.');
    return;
  }
  if (creating && !body.password) {
    alert('Enter a password of at least 8 characters.');
    return;
  }
  if (body.password && body.password.length < 8) {
    alert('Password must be at least 8 characters.');
    return;
  }

  try {
    await apiFetch(creating ? '/admin/users' : `/admin/users/${userId}`, {
      method: creating ? 'POST' : 'PATCH',
      body: JSON.stringify(body),
    });
    closeAdminUserForm();
    await loadAdminUsers();
    alert(creating ? 'User added.' : 'User updated.');
  } catch (err) {
    alert(err.message);
  }
}

async function setAdminUserActive(userId, active) {
  if (!isAdminUser()) return;
  const user = adminUsers.find(u => u.id === userId);
  if (!user) return;
  if (user.role === 'admin' || String(user.email || '').toLowerCase() === 'admin@pharmalocate.test') {
    alert('The administrator account cannot be deactivated.');
    return;
  }
  const verb = active ? 'Activate' : 'Deactivate';
  const detail = active
    ? `${user.name} will be able to sign in again.`
    : `${user.name} will not be able to sign in.`;
  if (!confirm(`${verb} ${user.name}?\n\n${detail}`)) return;

  try {
    await apiFetch(`/admin/users/${userId}/active`, {
      method: 'PATCH',
      body: JSON.stringify({ is_active: active }),
    });
    await loadAdminUsers();
  } catch (err) {
    alert(err.message);
  }
}

async function loadAdminSettings() {
  applyGeofenceAccessControl();
  if (!isLiveMode() || !isStaffOrAdmin()) return;

  if (!isAdminUser()) return;

  try {
    adminSettings = await apiFetch('/admin/settings');
    document.getElementById('setting-low-stock-threshold').value = adminSettings.low_stock_threshold ?? 10;
    document.getElementById('setting-notification-low-stock').checked = adminSettings.notification_low_stock === '1' || adminSettings.notification_low_stock === true;
    document.getElementById('setting-notification-inquiries').checked = adminSettings.notification_inquiries === '1' || adminSettings.notification_inquiries === true;
    document.getElementById('setting-backup-enabled').checked = adminSettings.backup_schedule_enabled === '1' || adminSettings.backup_schedule_enabled === true;
    document.getElementById('setting-backup-time').value = adminSettings.backup_schedule_time || '02:00';
    updateBackupScheduleStatus();
  } catch (err) {
    console.warn('Could not load settings:', err.message);
  }
}

async function saveAdminSettings() {
  if (!isAdminUser()) {
    alert('Only admins can save system settings.');
    return;
  }

  try {
    adminSettings = await apiFetch('/admin/settings', {
      method: 'PATCH',
      body: JSON.stringify({
        low_stock_threshold: parseInt(document.getElementById('setting-low-stock-threshold')?.value || '10', 10),
        notification_low_stock: document.getElementById('setting-notification-low-stock')?.checked,
        notification_inquiries: document.getElementById('setting-notification-inquiries')?.checked,
        backup_schedule_enabled: document.getElementById('setting-backup-enabled')?.checked,
        backup_schedule_time: (document.getElementById('setting-backup-time')?.value || '02:00').slice(0, 5),
      }),
    });
    updateBackupScheduleStatus();
    clearTimeout(lowStockSaveTimer);
    await refreshLowStockSurfaces();
    alert('Settings saved.');
  } catch (err) {
    alert(err.message);
  }
}

function updateBackupScheduleStatus() {
  const enabled = adminSettings.backup_schedule_enabled === '1' || adminSettings.backup_schedule_enabled === true;
  const time = adminSettings.backup_schedule_time || '02:00';
  const badge = document.getElementById('backup-schedule-badge');
  const detail = document.getElementById('backup-schedule-detail');
  if (badge) {
    badge.textContent = enabled ? 'Active' : 'Off';
    badge.className = enabled ? 'badge badge-green' : 'badge badge-gray';
  }
  if (detail) {
    detail.textContent = enabled
      ? `Every day at ${time} · Full JSON export`
      : 'Configure in System settings';
  }
}

async function saveBackupSchedule() {
  if (!isAdminUser()) {
    alert('Only admins can save the backup schedule.');
    return;
  }

  const time = (document.getElementById('setting-backup-time')?.value || '02:00').slice(0, 5);

  try {
    adminSettings = await apiFetch('/admin/settings', {
      method: 'PATCH',
      body: JSON.stringify({
        backup_schedule_enabled: document.getElementById('setting-backup-enabled')?.checked,
        backup_schedule_time: time,
      }),
    });
    updateBackupScheduleStatus();
    alert('Backup schedule saved.');
  } catch (err) {
    alert(err.message);
  }
}

function staffAssignedPharmacy() {
  const pharmacyId = currentUser?.pharmacy_id;
  if (!pharmacyId) return null;
  return adminPharmacies.find((p) => Number(p.id) === Number(pharmacyId)) || null;
}

function staffHasPharmacyZone() {
  const pharmacyId = currentUser?.pharmacy_id;
  if (!pharmacyId) return false;
  return adminGeofences.some((g) =>
    Number(g.radius_meters) === 50
    && (g.pharmacies || []).some((p) => Number(p.id) === Number(pharmacyId)),
  );
}

function fillStaffPharmacyLocation() {
  if (!isPharmacyScopedUser()) return;
  const pharmacy = staffAssignedPharmacy();
  const latInput = document.getElementById('staff-pharmacy-lat');
  const lngInput = document.getElementById('staff-pharmacy-lng');
  if (!latInput || !lngInput || !pharmacy) return;
  if (document.activeElement === latInput || document.activeElement === lngInput) return;
  if (pharmacy.latitude != null) latInput.value = pharmacy.latitude;
  if (pharmacy.longitude != null) lngInput.value = pharmacy.longitude;
}

function showStaffLocationPreview(lat, lng) {
  if (!adminGeofenceMap || typeof L === 'undefined') return;
  if (window.staffLocationPreview) adminGeofenceMap.removeLayer(window.staffLocationPreview);
  window.staffLocationPreview = L.circle([lat, lng], {
    radius: 50,
    color: '#0F6E56',
    weight: 2,
    dashArray: '4,4',
    fillColor: '#1D9E75',
    fillOpacity: 0.15,
  }).addTo(adminGeofenceMap);
  adminGeofenceMap.setView([lat, lng], 17);
}

async function saveStaffPharmacyLocation() {
  if (!isPharmacyScopedUser()) return;
  const latitude = parseFloat(document.getElementById('staff-pharmacy-lat')?.value);
  const longitude = parseFloat(document.getElementById('staff-pharmacy-lng')?.value);
  if (Number.isNaN(latitude) || Number.isNaN(longitude)) {
    alert('Click the map or enter the pharmacy latitude and longitude.');
    return;
  }
  if (!confirm('Save this pharmacy location with a 50 meter zone?')) return;

  const btn = document.getElementById('staff-geofence-add-btn');
  if (btn) btn.disabled = true;
  try {
    await apiFetch('/admin/geofences', {
      method: 'POST',
      body: JSON.stringify({
        center_latitude: latitude,
        center_longitude: longitude,
      }),
    });
    if (window.staffLocationPreview && adminGeofenceMap) {
      adminGeofenceMap.removeLayer(window.staffLocationPreview);
      window.staffLocationPreview = null;
    }
    await loadAdminGeofences();
    if (typeof reloadPublicCatalog === 'function') await reloadPublicCatalog();
    alert('Pharmacy location saved. The zone is 50 meters around that point.');
  } catch (err) {
    alert(err.message);
  } finally {
    if (btn) btn.disabled = false;
  }
}

async function loadAdminBackup() {
  applyGeofenceAccessControl();
  if (!isLiveMode() || !isStaffOrAdmin()) return;
  if (isAdminUser() && !adminSettings.low_stock_threshold) {
    try {
      adminSettings = await apiFetch('/admin/settings');
    } catch (_) { /* ignore */ }
  }
  updateBackupScheduleStatus();
}

async function downloadAdminExport() {
  const pharmacyScoped = isPharmacyScopedUser();
  if (!isAdminUser() && !pharmacyScoped) {
    alert('Sign in as staff or an admin to download an export.');
    return;
  }

  const scope = pharmacyScoped ? 'transactions' : (document.getElementById('export-scope')?.value || 'full');
  const format = pharmacyScoped ? 'csv' : (document.getElementById('export-format')?.value || 'csv');
  const url = `${API_BASE}/admin/export?scope=${encodeURIComponent(scope)}&format=${encodeURIComponent(format)}`;

  try {
    const res = await fetch(url, {
      headers: {
        Authorization: `Bearer ${apiToken}`,
        Accept: format === 'json' ? 'application/json' : 'text/csv',
      },
    });

    if (!res.ok) {
      const data = await res.json().catch(() => ({}));
      throw new Error(data.message || 'Export failed');
    }

    const blob = await res.blob();
    const disposition = res.headers.get('Content-Disposition') || '';
    const match = disposition.match(/filename="([^"]+)"/);
    const filename = match?.[1] || `pharmalocate-${scope}.${format}`;
    const link = document.createElement('a');
    link.href = URL.createObjectURL(blob);
    link.download = filename;
    link.click();
    URL.revokeObjectURL(link.href);
  } catch (err) {
    alert(err.message);
  }
}

async function refreshCustomerMedicines() {
  if (!isLiveMode()) return;
  try {
    const availability = await apiFetch('/availability');
    medicines = normalizeMedicines(Array.isArray(availability) ? availability : []);
    applyMedicineFilters();
    fillMedicineSuggestions();
  } catch (_) { /* ignore */ }
}

function formatCoverageRadius(minMeters, maxMeters) {
  const min = Number(minMeters);
  const max = Number(maxMeters);
  if (!Number.isFinite(min) || !Number.isFinite(max)) return 'No active zone';
  if (min === max) return formatRadiusKm(min);
  return `${formatRadiusKm(min)}–${formatRadiusKm(max)}`;
}

let chatbotLang = 'en';
let chatbotStarted = false;

const CHATBOT_FAQS = [
  {
    id: 'q1',
    q: { en: 'How do I refill my prescription?', fil: 'Paano ko po ire-refill ang reseta ko?' },
    a: {
      en: 'Please go to Pharmacies (Find nearest pharmacy). It starts from Tarlac Provincial Hospital and shows the closest pharmacy. Bring your old prescription. For regular maintenance medicine, you can usually refill without a new one. For strong or controlled medicine, you need a new prescription every time.',
      fil: 'Paki-punta po sa Pharmacies (pinakamalapit na botika). Nagsisimula ito sa Tarlac Provincial Hospital. Pakidalhin ang luma ninyong reseta. Para sa regular na gamot, madalas puwedeng i-refill nang walang bagong reseta. Para sa malakas o controlled na gamot, kailangan ng bagong reseta tuwing bibili.',
    },
    actions: [{ tab: 'pharmacies', en: 'Find nearest pharmacy', fil: 'Hanapin ang botika' }],
  },
  {
    id: 'q2',
    q: { en: 'Can I take this medicine with food or on an empty stomach?', fil: 'Puwede ko po bang inumin ang gamot kasama ng pagkain o sa walang laman na tiyan?' },
    a: {
      en: 'It depends on the medicine. Some need food or milk. Some work better on an empty stomach. Please look at the label or ask the pharmacist. This assistant cannot advise on a specific drug.',
      fil: 'Depende po sa gamot. May kailangan ng pagkain o gatas. May mas maganda kung walang laman ang tiyan. Pakibasa ang label o paki-tanong sa pharmacist.',
    },
  },
  {
    id: 'q3',
    q: { en: 'What’s the difference between generic and branded medicine?', fil: 'Ano po ang pagkakaiba ng generic at branded na gamot?' },
    a: {
      en: 'Generic and branded have the same main ingredient. Generic is cheaper because it has no brand name. Under RA 6675, drugstores in Tarlac must offer generics. Both must be FDA Philippines–approved. You can ask: “Do you have a generic version of this medicine?”',
      fil: 'Pareho po ang laman ng generic at branded. Mas mura ang generic dahil walang brand name. Ayon sa batas, ang botika sa Tarlac ay dapat mag-alok ng generic. Pareho aprubado ng FDA Philippines.',
    },
  },
  {
    id: 'q4',
    q: { en: 'What should I do if I miss a dose?', fil: 'Ano po ang dapat kong gawin kung nakalimutan kong uminom ng gamot?' },
    a: {
      en: 'It depends on your medicine. Check the medication guide or ask the pharmacist. If the pharmacy is closed, you may go to your barangay health center or the Tarlac City Health Office for an open clinic.',
      fil: 'Depende po sa gamot ninyo. Pakibasa ang papel na kasama o paki-tanong sa pharmacist. Kung sarado ang botika, puwede sa barangay health center o Tarlac City Health Office.',
    },
  },
  {
    id: 'q5',
    q: { en: 'How do I safely throw away unused or expired medicine?', fil: 'Paano po ako ligtas na magtatapon ng hindi nagamit o expired na gamot?' },
    a: {
      en: 'Do not flush medicine down the toilet. Do not throw it in the trash as it is. Take it out of the bottle, mix with used coffee grounds or dirt, seal in a bag, then throw it away. For large amounts, ask Tarlac City ENRO.',
      fil: 'Huwag po itapon sa toilet o basta sa basurahan. Alisin sa kahon, ihalo sa ginagamit na kape o lupa, selyuhan, tapos itapon. Kung marami, paki-tanong sa Tarlac City ENRO.',
    },
  },
  {
    id: 'q6',
    q: { en: 'Can I take two medicines together?', fil: 'Puwede ko po bang inumin ang dalawang gamot nang sabay?' },
    a: {
      en: 'Please do not use this chatbot to decide if mixing medicines is safe. Mixing can be dangerous. Under RA 10918, every drugstore must have a pharmacist. Ask them before combining medicines or supplements.',
      fil: 'Huwag po magtanong sa chatbot kung ligtas maghalo ng gamot. Mapanganib po iyon. Ayon sa batas, dapat may pharmacist sa botika. Laging paki-tanong sa kanila bago maghalo ng gamot o bitamina.',
    },
    actions: [{ tab: 'inquiries', en: 'Send an inquiry', fil: 'Magpadala ng inquiry' }],
  },
  {
    id: 'q7',
    q: { en: 'How should I store my medicines?', fil: 'Paano ko po dapat itago ang mga gamot ko?' },
    a: {
      en: 'Tarlac is hot and humid. Keep medicines in a cool, dry place away from sunlight — not in the car or near a window. Some (like insulin) need refrigeration. Check the label or ask the pharmacist.',
      fil: 'Mainit at mahalumigmig po sa Tarlac. Pakilagay sa malamig at tuyong lugar, layuan ang araw. Huwag sa kotse o malapit sa bintana. May gamot (tulad ng insulin) na kailangan ng refrigerator.',
    },
  },
  {
    id: 'q8',
    q: { en: 'Does PhilHealth or my insurance pay for this medicine?', fil: 'Binabayaran po ba ng PhilHealth o insurance ko ang gamot na ito?' },
    a: {
      en: 'PhilHealth usually does not pay for regular outpatient medicines except some case rates or Z Benefit Packages. For help with costs, go to the Malasakit Center at Tarlac Provincial Hospital (San Vicente). For a private HMO, check your plan.',
      fil: 'Karaniwan po hindi binabayaran ng PhilHealth ang ordinaryong gamot sa labas. Kung kailangan ng tulong sa pera, paki-punta sa Malasakit Center sa Tarlac Provincial Hospital (San Vicente). Para sa private HMO, paki-tingnan ang plano ninyo.',
    },
  },
  {
    id: 'q9',
    q: { en: 'Can I get my prescription delivered?', fil: 'Puwede po bang i-deliver ang reseta ko?' },
    a: {
      en: 'Many pharmacies in Tarlac City offer delivery. You can also use Lalamove or Grab for same-day delivery inside the city. PharmaLocate can help you find a nearby pharmacy.',
      fil: 'Maraming botika sa Tarlac City ang may delivery. Puwede ring Lalamove o Grab para sa same-day sa loob ng lungsod. Matutulungan kayo ng PharmaLocate maghanap ng malapit na botika.',
    },
    actions: [{ tab: 'pharmacies', en: 'Find nearest pharmacy', fil: 'Hanapin ang botika' }],
  },
  {
    id: 'q10',
    q: { en: 'How should this medicine generally be taken?', fil: 'Paano ko po dapat inumin ang gamot na ito?' },
    a: {
      en: 'Take the exact amount and time on the label or as told by a doctor or pharmacist. Do not change the dose yourself. This assistant cannot give a personal dosing plan.',
      fil: 'Paki-inom ang tamang dami at oras sa label o sinabi ng doktor o pharmacist. Huwag baguhin nang mag-isa.',
    },
  },
  {
    id: 'q11',
    q: { en: 'How do I transfer my prescription to a new pharmacy?', fil: 'Paano ko po ililipat ang reseta ko sa bagong botika?' },
    a: {
      en: 'Bring your original prescription and ID to the new pharmacy. For strong or controlled medicines, rules are stricter. You can also send an inquiry to pharmacy staff in this app.',
      fil: 'Pakidalhin ang orihinal na reseta at ID sa bagong botika. Para sa malakas o controlled na gamot, mas mahigpit ang rules. Puwede ring mag-inquiry sa app.',
    },
    actions: [{ tab: 'inquiries', en: 'Send an inquiry', fil: 'Magpadala ng inquiry' }],
  },
  {
    id: 'q12',
    q: { en: 'Do I need a prescription to buy this medicine?', fil: 'Kailangan ko po ba ng reseta para bilhin ang gamot na ito?' },
    a: {
      en: 'It depends. Some medicines need a prescription (ethical drugs). Some are over-the-counter (OTC). For prescription medicines, show a valid physical or electronic prescription from a licensed physician, dentist, or veterinarian.',
      fil: 'Depende po. May gamot na kailangan ng reseta. May OTC na libre bilhin. Para sa may reseta, ipakita ang valid na reseta mula sa lisensyadong doktor, dentista, o beterinaryo.',
    },
  },
  {
    id: 'q13',
    q: { en: 'What are common side effects of medicine?', fil: 'Ano po ang mga karaniwang side effect ng gamot?' },
    a: {
      en: 'Side effects differ by medicine (for example sleepiness or stomach pain). Check the printed insert. If you feel a strong reaction, see a doctor or hospital promptly. Adverse reactions can be reported to FDA Philippines.',
      fil: 'Iba-iba ang side effect. May antok o sakit ng tiyan; may malakas na reaksyon. Pakibasa ang papel sa kahon. Kung malakas ang reaksyon, paki-punta agad sa doktor o ospital. Puwedeng i-report sa FDA Philippines.',
    },
  },
  {
    id: 'q14',
    q: { en: 'Is this over-the-counter medicine safe for me?', fil: 'Ligtas po ba para sa akin ang over-the-counter na gamot na ito?' },
    a: {
      en: 'It depends on your health and other medicines. By law, every drugstore has a pharmacist for this question. Ask before taking anything new, especially if pregnant, breastfeeding, or managing another condition.',
      fil: 'Depende sa kalusugan ninyo at sa ibang gamot. Ayon sa batas, may pharmacist sa bawat botika. Paki-tanong muna, lalo na kung buntis, nagpapasuso, o may ibang sakit.',
    },
    actions: [{ tab: 'inquiries', en: 'Ask pharmacy staff', fil: 'Tanungin ang staff' }],
  },
  {
    id: 'q15',
    q: { en: 'How long is a prescription valid?', fil: 'Gaano po katagal ang validity ng reseta?' },
    a: {
      en: 'Regular maintenance prescriptions are often honored for a reasonable period. Strong or controlled medicines (S2) have shorter validity and stricter refill limits. Ask your pharmacist about your exact medicine.',
      fil: 'Ang regular na maintenance reseta ay puwedeng gamitin nang makatuwirang oras. Ang malakas o controlled (S2) ay may maikling validity at mahigpit na limit sa refill. Paki-tanong sa pharmacist.',
    },
  },
  {
    id: 'q16',
    q: { en: 'What do I do if I suspect an overdose or severe allergic reaction?', fil: 'Ano po ang dapat kung overdose o malakas na allergic reaction?' },
    a: {
      en: 'This is an emergency. Call 911 (works in Tarlac) or go immediately to the nearest hospital. Main options: Tarlac Provincial Hospital (San Vicente) or Central Luzon Doctors’ Hospital. Poison guidance: National Poison Management and Control Center +63 2 8524 1078.',
      fil: 'Emergency po ito. Tawagan ang 911 o pumunta agad sa ospital. Pangunahing ospital: Tarlac Provincial Hospital (San Vicente) o Central Luzon Doctors’ Hospital. Para sa lason: National Poison Management and Control Center +63 2 8524 1078.',
    },
  },
  {
    id: 'q17',
    q: { en: 'Do I get a discount as a senior citizen or PWD?', fil: 'May discount po ba ako bilang senior citizen o PWD?' },
    a: {
      en: 'Yes. Under RA 9994 and RA 10754, you get 20% discount plus VAT exemption on medicines at any Tarlac City pharmacy. Present your Senior Citizen or PWD ID at checkout.',
      fil: 'Oo po. Ayon sa RA 9994 at RA 10754, may 20% discount + walang VAT sa gamot sa anumang botika sa Tarlac City. Pakipakita ang Senior Citizen o PWD ID kapag magbabayad.',
    },
  },
  {
    id: 'q18',
    q: { en: 'Can I contact the pharmacist for more help?', fil: 'Puwede ko po bang kontakin ang pharmacist para sa karagdagang tulong?' },
    a: {
      en: 'Yes. Use Inquiries in this app for a question to pharmacy staff, or Pharmacies for directions to talk in person. Pharmacists must be on duty during open hours at licensed drugstores in Tarlac City.',
      fil: 'Oo po. Gamitin ang Inquiries sa app para sa tanong sa staff, o Pharmacies para sa direksyon. Dapat may pharmacist sa lisensyadong botika habang bukas.',
    },
    actions: [
      { tab: 'inquiries', en: 'Open Inquiries', fil: 'Buksan ang Inquiries' },
      { tab: 'pharmacies', en: 'Find nearest pharmacy', fil: 'Hanapin ang botika' },
    ],
  },
];

function chatbotCopy() {
  if (chatbotLang === 'fil') {
    return {
      subtitle: 'Mabilis na tulong sa botika, gamot, at inquiry',
      welcome: 'Pumili po ng tanong sa ibaba. Para po ito sa lahat ng user — tumulong sa botika, gamot, o inquiry sa pharmacist.',
      more: 'Iba pang tanong',
    };
  }
  return {
    subtitle: 'Quick help with pharmacies, medicines, and inquiries',
    welcome: 'Tap a question below. This help is for all users: find a community pharmacy, check medicines, or send an inquiry.',
    more: 'More questions',
  };
}

function appendChatbotBubble(role, text) {
  const box = document.getElementById('chatbot-messages');
  if (!box) return;
  const el = document.createElement('div');
  el.className = `chatbot-bubble ${role}`;
  el.textContent = text;
  box.appendChild(el);
  box.scrollTop = box.scrollHeight;
}

function renderChatbotChoices(items) {
  const wrap = document.getElementById('chatbot-choices');
  if (!wrap) return;
  wrap.innerHTML = '';
  items.forEach((item) => {
    const btn = document.createElement('button');
    btn.type = 'button';
    btn.className = 'chatbot-choice';
    btn.textContent = item.label;
    btn.addEventListener('click', item.onClick);
    wrap.appendChild(btn);
  });
}

function showChatbotMenu() {
  const copy = chatbotCopy();
  const msgs = document.getElementById('chatbot-messages');
  if (msgs) msgs.innerHTML = '';
  appendChatbotBubble('bot', copy.welcome);
  renderChatbotChoices(CHATBOT_FAQS.map((faq) => ({
    label: faq.q[chatbotLang],
    onClick: () => answerChatbotFaq(faq),
  })));
}

function answerChatbotFaq(faq) {
  const copy = chatbotCopy();
  appendChatbotBubble('user', faq.q[chatbotLang]);
  appendChatbotBubble('bot', faq.a[chatbotLang]);
  const items = (faq.actions || []).map((action) => ({
    label: action[chatbotLang],
    onClick: () => {
      if (isStaffOrAdmin()) {
        closeChatbot();
        switchView('admin');
        if (action.tab === 'inquiries') switchAdminSection('inq-mgmt');
        return;
      }
      switchView(currentUser ? 'user' : 'guest');
      switchTab(action.tab);
      closeChatbot();
    },
  }));
  items.push({ label: copy.more, onClick: showChatbotMenu });
  renderChatbotChoices(items);
}

function setChatbotLang(lang) {
  chatbotLang = lang;
  document.getElementById('chatbot-lang-en')?.classList.toggle('is-active', lang === 'en');
  document.getElementById('chatbot-lang-fil')?.classList.toggle('is-active', lang === 'fil');
  const copy = chatbotCopy();
  const sub = document.getElementById('chatbot-sub');
  if (sub) sub.textContent = copy.subtitle;
  showChatbotMenu();
}

function updateChatbotVisibility(view) {
  const root = document.getElementById('chatbot');
  if (!root) return;
  const show = view === 'guest' || view === 'user';
  root.hidden = !show;
  if (!show) closeChatbot();
}

function openChatbot() {
  const panel = document.getElementById('chatbot-panel');
  if (!panel) return;
  panel.classList.add('is-open');
  document.getElementById('chatbot')?.classList.add('is-open');
  const toggle = document.getElementById('chatbot-toggle');
  if (toggle) {
    toggle.setAttribute('aria-label', 'Close help assistant');
    const label = toggle.querySelector('span');
    if (label) label.textContent = 'Close';
  }
  if (!chatbotStarted) {
    chatbotStarted = true;
    showChatbotMenu();
  }
}

function closeChatbot() {
  const panel = document.getElementById('chatbot-panel');
  if (panel) panel.classList.remove('is-open');
  document.getElementById('chatbot')?.classList.remove('is-open');
  const toggle = document.getElementById('chatbot-toggle');
  if (toggle) {
    toggle.setAttribute('aria-label', 'Open help assistant');
    const label = toggle.querySelector('span');
    if (label) label.textContent = 'Help';
  }
}

function toggleChatbot() {
  const panel = document.getElementById('chatbot-panel');
  if (panel?.classList.contains('is-open')) closeChatbot();
  else openChatbot();
}

function initChatbot() {
  const openBtn = document.getElementById('chatbot-toggle');
  const closeBtn = document.getElementById('chatbot-close');
  const langEn = document.getElementById('chatbot-lang-en');
  const langFil = document.getElementById('chatbot-lang-fil');
  if (openBtn) {
    openBtn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      toggleChatbot();
    });
  }
  if (closeBtn) {
    closeBtn.addEventListener('click', (event) => {
      event.preventDefault();
      event.stopPropagation();
      closeChatbot();
    });
  }
  if (langEn) langEn.addEventListener('click', () => setChatbotLang('en'));
  if (langFil) langFil.addEventListener('click', () => setChatbotLang('fil'));
  updateChatbotVisibility(
    document.getElementById('view-admin')?.classList.contains('active')
      ? 'admin'
      : document.getElementById('view-auth')?.classList.contains('active')
        ? 'auth'
        : 'guest',
  );
}

const UI_ZOOM_MIN = 1;
const UI_ZOOM_MAX = 1.5;
const UI_ZOOM_STEP = 0.1;
const UI_ZOOM_KEY = 'pharmalocate-ui-zoom';
let uiZoom = 1;

function refreshMapsAfterZoom() {
  window.setTimeout(() => {
    try { customerMap?.invalidateSize?.(); } catch (_) { /* map may be unmounted */ }
    try { adminGeofenceMap?.invalidateSize?.(); } catch (_) { /* map may be unmounted */ }
  }, 80);
}

function applyUiZoom(next) {
  const rounded = Math.round(next * 10) / 10;
  uiZoom = Math.min(UI_ZOOM_MAX, Math.max(UI_ZOOM_MIN, rounded));
  document.documentElement.style.setProperty('--ui-zoom', String(uiZoom));
  try { localStorage.setItem(UI_ZOOM_KEY, String(uiZoom)); } catch (_) { /* private mode */ }
  const value = document.getElementById('a11y-zoom-value');
  if (value) value.textContent = `${Math.round(uiZoom * 100)}%`;
  const zoomIn = document.getElementById('a11y-zoom-in');
  const zoomOut = document.getElementById('a11y-zoom-out');
  if (zoomIn) zoomIn.disabled = uiZoom >= UI_ZOOM_MAX - 0.001;
  if (zoomOut) zoomOut.disabled = uiZoom <= UI_ZOOM_MIN + 0.001;
  refreshMapsAfterZoom();
}

function initA11yZoom() {
  let stored = 1;
  try { stored = Number.parseFloat(localStorage.getItem(UI_ZOOM_KEY) || '1'); } catch (_) { stored = 1; }
  if (!Number.isFinite(stored)) stored = 1;
  applyUiZoom(stored);
  document.getElementById('a11y-zoom-in')?.addEventListener('click', () => applyUiZoom(uiZoom + UI_ZOOM_STEP));
  document.getElementById('a11y-zoom-out')?.addEventListener('click', () => applyUiZoom(uiZoom - UI_ZOOM_STEP));
  document.getElementById('setting-low-stock-threshold')?.addEventListener('input', previewLowStockThreshold);
}

const GUEST_TOUR_KEY = 'ph_guest_tour_done';
let guideOpen = false;
let guideFinishing = false;
let guideSteps = [];
let guideIndex = 0;
let guideKind = '';
let guideRenderToken = 0;

function tourStorageKey(user = currentUser) {
  return user?.id ? `ph_tour_done_${user.id}` : '';
}

function guideAlreadyDone() {
  if (!currentUser) return localStorage.getItem(GUEST_TOUR_KEY) === '1';
  if (currentUser.tour_completed_at) return true;
  const key = tourStorageKey();
  return Boolean(key && localStorage.getItem(key) === '1');
}

function guideStepList() {
  if (!currentUser) {
    return [
      {
        target: '#tab-pharmacies',
        title: 'Where to find the Pharmacy Locator',
        body: 'Open Pharmacies in the top menu. That is the locator for stores around Tarlac Provincial Hospital. You can skip this guide or continue. It appears only the first time you visit.',
        prepare: () => switchTab('home'),
      },
      {
        target: '#pharmacy-list',
        title: 'Choose a pharmacy',
        body: 'Click a name in this list. The selected pharmacy is highlighted, and the map moves to it.',
        prepare: () => switchTab('pharmacies'),
        wait: 350,
      },
      {
        target: '#guest-leaflet-map',
        title: 'Follow the map',
        body: 'Each pin is a pharmacy. Click a pin to select that store, the same way you click a name in the list.',
        prepare: () => switchTab('pharmacies'),
        wait: 400,
      },
      {
        target: '#pharmacy-directions-btn',
        title: 'Get directions',
        body: 'After you select a pharmacy, this button opens a route from the hospital area to that store.',
        prepare: () => switchTab('pharmacies'),
      },
    ];
  }

  if (!isStaffOrAdmin()) {
    return [
      {
        target: '#tab-home',
        title: 'Home',
        body: 'Home is the starting page. It introduces the locator and shows a few medicines that are currently in stock. You can skip this guide or continue. It appears only the first time you sign in.',
        prepare: () => switchTab('home'),
      },
      {
        target: '#tab-pharmacies',
        title: 'Pharmacy Locator',
        body: 'Pharmacies lists the stores around Tarlac Provincial Hospital. Select one, then use the map and Get directions.',
        prepare: () => switchTab('pharmacies'),
        wait: 350,
      },
      {
        target: '#tab-medicines',
        title: 'Medicines',
        body: 'Medicines shows whether an item is in stock. You cannot buy from this page. SpaRx Pharmacy and Magic 8 Pharmacy keep separate counts.',
        prepare: () => switchTab('medicines'),
      },
      {
        target: '#tab-inquiries',
        title: 'Inquiries',
        body: 'Inquiries lets you write to one pharmacy. Choose the pharmacy, send your question, and read the reply in Your inquiries.',
        prepare: () => switchTab('inquiries'),
      },
    ];
  }

  const ownPharmacy = isPharmacyScopedUser();
  const steps = [
    {
      target: '#sidebar-dashboard',
      title: 'Dashboard',
      body: ownPharmacy
        ? 'Dashboard summarizes your pharmacy: inquiries, stock, and today’s sales. You can skip this guide or continue. It appears only the first time you sign in.'
        : 'Dashboard summarizes both pharmacies: inquiries, stock, and today’s sales. You can skip this guide or continue. It appears only the first time you sign in.',
      prepare: () => switchAdminSection('dashboard'),
    },
    {
      target: '#sidebar-inquiries',
      title: 'Reply to inquiries',
      body: ownPharmacy
        ? 'Reply to questions sent to your pharmacy. After you reply, the message moves to the replied list, where you can delete it.'
        : 'Reply to questions for SpaRx Pharmacy and Magic 8 Pharmacy. Replied messages are listed under each pharmacy, and you can delete them there.',
      prepare: () => switchAdminSection('inq-mgmt'),
    },
    {
      target: '#sidebar-stock',
      title: 'Inventory management',
      body: ownPharmacy
        ? 'Add a medicine with its price and quantity, edit the stock, or remove it. You only change your own pharmacy.'
        : 'Add a medicine with its price and quantity, edit the stock, or remove it. You can do this for both SpaRx Pharmacy and Magic 8 Pharmacy.',
      prepare: () => switchAdminSection('stock'),
    },
    {
      target: '#sidebar-pos',
      title: 'POS — sales',
      body: ownPharmacy
        ? 'Record a sale for your pharmacy. Add items to the cart and save the sale. The stock count updates after the sale.'
        : 'Record a sale for the pharmacy you select. Add items to the cart and save the sale. That pharmacy’s stock count updates.',
      prepare: () => switchAdminSection('pos'),
    },
    {
      target: '#sidebar-pharmacies',
      title: 'Manage pharmacies',
      body: ownPharmacy
        ? 'This page shows your pharmacy. You can review its details here.'
        : 'Add a pharmacy or edit a pharmacy’s name, address, hours, and location.',
      prepare: () => switchAdminSection('pharmacies'),
    },
    {
      target: '#sidebar-geofences',
      title: 'Geofences',
      body: ownPharmacy
        ? 'View the map and save your pharmacy’s 50 meter location. Creating or editing a starting-point zone stays with the administrator.'
        : 'Create and edit geofence zones, then place a pharmacy pin inside a starting point.',
      prepare: () => switchAdminSection('geofences'),
    },
  ];
  if (!ownPharmacy) {
    steps.push({
      target: '#sidebar-users',
      title: 'User management',
      body: 'Add a staff or customer account, edit it, or deactivate it so that person can no longer sign in. The administrator account stays locked.',
      prepare: () => switchAdminSection('users'),
    });
  }
  steps.push(
    {
      target: '#sidebar-settings',
      title: 'System settings',
      body: ownPharmacy
        ? 'You can view the low-stock threshold and notification settings. Only an administrator can save changes.'
        : 'Set the low-stock threshold and turn inventory and inquiry notifications on or off.',
      prepare: () => switchAdminSection('settings'),
    },
    {
      target: '#sidebar-backup',
      title: 'Backup & export',
      body: ownPharmacy
        ? 'Download a spreadsheet of your pharmacy’s sales.'
        : 'Download a copy of the system data, including sales and inventory.',
      prepare: () => switchAdminSection('backup'),
    },
  );
  return steps;
}

function maybeOfferGuide() {
  if (guideOpen || guideAlreadyDone()) return;
  const onAuth = document.getElementById('view-auth')?.classList.contains('active');
  if (!currentUser && onAuth) return;
  startGuide();
}

function startGuide() {
  guideSteps = guideStepList();
  guideIndex = 0;
  guideKind = currentUser ? (isStaffOrAdmin() ? 'staff' : 'customer') : 'guest';
  guideOpen = true;
  guideFinishing = false;
  document.getElementById('guide')?.classList.remove('hidden');
  showGuideStep();
}

async function showGuideStep() {
  const token = ++guideRenderToken;
  while (guideIndex < guideSteps.length) {
    const step = guideSteps[guideIndex];
    if (typeof step.prepare === 'function') step.prepare();
    if (step.wait) await new Promise(resolve => setTimeout(resolve, step.wait));
    if (token !== guideRenderToken || !guideOpen) return;
    const target = document.querySelector(step.target);
    if (target && target.getClientRects().length) {
      target.scrollIntoView({ block: 'nearest', inline: 'nearest' });
      const stepLabel = document.getElementById('guide-step');
      const title = document.getElementById('guide-title');
      const body = document.getElementById('guide-body');
      const next = document.getElementById('guide-next');
      if (stepLabel) stepLabel.textContent = `Step ${guideIndex + 1} of ${guideSteps.length}`;
      if (title) title.textContent = step.title;
      if (body) body.textContent = step.body;
      if (next) next.textContent = guideIndex === guideSteps.length - 1 ? 'Done' : 'Continue';
      positionGuide(target);
      next?.focus();
      return;
    }
    guideIndex += 1;
  }
  finishGuide();
}

function positionGuide(target) {
  const rect = target.getBoundingClientRect();
  const pad = 6;
  const spot = document.getElementById('guide-spot');
  if (spot) {
    spot.style.top = `${Math.max(0, rect.top - pad)}px`;
    spot.style.left = `${Math.max(0, rect.left - pad)}px`;
    spot.style.width = `${rect.width + pad * 2}px`;
    spot.style.height = `${rect.height + pad * 2}px`;
  }
  const card = document.getElementById('guide-card');
  if (!card) return;
  const margin = 12;
  const width = card.offsetWidth || 320;
  const height = card.offsetHeight || 160;
  let left = rect.left;
  let top = rect.bottom + margin;
  if (rect.right < window.innerWidth * 0.55) {
    left = rect.right + margin;
    top = Math.max(margin, rect.top);
  }
  if (left + width > window.innerWidth - margin) left = window.innerWidth - width - margin;
  if (top + height > window.innerHeight - margin) top = Math.max(margin, rect.top - height - margin);
  if (left < margin) left = margin;
  card.style.left = `${left}px`;
  card.style.top = `${top}px`;
}

async function finishGuide() {
  if (guideFinishing) return;
  guideFinishing = true;
  guideOpen = false;
  guideRenderToken += 1;
  document.getElementById('guide')?.classList.add('hidden');
  const kind = guideKind;
  guideSteps = [];
  guideKind = '';
  if (kind === 'guest') {
    try { localStorage.setItem(GUEST_TOUR_KEY, '1'); } catch (_) { /* private mode */ }
    guideFinishing = false;
    return;
  }
  const key = tourStorageKey();
  if (key) {
    try { localStorage.setItem(key, '1'); } catch (_) { /* private mode */ }
  }
  if (currentUser) {
    currentUser.tour_completed_at = new Date().toISOString();
    saveSession(currentUser, apiToken);
  }
  if (isLiveMode() && apiToken && apiToken !== 'demo') {
    try { await apiFetch('/tour/complete', { method: 'POST' }); } catch (_) { /* keep the local flag */ }
  }
  if (isStaffOrAdmin()) switchAdminSection('dashboard');
  else switchTab('home');
  guideFinishing = false;
}

function initGuide() {
  document.getElementById('guide-skip')?.addEventListener('click', () => finishGuide());
  document.getElementById('guide-next')?.addEventListener('click', () => {
    if (!guideOpen) return;
    guideIndex += 1;
    if (guideIndex >= guideSteps.length) finishGuide();
    else showGuideStep();
  });
  document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape' && guideOpen) finishGuide();
  });
  window.addEventListener('resize', () => {
    if (!guideOpen) return;
    const step = guideSteps[guideIndex];
    const target = step ? document.querySelector(step.target) : null;
    if (target) positionGuide(target);
  });
}

document.addEventListener('DOMContentLoaded', async () => {
  try {
    if (!isLiveMode()) {
      applyMedicineFilters();
    }
    renderInquiries([]);
    initChatbot();
    initA11yZoom();
    initGuide();
    loadSignupTerms();
    try {
      await Promise.race([
        initLiveData(),
        new Promise((resolve) => setTimeout(resolve, 6000)),
      ]);
    } catch (_) { /* show UI anyway */ }
    if (currentUser) {
      switchView(isStaffOrAdmin() ? 'admin' : 'user');
    }
  } finally {
    revealApp();
  }
  setTimeout(maybeOfferGuide, 280);
});
