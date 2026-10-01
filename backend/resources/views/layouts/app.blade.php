<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <meta name="csrf-token" content="{{ csrf_token() }}" />
  <title>@yield('title', 'PharmaLocate') — Geofencing-Powered Pharmacy System</title>

  <link rel="preconnect" href="https://fonts.googleapis.com" />
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,400;0,9..40,500;0,9..40,600;1,9..40,400&family=Instrument+Serif:ital@0;1&display=swap" rel="stylesheet" />
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.19.0/dist/tabler-icons.min.css" />
  <link rel="stylesheet" href="{{ asset('css/pharmalocate.css') }}" />
  @stack('head')
</head>
<body>
<div class="app">

  <nav class="navbar">
    <a href="{{ route('home') }}" class="nav-brand">
      <div class="nav-brand-icon"><i class="ti ti-first-aid-kit" aria-hidden="true"></i></div>
      <div class="nav-brand-text">
        <span class="nav-brand-name">PharmaLocate</span>
        <span class="nav-brand-sub">Geofencing-Powered Pharmacy System</span>
      </div>
    </a>

    <div class="nav-divider"></div>

    <div class="nav-spacer"></div>

    <div class="nav-tabs" id="nav-tabs">
      <a class="nav-tab {{ request()->routeIs('home') ? 'active' : '' }}" href="{{ route('home') }}">
        <i class="ti ti-home" aria-hidden="true"></i> Home
      </a>
      <a class="nav-tab {{ request()->routeIs('pharmacies*') ? 'active' : '' }}" href="{{ route('pharmacies') }}">
        <i class="ti ti-map-pin" aria-hidden="true"></i> Pharmacies
      </a>
      <a class="nav-tab {{ request()->routeIs('medicines*') ? 'active' : '' }}" href="{{ route('medicines') }}">
        <i class="ti ti-pill" aria-hidden="true"></i> Medicines
      </a>
      <a class="nav-tab {{ request()->routeIs('inquiries*') ? 'active' : '' }}" href="{{ route('inquiries') }}">
        <i class="ti ti-message-question" aria-hidden="true"></i> Inquiries
      </a>
    </div>

    <div class="nav-auth" id="nav-auth">
      @auth
        <div style="display:flex;align-items:center;gap:10px;">
          <div style="display:flex;align-items:center;gap:7px;font-size:13px;color:var(--text);">
            <div style="width:30px;height:30px;border-radius:50%;background:var(--green-light);display:flex;align-items:center;justify-content:center;font-size:11px;font-weight:600;color:var(--green-700);">
              {{ strtoupper(substr(auth()->user()->name, 0, 2)) }}
            </div>
            {{ auth()->user()->name }}
          </div>
          <form method="POST" action="{{ route('logout') }}">
            @csrf
            <button type="submit" class="btn btn-ghost btn-sm"><i class="ti ti-logout"></i> Log out</button>
          </form>
        </div>
      @else
        <a class="btn btn-ghost btn-sm" href="{{ route('login') }}"><i class="ti ti-login"></i> Log in</a>
        <a class="btn btn-primary btn-sm" href="{{ route('register') }}"><i class="ti ti-user-plus"></i> Sign up</a>
      @endauth
    </div>
  </nav>

  @yield('content')

</div>
@stack('scripts')
</body>
</html>
