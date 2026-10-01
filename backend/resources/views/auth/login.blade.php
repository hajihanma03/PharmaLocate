@extends('layouts.app')
@section('title', 'Log in')

@section('content')
<div class="view active" id="view-auth">
  <div class="auth-wrap">
    <div class="auth-card">

      <div class="auth-header">
        <div class="auth-logo-icon"><i class="ti ti-first-aid-kit" aria-hidden="true"></i></div>
        <div class="auth-title">PharmaLocate</div>
        <div class="auth-sub">Geofencing-Powered Pharmacy System</div>
      </div>

      <div class="auth-seg">
        <a class="auth-seg-btn active" href="{{ route('login') }}">Log in</a>
        <a class="auth-seg-btn" href="{{ route('register') }}">Sign up</a>
      </div>

      @if ($errors->any())
        <div class="flash flash-error">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('login') }}" id="auth-login-form">
        @csrf
        <div class="form-group">
          <label>Email or username</label>
          <input type="text" name="login" value="{{ old('login') }}" placeholder="Enter your email or username" autofocus />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Enter your password" />
        </div>
        <button type="submit" class="btn btn-primary btn-full mb-8">
          <i class="ti ti-login"></i> Log in
        </button>
        <a class="btn btn-full btn-ghost" style="font-size:12px; color:var(--text-2);" href="{{ route('home') }}">
          Continue as guest
        </a>
      </form>

      <div class="auth-footer">
        <a href="{{ route('home') }}">&larr; Browse as guest without an account</a>
      </div>

    </div>
  </div>
</div>
@endsection
