@extends('layouts.app')
@section('title', 'Pharmacies')

@push('head')
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
@endpush

@section('content')
<div class="scroll-area">
  <div class="page">

    <div class="section-hd">
      <h2><i class="ti ti-map-pin"></i> Nearest pharmacies</h2>
    </div>

    <div class="geo-layout">
      <div>
        <div class="geofence-notice">
          <i class="ti ti-radar"></i>
          Showing pharmacies within <strong style="margin:0 3px;">5 km</strong> geofence of Tarlac Provincial Hospital
        </div>
        <div class="pharmacy-list">
          @foreach ($pharmacies as $p)
            <div class="pharmacy-item {{ $loop->first ? 'active' : '' }}" data-lat="{{ $p->latitude }}" data-lng="{{ $p->longitude }}" data-name="{{ $p->name }}" onclick="selectPharmacy(this, {{ $p->latitude }}, {{ $p->longitude }})">
              <div class="pharm-name">{{ $p->name }}</div>
              <div class="pharm-row">
                <span class="pharm-dist"><i class="ti ti-walk"></i> {{ $p->distance_km }} km</span>
                <span class="badge {{ $p->is_active ? 'badge-green' : 'badge-red' }}">{{ $p->is_active ? 'Open' : 'Closed' }}</span>
              </div>
              <div class="pharm-addr">{{ $p->address }}</div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="geo-map-wrap">
        <div id="leaflet-map"></div>
        <div class="map-overlay"><i class="ti ti-current-location"></i> Tarlac City, Tarlac, PH</div>
      </div>
    </div>

  </div>
</div>

@php
  $mapPharmacies = $pharmacies->map(fn ($p) => ['name' => $p->name, 'lat' => (float) $p->latitude, 'lng' => (float) $p->longitude, 'address' => $p->address, 'distance' => $p->distance_km]);
  $mapGeofences = $geofences->map(fn ($g) => ['name' => $g->name, 'lat' => (float) $g->center_latitude, 'lng' => (float) $g->center_longitude, 'radius' => $g->radius_meters]);
@endphp
<script id="map-data" type="application/json">@json(['center' => $center, 'pharmacies' => $mapPharmacies, 'geofences' => $mapGeofences])</script>
@endsection

@push('scripts')
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
<script>
  const DATA = JSON.parse(document.getElementById('map-data').textContent);
  const map = L.map('leaflet-map').setView([DATA.center.lat, DATA.center.lng], 15);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
    maxZoom: 19,
    attribution: '&copy; OpenStreetMap contributors'
  }).addTo(map);

  // Draw geofence service zones (circles) — the geofencing visualization.
  DATA.geofences.forEach(g => {
    L.circle([g.lat, g.lng], {
      radius: g.radius,
      color: '#1D9E75', weight: 1.5, dashArray: '6,5',
      fillColor: '#1D9E75', fillOpacity: 0.06
    }).addTo(map).bindTooltip(g.name);
  });

  // "You are here" marker at the hospital center.
  L.circleMarker([DATA.center.lat, DATA.center.lng], {
    radius: 8, color: '#fff', weight: 2, fillColor: '#1D9E75', fillOpacity: 1
  }).addTo(map).bindTooltip('Tarlac Provincial Hospital (center)');

  // Pharmacy markers.
  const markers = {};
  DATA.pharmacies.forEach(p => {
    const m = L.marker([p.lat, p.lng]).addTo(map)
      .bindPopup(`<strong>${p.name}</strong><br>${p.address}<br>~${p.distance} km`);
    markers[p.name] = m;
  });

  function selectPharmacy(el, lat, lng) {
    document.querySelectorAll('.pharmacy-item').forEach(p => p.classList.remove('active'));
    el.classList.add('active');
    map.setView([lat, lng], 16);
    const m = markers[el.dataset.name];
    if (m) m.openPopup();
  }
</script>
@endpush
