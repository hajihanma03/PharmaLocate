@extends('layouts.app')
@section('title', 'Home')

@php
  $badge = fn ($s) => $s === 'available' ? 'badge-green' : ($s === 'low' ? 'badge-amber' : 'badge-red');
  $label = fn ($i) => $i->status === 'available' ? 'Available · '.$i->qty.' pcs' : ($i->status === 'low' ? 'Low stock · '.$i->qty.' pcs' : 'Out of stock');
@endphp

@section('content')
<div class="scroll-area">
  <div class="page">

    @if (session('status'))
      <div class="flash flash-success">{{ session('status') }}</div>
    @endif

    <div class="hero">
      <div class="hero-body">
        <h1>Find medicines <em>near you</em>,<br>in real time.</h1>
        <p>
          PharmaLocate connects you instantly with pharmacies around Tarlac Provincial Hospital.
          Check availability, view live stock, and get directions — all in one place.
        </p>
        <div class="hero-btns">
          <a class="btn btn-primary btn-lg" href="{{ route('pharmacies') }}"><i class="ti ti-map-2"></i> Find pharmacies</a>
          <a class="btn btn-lg" href="{{ route('medicines') }}"><i class="ti ti-pill"></i> Browse medicines</a>
        </div>
      </div>

      <div class="hero-map">
        <svg viewBox="0 0 220 170" xmlns="http://www.w3.org/2000/svg">
          <rect width="220" height="170" fill="#E8F5EE"/>
          <rect x="0" y="60" width="220" height="12" fill="#9FE1CB"/>
          <rect x="0" y="110" width="220" height="12" fill="#9FE1CB"/>
          <rect x="72" y="0" width="12" height="170" fill="#9FE1CB"/>
          <rect x="145" y="0" width="12" height="170" fill="#9FE1CB"/>
          <circle cx="110" cy="85" r="62" fill="none" stroke="#1D9E75" stroke-width="1.2" stroke-dasharray="5,4" opacity="0.55"/>
          <circle cx="110" cy="85" r="35" fill="none" stroke="#1D9E75" stroke-width="1" opacity="0.3"/>
          <circle cx="110" cy="85" r="14" fill="#1D9E75" fill-opacity="0.15"/>
          <circle cx="110" cy="85" r="6" fill="#1D9E75"/>
          <circle cx="130" cy="68" r="7" fill="#378ADD"/>
          <circle cx="92" cy="100" r="7" fill="#378ADD"/>
          <line x1="110" y1="85" x2="130" y2="68" stroke="#1D9E75" stroke-width="1" stroke-dasharray="3,3"/>
          <line x1="110" y1="85" x2="92" y2="100" stroke="#1D9E75" stroke-width="1" stroke-dasharray="3,3"/>
        </svg>
        <div class="hero-map-label"><i class="ti ti-focus-2"></i> 5 km geofence</div>
      </div>
    </div>

    <div class="grid-2">
      <div>
        <div class="section-hd">
          <h2><i class="ti ti-pill"></i> Available medicines</h2>
          <a class="btn btn-ghost btn-sm" href="{{ route('medicines') }}">View all <i class="ti ti-arrow-right"></i></a>
        </div>
        <div style="display:flex; flex-direction:column; gap:9px;">
          @foreach ($featured as $item)
            <div class="med-card">
              <div class="med-card-top">
                <div class="med-icon"><i class="ti ti-pill"></i></div>
                <span class="badge {{ $badge($item->status) }}">{{ $label($item) }}</span>
              </div>
              <div class="med-name">{{ $item->name }}</div>
              <div class="med-type">{{ $item->brand ?: $item->description }}</div>
              <div class="med-pharmacy"><i class="ti ti-building-store"></i> {{ $item->pharmacy_name }}</div>
            </div>
          @endforeach
        </div>
      </div>

      <div class="tips-panel">
        <h3><i class="ti ti-info-circle"></i> Medicine safety tips</h3>
        <div class="tip-row"><div class="tip-dot"></div><div class="tip-text">Take medications exactly as directed — on time and with or without food as specified.</div></div>
        <div class="tip-row"><div class="tip-dot"></div><div class="tip-text">Filling all prescriptions at one pharmacy helps prevent dangerous drug interactions.</div></div>
        <div class="tip-row"><div class="tip-dot"></div><div class="tip-text">Complete all antibiotic courses even if you feel better, to prevent resistance.</div></div>
        <div class="tip-row"><div class="tip-dot"></div><div class="tip-text">Avoid self-medicating. Ask a pharmacist before combining OTC drugs with prescriptions.</div></div>
        <div class="tip-row"><div class="tip-dot"></div><div class="tip-text">Keep medicines in a cool, dry place out of reach of children. Dispose of expired medicines properly.</div></div>
      </div>
    </div>

  </div>
</div>
@endsection
