@php
    /** @var \App\Models\Hrm\Timesheet $record */
    $sessions = $record->attendanceSessions()->orderBy('session_number')->get();
    
    // Fallback to sessions_summary json if relation is empty
    $rawSessions = $record->sessions_summary ?? [];
    $employee = $record->employee;
@endphp

<div class="lj-timesheet-modal-wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; flex-direction: column; gap: 1.25rem;">
    <style>
        .lj-timesheet-modal-wrap {
            font-size: 0.875rem;
            color: #1e293b;
        }
        .dark .lj-timesheet-modal-wrap {
            color: #f1f5f9;
        }
        .lj-card-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem 1.25rem;
        }
        .dark .lj-card-box {
            background: #1e293b;
            border-color: #334155;
        }
        .lj-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            letter-spacing: 0.03em;
            text-transform: uppercase;
        }
        .lj-badge-success { background: #dcfce7; color: #15803d; border: 1px solid #bbf7d0; }
        .lj-badge-info { background: #e0f2fe; color: #0369a1; border: 1px solid #bae6fd; }
        .lj-badge-warning { background: #fef3c7; color: #b45309; border: 1px solid #fde68a; }
        .lj-badge-danger { background: #fee2e2; color: #b91c1c; border: 1px solid #fecaca; }
        .lj-badge-gray { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        
        .dark .lj-badge-success { background: rgba(34, 197, 94, 0.2); color: #86efac; border-color: rgba(34, 197, 94, 0.3); }
        .dark .lj-badge-info { background: rgba(14, 165, 233, 0.2); color: #7dd3fc; border-color: rgba(14, 165, 233, 0.3); }
        .dark .lj-badge-warning { background: rgba(245, 158, 11, 0.2); color: #fcd34d; border-color: rgba(245, 158, 11, 0.3); }
        .dark .lj-badge-danger { background: rgba(239, 68, 68, 0.2); color: #fca5a5; border-color: rgba(239, 68, 68, 0.3); }
        .dark .lj-badge-gray { background: rgba(148, 163, 184, 0.2); color: #cbd5e1; border-color: rgba(148, 163, 184, 0.3); }

        .lj-grid-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(140px, 1fr));
            gap: 0.75rem;
        }
        .lj-stat-tile {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .dark .lj-stat-tile {
            background: #0f172a;
            border-color: #334155;
        }
        .lj-stat-title {
            font-size: 0.6875rem;
            font-weight: 600;
            text-transform: uppercase;
            color: #64748b;
        }
        .dark .lj-stat-title {
            color: #94a3b8;
        }
        .lj-stat-val {
            font-size: 1.15rem;
            font-weight: 700;
            color: #0f172a;
        }
        .dark .lj-stat-val {
            color: #f8fafc;
        }
        .lj-session-card {
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            background: #ffffff;
            overflow: hidden;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        .dark .lj-session-card {
            border-color: #334155;
            background: #0f172a;
        }
        .lj-session-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.75rem 1rem;
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
        }
        .dark .lj-session-header {
            background: #1e293b;
            border-color: #334155;
        }
        .lj-session-body {
            padding: 1rem;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1rem;
        }
        @media (max-width: 640px) {
            .lj-session-body {
                grid-template-columns: 1fr;
            }
        }
        .lj-punch-pane {
            display: flex;
            flex-direction: column;
            gap: 0.35rem;
            background: #f8fafc;
            border: 1px solid #f1f5f9;
            border-radius: 0.5rem;
            padding: 0.75rem;
        }
        .dark .lj-punch-pane {
            background: #1e293b;
            border-color: #334155;
        }
        .lj-gps-link {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            font-size: 0.75rem;
            color: #2563eb;
            text-decoration: underline;
        }
        .dark .lj-gps-link {
            color: #60a5fa;
        }
    </style>

    <!-- Top Daily Overview Banner -->
    <div class="lj-card-box">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 0.75rem; flex-wrap: wrap; gap: 0.5rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.15rem; font-weight: 700; color: inherit;">
                    {{ $employee?->full_name ?? 'Employee' }}
                </h3>
                <div style="font-size: 0.8125rem; color: #64748b; margin-top: 0.2rem;">
                    Emp #: <span style="font-weight: 600;">{{ $employee?->employee_number ?? '—' }}</span> &bull; 
                    Date: <span style="font-weight: 600;">{{ $record->date?->format('l, M d, Y') ?? '—' }} (Kathmandu)</span> &bull;
                    Location: <span style="font-weight: 600;">{{ $record->location ?? 'Laijau Showroom' }}</span>
                </div>
            </div>
            <div style="display: flex; gap: 0.4rem; align-items: center;">
                <span class="lj-badge lj-badge-{{ $record->attendance_status === 'present' ? 'success' : ($record->attendance_status === 'half_day' ? 'warning' : 'gray') }}">
                    {{ ucfirst(str_replace('_', ' ', $record->attendance_status)) }}
                </span>
                <span class="lj-badge lj-badge-{{ $record->status === 'approved' ? 'success' : ($record->status === 'submitted' ? 'warning' : 'gray') }}">
                    Approval: {{ ucfirst($record->status) }}
                </span>
            </div>
        </div>

        <div class="lj-grid-stats">
            <div class="lj-stat-tile">
                <span class="lj-stat-title">Total Sessions</span>
                <span class="lj-stat-val">{{ $record->total_sessions ?: ($sessions->count() ?: count($rawSessions)) }}</span>
            </div>
            <div class="lj-stat-tile">
                <span class="lj-stat-title">Total Worked Hours</span>
                <span class="lj-stat-val" style="color: #2563eb;">
                    @php
                        $hrs = (float)($record->total_worked_hours ?: $record->regular_hours);
                        $mins = (int)($record->total_worked_minutes ?: ($hrs * 60));
                        $h = floor($mins / 60);
                        $m = $mins % 60;
                    @endphp
                    {{ $h }}h {{ $m }}m
                    <span style="font-size: 0.75rem; font-weight: 500; color: #64748b;">({{ number_format($hrs, 2) }} hrs)</span>
                </span>
            </div>
            <div class="lj-stat-tile">
                <span class="lj-stat-title">First Clock In</span>
                <span class="lj-stat-val" style="font-size: 0.95rem;">
                    {{ $record->clock_in ? \Carbon\Carbon::parse($record->clock_in)->format('h:i A') : '—' }}
                </span>
            </div>
            <div class="lj-stat-tile">
                <span class="lj-stat-title">Last Clock Out</span>
                <span class="lj-stat-val" style="font-size: 0.95rem;">
                    {{ $record->clock_out ? \Carbon\Carbon::parse($record->clock_out)->format('h:i A') : 'Active / Working' }}
                </span>
            </div>
            <div class="lj-stat-tile">
                <span class="lj-stat-title">Overtime</span>
                <span class="lj-stat-val" style="color: {{ (float)$record->overtime_hours > 0 ? '#d97706' : '#64748b' }};">
                    {{ number_format((float)$record->overtime_hours, 2) }} hrs
                </span>
            </div>
        </div>
    </div>

    <!-- Sessions Breakdown Header -->
    <div style="display: flex; justify-content: space-between; align-items: center;">
        <h4 style="margin: 0; font-size: 1rem; font-weight: 600;">
            Daily Attendance Sessions Breakdown
        </h4>
        <span style="font-size: 0.75rem; color: #64748b;">
            All timestamps in Nepal Standard Time (UTC+5:45)
        </span>
    </div>

    <!-- Sessions List -->
    @if($sessions->isNotEmpty())
        <div style="display: flex; flex-direction: column; gap: 0.875rem;">
            @foreach($sessions as $session)
                @php
                    $isOpen = $session->isOpen();
                    $durationText = $isOpen ? $session->getComputedDurationFormatted(now()) : ($session->duration_formatted ?: '—');
                @endphp
                <div class="lj-session-card">
                    <div class="lj-session-header">
                        <div style="display: flex; align-items: center; gap: 0.6rem;">
                            <span style="font-weight: 700; font-size: 0.95rem;">
                                Session #{{ $session->session_number }}
                            </span>
                            @if($isOpen)
                                <span class="lj-badge lj-badge-warning">
                                    <span style="display:inline-block; width:6px; height:6px; border-radius:50%; background:#d97706; animation: pulse 1.5s infinite;"></span>
                                    Currently Active
                                </span>
                            @else
                                <span class="lj-badge lj-badge-success">Completed</span>
                            @endif
                        </div>
                        <div style="font-weight: 700; font-size: 0.9rem; color: #2563eb;">
                            Duration: {{ $durationText }}
                            @if($session->duration_hours)
                                <span style="font-size: 0.75rem; font-weight: 500; color: #64748b;">({{ number_format((float)$session->duration_hours, 2) }} hrs)</span>
                            @endif
                        </div>
                    </div>

                    <div class="lj-session-body">
                        <!-- Clock In Pane -->
                        <div class="lj-punch-pane">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700; font-size: 0.8125rem; color: #15803d; text-transform: uppercase;">
                                    &darr; Clock In
                                </span>
                                <span style="font-weight: 700; font-size: 0.95rem;">
                                    {{ $session->clock_in_at ? $session->clock_in_at->timezone('Asia/Kathmandu')->format('h:i:s A') : '—' }}
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">
                                Date: {{ $session->clock_in_at ? $session->clock_in_at->timezone('Asia/Kathmandu')->format('M d, Y') : '—' }}
                            </div>
                            <div style="font-size: 0.75rem; color: #475569; margin-top: 0.2rem;">
                                <strong>Location:</strong> {{ $session->clock_in_location_name ?? 'Laijau Showroom' }}
                            </div>
                            @if($session->clock_in_latitude && $session->clock_in_longitude)
                                <div style="font-size: 0.75rem; margin-top: 0.2rem; display: flex; align-items: center; justify-content: space-between;">
                                    <span>
                                        <strong>GPS:</strong> {{ number_format($session->clock_in_latitude, 5) }}, {{ number_format($session->clock_in_longitude, 5) }}
                                        @if($session->clock_in_accuracy)
                                            <span style="color: #64748b;">(&plusmn;{{ round($session->clock_in_accuracy) }}m)</span>
                                        @endif
                                    </span>
                                    <a href="https://www.google.com/maps?q={{ $session->clock_in_latitude }},{{ $session->clock_in_longitude }}" target="_blank" rel="noopener" class="lj-gps-link">
                                        Map &nearr;
                                    </a>
                                </div>
                            @endif
                            @if($session->clock_in_device_info)
                                <div style="font-size: 0.6875rem; color: #94a3b8; margin-top: 0.35rem; word-break: break-all;" title="{{ $session->clock_in_device_info }}">
                                    Device: {{ Str::limit($session->clock_in_device_info, 55) }}
                                </div>
                            @endif
                        </div>

                        <!-- Clock Out Pane -->
                        <div class="lj-punch-pane">
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="font-weight: 700; font-size: 0.8125rem; color: #b91c1c; text-transform: uppercase;">
                                    &uarr; Clock Out
                                </span>
                                <span style="font-weight: 700; font-size: 0.95rem;">
                                    @if($isOpen)
                                        <span style="color: #d97706; font-style: italic;">In Progress</span>
                                    @else
                                        {{ $session->clock_out_at ? $session->clock_out_at->timezone('Asia/Kathmandu')->format('h:i:s A') : '—' }}
                                    @endif
                                </span>
                            </div>
                            <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.2rem;">
                                Date: {{ $session->clock_out_at ? $session->clock_out_at->timezone('Asia/Kathmandu')->format('M d, Y') : ($isOpen ? 'In Progress' : '—') }}
                            </div>
                            <div style="font-size: 0.75rem; color: #475569; margin-top: 0.2rem;">
                                <strong>Location:</strong> {{ $session->clock_out_location_name ?? ($isOpen ? 'In Progress' : 'Laijau Showroom') }}
                            </div>
                            @if($session->clock_out_latitude && $session->clock_out_longitude)
                                <div style="font-size: 0.75rem; margin-top: 0.2rem; display: flex; align-items: center; justify-content: space-between;">
                                    <span>
                                        <strong>GPS:</strong> {{ number_format($session->clock_out_latitude, 5) }}, {{ number_format($session->clock_out_longitude, 5) }}
                                        @if($session->clock_out_accuracy)
                                            <span style="color: #64748b;">(&plusmn;{{ round($session->clock_out_accuracy) }}m)</span>
                                        @endif
                                    </span>
                                    <a href="https://www.google.com/maps?q={{ $session->clock_out_latitude }},{{ $session->clock_out_longitude }}" target="_blank" rel="noopener" class="lj-gps-link">
                                        Map &nearr;
                                    </a>
                                </div>
                            @endif
                            @if($session->clock_out_device_info)
                                <div style="font-size: 0.6875rem; color: #94a3b8; margin-top: 0.35rem; word-break: break-all;" title="{{ $session->clock_out_device_info }}">
                                    Device: {{ Str::limit($session->clock_out_device_info, 55) }}
                                </div>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @elseif(!empty($rawSessions))
        <!-- Render fallback raw JSON sessions if present -->
        <div style="display: flex; flex-direction: column; gap: 0.75rem;">
            @foreach($rawSessions as $idx => $s)
                <div class="lj-session-card">
                    <div class="lj-session-header">
                        <span style="font-weight: 700;">Session #{{ $s['session_number'] ?? ($idx + 1) }}</span>
                        <span style="color: #2563eb; font-weight: 700;">{{ $s['duration_formatted'] ?? '—' }}</span>
                    </div>
                    <div class="lj-session-body">
                        <div class="lj-punch-pane">
                            <span style="font-weight: 700; color: #15803d;">Clock In: {{ $s['clock_in_time'] ?? '—' }}</span>
                            <span style="font-size: 0.75rem; color: #64748b;">{{ $s['clock_in_at'] ?? '—' }}</span>
                            @if(!empty($s['clock_in_latitude']) && !empty($s['clock_in_longitude']))
                                <a href="https://www.google.com/maps?q={{ $s['clock_in_latitude'] }},{{ $s['clock_in_longitude'] }}" target="_blank" class="lj-gps-link">
                                    GPS: {{ $s['clock_in_latitude'] }}, {{ $s['clock_in_longitude'] }} &nearr;
                                </a>
                            @endif
                        </div>
                        <div class="lj-punch-pane">
                            <span style="font-weight: 700; color: #b91c1c;">Clock Out: {{ $s['clock_out_time'] ?? '—' }}</span>
                            <span style="font-size: 0.75rem; color: #64748b;">{{ $s['clock_out_at'] ?? '—' }}</span>
                            @if(!empty($s['clock_out_latitude']) && !empty($s['clock_out_longitude']))
                                <a href="https://www.google.com/maps?q={{ $s['clock_out_latitude'] }},{{ $s['clock_out_longitude'] }}" target="_blank" class="lj-gps-link">
                                    GPS: {{ $s['clock_out_latitude'] }}, {{ $s['clock_out_longitude'] }} &nearr;
                                </a>
                            @endif
                        </div>
                    </div>
                </div>
            @endforeach
        </div>
    @else
        <!-- No multi-session records found; show legacy single-session details -->
        <div class="lj-card-box" style="text-align: center; padding: 2rem;">
            <p style="margin: 0 0 0.5rem 0; font-weight: 600; color: #64748b;">
                Single Legacy Attendance Record
            </p>
            <div style="display: inline-flex; gap: 2rem; text-align: left; margin-top: 0.5rem;">
                <div>
                    <strong>Clock In:</strong> {{ $record->clock_in ? \Carbon\Carbon::parse($record->clock_in)->format('h:i A') : '—' }}
                    @if($record->clock_in_latitude && $record->clock_in_longitude)
                        <br><a href="https://www.google.com/maps?q={{ $record->clock_in_latitude }},{{ $record->clock_in_longitude }}" target="_blank" class="lj-gps-link">GPS: {{ $record->clock_in_latitude }}, {{ $record->clock_in_longitude }} &nearr;</a>
                    @endif
                </div>
                <div>
                    <strong>Clock Out:</strong> {{ $record->clock_out ? \Carbon\Carbon::parse($record->clock_out)->format('h:i A') : '—' }}
                    @if($record->clock_out_latitude && $record->clock_out_longitude)
                        <br><a href="https://www.google.com/maps?q={{ $record->clock_out_latitude }},{{ $record->clock_out_longitude }}" target="_blank" class="lj-gps-link">GPS: {{ $record->clock_out_latitude }}, {{ $record->clock_out_longitude }} &nearr;</a>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
