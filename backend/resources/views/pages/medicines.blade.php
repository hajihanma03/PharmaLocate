@extends('layouts.app')
@section('title', 'Medicines')

@php
  $badge = fn ($s) => $s === 'available' ? 'badge-green' : ($s === 'low' ? 'badge-amber' : 'badge-red');
  $label = fn ($i) => $i->status === 'available' ? 'Available · '.$i->qty.' pcs' : ($i->status === 'low' ? 'Low stock · '.$i->qty.' pcs' : 'Out of stock');
@endphp

@section('content')
<div class="scroll-area">
  <div class="page">

    <div class="section-hd">
      <h2><i class="ti ti-pill"></i> Medicine availability</h2>
      <form method="GET" action="{{ route('medicines') }}" style="display:flex; gap:8px;">
        <input type="text" name="search" value="{{ $search }}" placeholder="Search medicine..."
          style="padding:7px 11px; font-size:13px; border:1px solid var(--border-md); border-radius:var(--radius-md); width:200px; background:var(--surface); color:var(--text);"
          oninput="filterMeds(this.value)" />
        <select onchange="filterMedsByPharmacy(this.value)"
          style="padding:7px 11px; font-size:13px; border:1px solid var(--border-md); border-radius:var(--radius-md); background:var(--surface); color:var(--text);">
          <option value="">All pharmacies</option>
          @foreach ($pharmacyNames as $name)
            <option>{{ $name }}</option>
          @endforeach
        </select>
      </form>
    </div>

    <div class="grid-auto" id="med-grid">
      @forelse ($items as $item)
        <div class="med-card" data-name="{{ strtolower($item->name) }}" data-pharmacy="{{ $item->pharmacy_name }}">
          <div class="med-card-top">
            <div class="med-icon"><i class="ti ti-pill"></i></div>
            <span class="badge {{ $badge($item->status) }}">{{ $label($item) }}</span>
          </div>
          <div class="med-name">{{ $item->name }}</div>
          <div class="med-type">{{ $item->brand ?: $item->description }} · &#8369;{{ number_format($item->price, 2) }}</div>
          <div class="med-pharmacy"><i class="ti ti-building-store"></i> {{ $item->pharmacy_name }}</div>
        </div>
      @empty
        <div class="text-muted">No results.</div>
      @endforelse
    </div>

  </div>
</div>
@endsection

@push('scripts')
<script>
  // Client-side filtering over the server-rendered cards (no reload).
  function applyFilters() {
    const q = (window._q || '').toLowerCase();
    const ph = window._ph || '';
    document.querySelectorAll('#med-grid .med-card').forEach(card => {
      const matchName = card.dataset.name.includes(q);
      const matchPh = !ph || card.dataset.pharmacy === ph;
      card.style.display = (matchName && matchPh) ? '' : 'none';
    });
  }
  function filterMeds(v) { window._q = v; applyFilters(); }
  function filterMedsByPharmacy(v) { window._ph = v; applyFilters(); }
</script>
@endpush
