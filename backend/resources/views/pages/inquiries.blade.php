@extends('layouts.app')
@section('title', 'Inquiries')

@php
  $statusBadge = fn ($s) => $s === 'resolved' ? 'badge-green' : ($s === 'in_progress' ? 'badge-amber' : 'badge-gray');
  $statusText = fn ($s) => $s === 'resolved' ? 'Replied' : ($s === 'in_progress' ? 'In progress' : 'Pending');
@endphp

@section('content')
<div class="scroll-area">
  <div class="page">

    @if (session('status'))
      <div class="flash flash-success">{{ session('status') }}</div>
    @endif

    <div class="inquiry-layout">

      <div>
        <div class="section-hd">
          <h2><i class="ti ti-message-question"></i> Send an inquiry</h2>
        </div>
        <div class="card card-p">
          @if ($errors->any())
            <div class="flash flash-error">{{ $errors->first() }}</div>
          @endif

          <form method="POST" action="{{ route('inquiries.store') }}">
            @csrf
            <div class="form-group">
              <label>Select pharmacy</label>
              <select name="pharmacy_id">
                <option value="">Any / not sure</option>
                @foreach ($pharmacies as $p)
                  <option value="{{ $p->id }}">{{ $p->name }}</option>
                @endforeach
              </select>
            </div>
            <div class="form-group">
              <label>Message / Question</label>
              <textarea name="message" placeholder="Describe your inquiry or question in detail...">{{ old('message') }}</textarea>
            </div>
            <button type="submit" class="btn btn-primary btn-full">
              <i class="ti ti-send"></i> Submit inquiry
            </button>
          </form>
        </div>
      </div>

      <div>
        <div class="section-hd">
          <h2 style="font-size:14px;"><i class="ti ti-clock-history"></i> Your inquiries</h2>
        </div>
        @forelse ($inquiries as $inq)
          <div class="inq-item">
            <div class="inq-item-top">
              <span class="inq-med">{{ $inq->medicine?->name ?? \Illuminate\Support\Str::limit($inq->message, 40) }}</span>
              <span class="badge {{ $statusBadge($inq->status) }}">{{ $statusText($inq->status) }}</span>
            </div>
            <div class="inq-meta">
              <span class="badge badge-gray text-xs">{{ $inq->pharmacy?->name ?? 'Any pharmacy' }}</span>
              <span class="inq-time">{{ $inq->created_at->diffForHumans() }}</span>
            </div>
            @if ($inq->response)
              <div class="inq-reply">{{ $inq->response }}</div>
            @endif
          </div>
        @empty
          <div class="text-muted">You haven't submitted any inquiries yet.</div>
        @endforelse
      </div>

    </div>

  </div>
</div>
@endsection
