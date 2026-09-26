@php
    /** @var array $summary */
    $emp = $summary['employee'] ?? [];
    $metrics = $summary['metrics'] ?? [];
    $timesheets = $summary['timesheets'] ?? collect();
    $sessions = $summary['sessions'] ?? collect();
@endphp

<div class="lj-emp-summary-wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; flex-direction: column; gap: 1.25rem;">
    <style>
        .lj-emp-summary-wrap {
            font-size: 0.875rem;
            color: #1e293b;
        }
        .dark .lj-emp-summary-wrap {
            color: #f1f5f9;
        }
        .lj-summary-hero {
            background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%);
            border: 1px solid #dbeafe;
            border-radius: 0.75rem;
            padding: 1.25rem;
        }
        .dark .lj-summary-hero {
            background: linear-gradient(135deg, #1e293b 0%, #172554 100%);
            border-color: #1e3a8a;
        }
        .lj-kpi-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(130px, 1fr));
            gap: 0.75rem;
        }
        .lj-kpi-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 0.75rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .dark .lj-kpi-card {
            background: #0f172a;
            border-color: #334155;
        }
        .lj-kpi-label {
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            color: #64748b;
            letter-spacing: 0.03em;
        }
        .dark .lj-kpi-label {
            color: #94a3b8;
        }
        .lj-kpi-val {
            font-size: 1.25rem;
            font-weight: 800;
            color: #0f172a;
        }
        .dark .lj-kpi-val {
            color: #f8fafc;
        }
        .lj-summary-table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.8125rem;
        }
        .lj-summary-table th {
            text-align: left;
            padding: 0.6rem 0.75rem;
            background: #f1f5f9;
            color: #475569;
            font-weight: 700;
            border-bottom: 1px solid #e2e8f0;
            font-size: 0.75rem;
            text-transform: uppercase;
        }
        .dark .lj-summary-table th {
            background: #1e293b;
            color: #cbd5e1;
            border-color: #334155;
        }
        .lj-summary-table td {
            padding: 0.6rem 0.75rem;
            border-bottom: 1px solid #f1f5f9;
            color: inherit;
        }
        .dark .lj-summary-table td {
            border-color: #1e293b;
        }
    </style>

    <!-- Top Profile & Period Banner -->
    <div class="lj-summary-hero">
        <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem;">
            <div>
                <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800;">
                    {{ $emp['name'] ?? 'Employee Summary' }}
                </h3>
                <div style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                    Emp #: <span style="font-weight: 600;">{{ $emp['code'] ?? '—' }}</span> &bull; 
                    Dept: <span style="font-weight: 600;">{{ $emp['department'] ?? '—' }}</span> &bull; 
                    Role: <span style="font-weight: 600;">{{ $emp['position'] ?? '—' }}</span>
                </div>
            </div>
            <div style="text-align: right;">
                <span style="display: inline-block; background: #2563eb; color: #ffffff; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;">
                    {{ ucfirst($summary['period'] ?? 'monthly') }} Period
                </span>
                <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;">
                    {{ $summary['formatted_period'] ?? '' }}
                </div>
            </div>
        </div>

        <div class="lj-kpi-grid" style="margin-top: 1rem;">
            <div class="lj-kpi-card">
                <span class="lj-kpi-label">Total Worked Hours</span>
                <span class="lj-kpi-val" style="color: #2563eb;">{{ $metrics['total_worked_formatted'] ?? '0h 0m' }}</span>
                <span style="font-size: 0.6875rem; color: #64748b;">{{ number_format((float)($metrics['total_worked_hours'] ?? 0), 2) }} total hrs</span>
            </div>
            <div class="lj-kpi-card">
                <span class="lj-kpi-label">Total Sessions</span>
                <span class="lj-kpi-val">{{ $metrics['total_sessions'] ?? 0 }}</span>
                <span style="font-size: 0.6875rem; color: #64748b;">{{ $metrics['completed_sessions'] ?? 0 }} completed</span>
            </div>
            <div class="lj-kpi-card">
                <span class="lj-kpi-label">Days Present</span>
                <span class="lj-kpi-val" style="color: #16a34a;">{{ $metrics['days_present'] ?? 0 }}</span>
                <span style="font-size: 0.6875rem; color: #64748b;">{{ $metrics['days_half_day'] ?? 0 }} half-day</span>
            </div>
            <div class="lj-kpi-card">
                <span class="lj-kpi-label">Avg Hours / Day</span>
                <span class="lj-kpi-val">{{ number_format((float)($metrics['average_hours_per_day'] ?? 0), 2) }}h</span>
                <span style="font-size: 0.6875rem; color: #64748b;">standard: 8.00h</span>
            </div>
            <div class="lj-kpi-card">
                <span class="lj-kpi-label">Overtime Hours</span>
                <span class="lj-kpi-val" style="color: {{ ($metrics['overtime_hours'] ?? 0) > 0 ? '#d97706' : '#64748b' }};">
                    {{ number_format((float)($metrics['overtime_hours'] ?? 0), 2) }}h
                </span>
                <span style="font-size: 0.6875rem; color: #64748b;">Regular: {{ number_format((float)($metrics['regular_hours'] ?? 0), 2) }}h</span>
            </div>
            <div class="lj-kpi-card">
                <span class="lj-kpi-label">Late Arrival</span>
                <span class="lj-kpi-val" style="color: {{ ($metrics['total_late_minutes'] ?? 0) > 0 ? '#dc2626' : '#16a34a' }};">
                    {{ $metrics['total_late_minutes'] ?? 0 }}m
                </span>
                <span style="font-size: 0.6875rem; color: #64748b;">Total minutes</span>
            </div>
        </div>
    </div>

    <!-- Daily Timesheets Breakdown in Selected Period -->
    <div>
        <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 700;">
            Daily Timesheet Log in Period
        </h4>

        @if($timesheets->isNotEmpty())
            <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: #ffffff;">
                <table class="lj-summary-table">
                    <thead>
                        <tr>
                            <th>Date</th>
                            <th>Sessions</th>
                            <th>Clock In</th>
                            <th>Clock Out</th>
                            <th>Worked Hours</th>
                            <th>Regular / OT</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($timesheets as $ts)
                            <tr>
                                <td style="font-weight: 600;">
                                    {{ $ts->date?->format('M d, Y (D)') }}
                                </td>
                                <td>
                                    <span style="display: inline-block; padding: 0.15rem 0.5rem; background: #f1f5f9; border-radius: 9999px; font-weight: 700; font-size: 0.6875rem;">
                                        {{ $ts->total_sessions ?: 1 }} {{ Str::plural('session', $ts->total_sessions ?: 1) }}
                                    </span>
                                </td>
                                <td>{{ $ts->clock_in ? \Carbon\Carbon::parse($ts->clock_in)->format('h:i A') : '—' }}</td>
                                <td>{{ $ts->clock_out ? \Carbon\Carbon::parse($ts->clock_out)->format('h:i A') : 'Active' }}</td>
                                <td style="font-weight: 700; color: #2563eb;">
                                    @php
                                        $wh = (float)($ts->total_worked_hours ?: $ts->regular_hours);
                                        $wm = (int)($ts->total_worked_minutes ?: ($wh * 60));
                                    @endphp
                                    {{ floor($wm / 60) }}h {{ $wm % 60 }}m
                                </td>
                                <td>
                                    {{ number_format((float)$ts->regular_hours, 1) }}h
                                    @if((float)$ts->overtime_hours > 0)
                                        <span style="color: #d97706; font-weight: 600;">+{{ number_format((float)$ts->overtime_hours, 1) }} OT</span>
                                    @endif
                                </td>
                                <td>
                                    <span style="text-transform: capitalize; font-weight: 600; color: {{ $ts->attendance_status === 'present' ? '#16a34a' : ($ts->attendance_status === 'half_day' ? '#d97706' : '#64748b') }};">
                                        {{ str_replace('_', ' ', $ts->attendance_status) }}
                                    </span>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @else
            <div style="padding: 1.5rem; text-align: center; border: 1px dashed #cbd5e1; border-radius: 0.5rem; color: #64748b;">
                No attendance records logged for this employee within this period.
            </div>
        @endif
    </div>
</div>
