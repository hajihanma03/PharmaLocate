@extends('layouts.app')
@section('title', 'Create account')

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
        <a class="auth-seg-btn" href="{{ route('login') }}">Log in</a>
        <a class="auth-seg-btn active" href="{{ route('register') }}">Sign up</a>
      </div>

      @if ($errors->any())
        <div class="flash flash-error">{{ $errors->first() }}</div>
      @endif

      <form method="POST" action="{{ route('register') }}" id="auth-signup-form">
        @csrf
        <div class="form-group">
          <label>Full name</label>
          <input type="text" name="name" value="{{ old('name') }}" placeholder="e.g. Juan Dela Cruz" autofocus />
        </div>
        <div class="form-group">
          <label>Username</label>
          <input type="text" name="username" value="{{ old('username') }}" placeholder="Choose a username" />
        </div>
        <div class="form-group">
          <label>Email address</label>
          <input type="email" name="email" value="{{ old('email') }}" placeholder="your@email.com" />
        </div>
        <div class="form-group">
          <label>Password</label>
          <input type="password" name="password" placeholder="Create a strong password (min 8 chars)" />
        </div>
        <div class="form-group">
          <label>Confirm password</label>
          <input type="password" name="password_confirmation" placeholder="Re-enter your password" />
        </div>
        <button type="submit" class="btn btn-primary btn-full">
          <i class="ti ti-user-check"></i> Create account
        </button>
      </form>

      <div class="auth-footer">
        <a href="{{ route('home') }}">&larr; Browse as guest without an account</a>
      </div>

    </div>
  </div>
</div>
@endsection
