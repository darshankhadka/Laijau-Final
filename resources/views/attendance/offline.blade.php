@extends('attendance.layout')

@section('title', 'Offline — Laijau Attendance')

@section('content')
<div class="att-card" style="text-align:center;padding:3rem 1.5rem;display:flex;flex-direction:column;align-items:center;gap:1rem;margin-top:2rem;">
    <div style="width:4rem;height:4rem;border-radius:9999px;background:#fee2e2;color:#dc2626;display:flex;align-items:center;justify-content:center;font-size:2rem;">
        📶
    </div>

    <h2 style="font-size:1.25rem;font-weight:900;color:var(--att-navy);margin:0;">
        Internet Connection Required
    </h2>

    <p style="font-size:0.8125rem;color:var(--att-gray-600);line-height:1.5;max-width:320px;">
        To ensure accurate server-authoritative timestamps, GPS verification, and photo submission, attendance requires an active internet connection.
    </p>

    <div style="width:100%;margin-top:1rem;">
        <button
            type="button"
            onclick="window.location.reload()"
            class="att-btn att-btn-primary">
            🔄 Retry Connection
        </button>
    </div>
</div>
@endsection
