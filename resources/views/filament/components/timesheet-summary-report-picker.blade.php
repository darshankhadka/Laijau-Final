@php
    $employees = $employees ?? \App\Models\Hrm\Employee::where('status', 'active')->orderBy('first_name')->get();
@endphp

<div x-data="{
    employeeId: '{{ $employees->first()?->id ?? '' }}',
    period: 'monthly',
    startDate: '',
    endDate: '',
    loading: false,
    summary: null,
    error: null,

    async loadSummary() {
        if (!this.employeeId) return;
        this.loading = true;
        this.error = null;
        try {
            const url = new URL('{{ route('admin.attendance.employee_summary_data') }}', window.location.origin);
            url.searchParams.set('employee_id', this.employeeId);
            url.searchParams.set('period', this.period);
            if (this.period === 'custom') {
                if (this.startDate) url.searchParams.set('start_date', this.startDate);
                if (this.endDate) url.searchParams.set('end_date', this.endDate);
            }
            const res = await fetch(url.toString(), {
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            });
            if (!res.ok) {
                throw new Error('Failed to load employee attendance summary.');
            }
            this.summary = await res.json();
        } catch (err) {
            this.error = err.message || 'Error loading report';
        } finally {
            this.loading = false;
        }
    },

    init() {
        if (this.employeeId) {
            this.loadSummary();
        }
    }
}" class="lj-summary-picker-wrap" style="font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; display: flex; flex-direction: column; gap: 1.25rem;">

    <style>
        .lj-control-bar {
            display: grid;
            grid-template-columns: 2fr 1fr auto;
            gap: 0.75rem;
            align-items: end;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 1rem;
        }
        .dark .lj-control-bar {
            background: #1e293b;
            border-color: #334155;
        }
        @media (max-width: 768px) {
            .lj-control-bar {
                grid-template-columns: 1fr;
            }
        }
        .lj-input-field {
            width: 100%;
            padding: 0.5rem 0.75rem;
            border: 1px solid #cbd5e1;
            border-radius: 0.5rem;
            font-size: 0.875rem;
            background: #ffffff;
            color: #1e293b;
        }
        .dark .lj-input-field {
            background: #0f172a;
            border-color: #475569;
            color: #f8fafc;
        }
    </style>

    <!-- Controls to Pick Employee & Period -->
    <div class="lj-control-bar">
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 0.25rem;">
                Select Employee
            </label>
            <select x-model="employeeId" @change="loadSummary()" class="lj-input-field">
                @foreach($employees as $emp)
                    <option value="{{ $emp->id }}">
                        {{ $emp->full_name }} {{ $emp->employee_number ? "({$emp->employee_number})" : '' }}
                    </option>
                @endforeach
            </select>
        </div>

        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #64748b; margin-bottom: 0.25rem;">
                Aggregation Period
            </label>
            <select x-model="period" @change="loadSummary()" class="lj-input-field">
                <option value="daily">Daily (Today)</option>
                <option value="weekly">Weekly (This Week)</option>
                <option value="monthly">Monthly (This Month)</option>
                <option value="custom">Custom Date Range</option>
            </select>
        </div>

        <div>
            <button type="button" @click="loadSummary()" :disabled="loading" style="padding: 0.5rem 1.25rem; background: #2563eb; color: #ffffff; font-weight: 700; font-size: 0.875rem; border-radius: 0.5rem; border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 0.5rem;">
                <span x-show="loading" style="display: inline-block; width: 14px; height: 14px; border: 2px solid #fff; border-top-color: transparent; border-radius: 50%; animation: spin 1s linear infinite;"></span>
                <span x-text="loading ? 'Loading...' : 'Generate Report'"></span>
            </button>
        </div>
    </div>

    <!-- Custom Date Range Row -->
    <div x-show="period === 'custom'" style="display: grid; grid-template-columns: 1fr 1fr; gap: 0.75rem; background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 0.5rem; padding: 0.75rem;">
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #1e40af; margin-bottom: 0.25rem;">
                From Date
            </label>
            <input type="date" x-model="startDate" @change="loadSummary()" class="lj-input-field">
        </div>
        <div>
            <label style="display: block; font-size: 0.75rem; font-weight: 700; text-transform: uppercase; color: #1e40af; margin-bottom: 0.25rem;">
                Until Date
            </label>
            <input type="date" x-model="endDate" @change="loadSummary()" class="lj-input-field">
        </div>
    </div>

    <!-- Error Banner -->
    <div x-show="error" style="padding: 0.75rem; background: #fee2e2; border: 1px solid #fecaca; border-radius: 0.5rem; color: #b91c1c; font-size: 0.875rem;" x-text="error"></div>

    <!-- Report Container -->
    <div x-show="summary && !loading" style="display: flex; flex-direction: column; gap: 1.25rem;">
        <!-- Employee Hero -->
        <div style="background: linear-gradient(135deg, #f8fafc 0%, #eff6ff 100%); border: 1px solid #dbeafe; border-radius: 0.75rem; padding: 1.25rem;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start; flex-wrap: wrap; gap: 0.75rem;">
                <div>
                    <h3 style="margin: 0; font-size: 1.25rem; font-weight: 800; color: #1e293b;" x-text="summary?.employee?.name"></h3>
                    <div style="font-size: 0.8125rem; color: #64748b; margin-top: 0.25rem;">
                        Emp #: <span style="font-weight: 600;" x-text="summary?.employee?.code || '—'"></span> &bull; 
                        Dept: <span style="font-weight: 600;" x-text="summary?.employee?.department || 'Showroom'"></span> &bull; 
                        Role: <span style="font-weight: 600;" x-text="summary?.employee?.position || 'Staff'"></span>
                    </div>
                </div>
                <div style="text-align: right;">
                    <span style="display: inline-block; background: #2563eb; color: #ffffff; padding: 0.25rem 0.75rem; border-radius: 9999px; font-size: 0.75rem; font-weight: 700; text-transform: uppercase;" x-text="(summary?.period || 'monthly') + ' Period'"></span>
                    <div style="font-size: 0.75rem; color: #64748b; margin-top: 0.25rem;" x-text="summary?.formatted_period"></div>
                </div>
            </div>

            <!-- KPI Tiles -->
            <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(130px, 1fr)); gap: 0.75rem; margin-top: 1rem;">
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Total Worked</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #2563eb;" x-text="summary?.metrics?.total_worked_formatted || '0h 0m'"></span>
                    <span style="font-size: 0.6875rem; color: #64748b;" x-text="(summary?.metrics?.total_worked_hours || 0) + ' hrs'"></span>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Total Sessions</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #0f172a;" x-text="summary?.metrics?.total_sessions || 0"></span>
                    <span style="font-size: 0.6875rem; color: #64748b;" x-text="(summary?.metrics?.completed_sessions || 0) + ' completed'"></span>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Days Present</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #16a34a;" x-text="summary?.metrics?.days_present || 0"></span>
                    <span style="font-size: 0.6875rem; color: #64748b;" x-text="(summary?.metrics?.days_half_day || 0) + ' half-day'"></span>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Avg Hours/Day</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #0f172a;" x-text="(summary?.metrics?.average_hours_per_day || 0) + 'h'"></span>
                    <span style="font-size: 0.6875rem; color: #64748b;">Standard: 8.00h</span>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Overtime</span>
                    <span style="font-size: 1.25rem; font-weight: 800; color: #d97706;" x-text="(summary?.metrics?.overtime_hours || 0) + 'h'"></span>
                    <span style="font-size: 0.6875rem; color: #64748b;">Regular: <span x-text="summary?.metrics?.regular_hours || 0"></span>h</span>
                </div>
                <div style="background: #ffffff; border: 1px solid #e2e8f0; border-radius: 0.5rem; padding: 0.75rem; display: flex; flex-direction: column; gap: 0.2rem;">
                    <span style="font-size: 0.6875rem; font-weight: 700; text-transform: uppercase; color: #64748b;">Late Minutes</span>
                    <span style="font-size: 1.25rem; font-weight: 800;" :style="{ color: (summary?.metrics?.total_late_minutes || 0) > 0 ? '#dc2626' : '#16a34a' }" x-text="(summary?.metrics?.total_late_minutes || 0) + 'm'"></span>
                    <span style="font-size: 0.6875rem; color: #64748b;">Total delay</span>
                </div>
            </div>
        </div>

        <!-- Timesheets Table -->
        <div>
            <h4 style="margin: 0 0 0.5rem 0; font-size: 0.95rem; font-weight: 700; color: #1e293b;">
                Daily Attendance Breakdown in Period
            </h4>
            <template x-if="summary?.timesheets?.length > 0">
                <div style="border: 1px solid #e2e8f0; border-radius: 0.5rem; overflow: hidden; background: #ffffff;">
                    <table style="width: 100%; border-collapse: collapse; font-size: 0.8125rem;">
                        <thead>
                            <tr style="background: #f1f5f9; text-transform: uppercase; font-size: 0.75rem; color: #475569;">
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">Date</th>
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">Sessions</th>
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">First In</th>
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">Last Out</th>
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">Worked</th>
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">Reg / OT</th>
                                <th style="text-align: left; padding: 0.6rem 0.75rem;">Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            <template x-for="ts in summary.timesheets" :key="ts.id">
                                <tr style="border-bottom: 1px solid #f1f5f9;">
                                    <td style="padding: 0.6rem 0.75rem; font-weight: 600;" x-text="ts.date"></td>
                                    <td style="padding: 0.6rem 0.75rem;">
                                        <span style="display: inline-block; padding: 0.15rem 0.5rem; background: #f1f5f9; border-radius: 9999px; font-weight: 700; font-size: 0.6875rem;" x-text="(ts.total_sessions || 1) + ' sess'"></span>
                                    </td>
                                    <td style="padding: 0.6rem 0.75rem;" x-text="ts.clock_in || '—'"></td>
                                    <td style="padding: 0.6rem 0.75rem;" x-text="ts.clock_out || 'Active'"></td>
                                    <td style="padding: 0.6rem 0.75rem; font-weight: 700; color: #2563eb;" x-text="(ts.total_worked_hours || ts.regular_hours) + ' hrs'"></td>
                                    <td style="padding: 0.6rem 0.75rem;" x-text="ts.regular_hours + 'h / ' + (ts.overtime_hours || 0) + 'h OT'"></td>
                                    <td style="padding: 0.6rem 0.75rem; text-transform: capitalize; font-weight: 600;" x-text="ts.attendance_status"></td>
                                </tr>
                            </template>
                        </tbody>
                    </table>
                </div>
            </template>
            <template x-if="!summary?.timesheets?.length">
                <div style="padding: 2rem; text-align: center; border: 1px dashed #cbd5e1; border-radius: 0.5rem; color: #64748b;">
                    No attendance records found for this employee in this period.
                </div>
            </template>
        </div>
    </div>
</div>
