<x-filament-panels::page>
    <link rel="stylesheet" href="{{ asset('css/nepal-accounting.css') }}?v=4.0">
    <style>
        .hrm-metric-icon {
            width: 44px;
            height: 44px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 20px;
            background: rgba(10, 46, 35, 0.06);
            color: #0A2E23;
        }

        .dark .hrm-metric-icon {
            background: rgba(197, 160, 89, 0.15);
            color: #C5A059;
        }

        .hrm-quick-btn {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 12px 18px;
            background: #FFFFFF;
            border: 1px solid #E5E7EB;
            border-radius: 10px;
            font-size: 13px;
            font-weight: 600;
            color: #1F2937;
            text-decoration: none;
            transition: all 0.2s ease;
        }

        .dark .hrm-quick-btn {
            background: #181B20;
            border-color: #2D333B;
            color: #F3F4F6;
        }

        .hrm-quick-btn:hover {
            border-color: #C5A059;
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.06);
        }

        .hrm-payroll-bar {
            display: flex;
            height: 18px;
            border-radius: 9px;
            overflow: hidden;
            margin: 16px 0 10px;
            background: #E5E7EB;
        }

        .hrm-payroll-segment {
            height: 100%;
            transition: width 0.3s ease;
        }

        .hrm-legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 12px;
        }

        .hrm-legend-dot {
            width: 10px;
            height: 10px;
            border-radius: 50%;
        }

        .hrm-avatar {
            width: 36px;
            height: 36px;
            border-radius: 50%;
            background: #0A2E23;
            color: #FFFFFF;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 700;
            font-size: 13px;
        }
    </style>

    {{-- HEADER BANNER (Clean Minimalist Light Mode) --}}
    <div class="na-header-banner">
        <div style="display: flex; justify-content: space-between; align-items: center; flex-wrap: wrap; gap: 16px; width: 100%;">
            <div>
                <div style="display: flex; align-items: center; gap: 12px;">
                    <div style="width: 44px; height: 44px; border-radius: 10px; background: #f1f5f9; display: flex; align-items: center; justify-content: center; font-size: 22px; border: 1px solid #e2e8f0;">
                        👥
                    </div>
                    <div>
                        <h1 class="na-title-main" style="margin: 0;">
                            Human Resource & Workforce Management
                        </h1>
                        <p class="na-subtitle">
                            Workforce administration, attendance shifts, statutory leave provisions & monthly payroll.
                        </p>
                    </div>
                </div>
            </div>

            <div style="display: flex; align-items: center; gap: 10px; flex-wrap: wrap;">
                <span class="na-badge" style="background: #ecfdf5; color: #065f46; border: 1px solid #a7f3d0; font-size: 12px; padding: 6px 14px;">
                    <span class="na-badge-dot" style="background: #10B981;"></span> Workforce Active
                </span>
                <span class="na-badge" style="background: #f1f5f9; color: #334155; border: 1px solid #e2e8f0; font-size: 12px; padding: 6px 14px;">
                    Statutory Leave Tracking
                </span>
            </div>
        </div>
    </div>

    {{-- QUICK ACTION BAR --}}
    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; margin-bottom: 24px;">
        <a href="{{ route('filament.admin.resources.employees.create') }}" class="hrm-quick-btn">
            <span style="font-size: 18px;">➕</span>
            <span>Hire New Employee</span>
        </a>
        <a href="{{ route('filament.admin.resources.payroll-runs.create') }}" class="hrm-quick-btn">
            <span style="font-size: 18px;">⚡</span>
            <span>Run Monthly Payroll</span>
        </a>
        <a href="{{ route('filament.admin.resources.timesheets.create') }}" class="hrm-quick-btn">
            <span style="font-size: 18px;">⏱️</span>
            <span>Record Work Shift</span>
        </a>
        <a href="{{ route('filament.admin.resources.leave-requests.create') }}" class="hrm-quick-btn">
            <span style="font-size: 18px;">🏖️</span>
            <span>Request Holiday / Leave</span>
        </a>
        <a href="{{ route('filament.admin.resources.expense-claims.create') }}" class="hrm-quick-btn">
            <span style="font-size: 18px;">🧾</span>
            <span>Submit Expense Claim</span>
        </a>
    </div>

    {{-- TOP KPI METRICS (6 TILES) --}}
    <div class="na-grid-3" style="margin-bottom: 24px;">
        {{-- Card 1: Active Staff --}}
        <div class="na-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span style="font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px;">Active Workforce</span>
                    <div style="font-family: 'Cinzel', serif; font-size: 28px; font-weight: 700; color: #0A2E23; margin-top: 6px;">
                        {{ $kpis['active_employees'] }} <span style="font-size: 14px; font-family: sans-serif; font-weight: 500; color: #6B7280;">Staff ({{ $kpis['total_fte'] }} FTE)</span>
                    </div>
                </div>
                <div class="hrm-metric-icon">👔</div>
            </div>
            <div style="margin-top: 12px; font-size: 12px; color: #6B7280; display: flex; gap: 12px;">
                <span>🇳🇵 PAN & Citizenship Verified</span>
                <span>• {{ count($departments) }} Departments</span>
            </div>
        </div>

        {{-- Card 2: Monthly Gross Payroll --}}
        <div class="na-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span style="font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px;">Est. Monthly Salary Roll</span>
                    <div style="font-family: 'Space Mono', monospace; font-size: 24px; font-weight: 700; color: #0A2E23; margin-top: 6px;">
                        Rs. {{ number_format($kpis['monthly_gross_salary_cost_npr'] ?? $kpis['monthly_gross_salary_cost_npr'], 2, '.', ',') }}
                    </div>
                </div>
                <div class="hrm-metric-icon">💰</div>
            </div>
            <div style="margin-top: 12px; font-size: 12px; color: #6B7280;">
                Includes contractual salaries & hourly rates
            </div>
        </div>

        {{-- Card 3: Pending Workflow Approvals --}}
        <div class="na-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: flex-start;">
                <div>
                    <span style="font-size: 12px; font-weight: 600; color: #6B7280; text-transform: uppercase; letter-spacing: 0.5px;">Pending HR Approvals</span>
                    <div style="font-family: 'Cinzel', serif; font-size: 28px; font-weight: 700; color: #D97706; margin-top: 6px;">
                        {{ $kpis['pending_leaves_count'] + $kpis['pending_timesheets_count'] + $kpis['pending_expenses_count'] }} <span style="font-size: 14px; font-family: sans-serif; font-weight: 500; color: #6B7280;">Tasks</span>
                    </div>
                </div>
                <div class="hrm-metric-icon" style="color: #D97706; background: rgba(217, 119, 6, 0.1);">⏳</div>
            </div>
            <div style="margin-top: 12px; font-size: 12px; color: #6B7280; display: flex; gap: 8px; flex-wrap: wrap;">
                <span class="na-badge">{{ $kpis['pending_leaves_count'] }} Leaves</span>
                <span class="na-badge">{{ $kpis['pending_timesheets_count'] }} Shifts</span>
                <span class="na-badge">{{ $kpis['pending_expenses_count'] }} Expenses</span>
            </div>
        </div>
    </div>

    {{-- MAIN SECTION: 2 COLUMNS --}}
    <div class="na-grid-2-1" style="margin-bottom: 24px;">
        {{-- LEFT COLUMN: LATEST PAYROLL RUN BREAKDOWN --}}
        <div class="na-card" style="padding: 24px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                <div>
                    <h3 style="font-family: 'Cinzel', serif; font-size: 18px; font-weight: 700; color: #0A2E23; margin: 0;">
                        Statutory Nepal Payroll Engine (हालको तलब भुक्तानी)
                    </h3>
                    <p style="font-size: 12px; color: #6B7280; margin: 4px 0 0 0;">
                        @if($latestPayroll)
                        Run: <strong>{{ $latestPayroll->run_number }}</strong> ({{ $latestPayroll->name }}) • Pay Date: {{ $latestPayroll->pay_date->format('d/m/Y') }}
                        @else
                        No payroll run executed yet for current month.
                        @endif
                    </p>
                </div>
                @if($latestPayroll)
                <span class="na-badge" style="background: rgba(16, 185, 129, 0.1); color: #10B981; font-weight: 600;">
                    {{ strtoupper($latestPayroll->status) }}
                </span>
                @endif
            </div>

            @if($latestPayroll)
            {{-- Horizontal Proportional Bar --}}
            @php
            $gross = $latestPayroll->total_gross_salary_npr > 0 ? $latestPayroll->total_gross_salary_npr : 1;
            $ssfPct = round((($latestPayroll->total_ssf_employee_npr ?? 0) / $gross) * 100, 1);
            $tdsPct = round((($latestPayroll->total_tds_tax_npr ?? 0) / $gross) * 100, 1);
            $netPct = round((($latestPayroll->total_net_payout_npr ?? 0) / $gross) * 100, 1);
            @endphp
            <div class="hrm-payroll-bar">
                <div class="hrm-payroll-segment" style="width: {{ $netPct }}%; background: #10B981;" title="Net Payout ({{ $netPct }}%)"></div>
                <div class="hrm-payroll-segment" style="width: {{ $ssfPct }}%; background: #6366F1;" title="Statutory SSF ({{ $ssfPct }}%)"></div>
                <div class="hrm-payroll-segment" style="width: {{ $tdsPct }}%; background: #EF4444;" title="TDS ({{ $tdsPct }}%)"></div>
            </div>

            <div style="display: flex; justify-content: space-between; flex-wrap: wrap; gap: 12px; margin-bottom: 20px;">
                <div class="hrm-legend-item">
                    <span class="hrm-legend-dot" style="background: #10B981;"></span>
                    <span>Net Payout: <strong>Rs. {{ number_format($latestPayroll->total_net_payout_npr, 2, '.', ',') }}</strong></span>
                </div>
                <div class="hrm-legend-item">
                    <span class="hrm-legend-dot" style="background: #6366F1;"></span>
                    <span>Statutory SSF (PF/CIT): <strong>Rs. {{ number_format($latestPayroll->total_ssf_employee_npr ?? 0, 2, '.', ',') }}</strong></span>
                </div>
                <div class="hrm-legend-item">
                    <span class="hrm-legend-dot" style="background: #EF4444;"></span>
                    <span>TDS (IRD): <strong>Rs. {{ number_format($latestPayroll->total_tds_tax_npr ?? 0, 2, '.', ',') }}</strong></span>
                </div>
            </div>

            {{-- Detailed Waterfall Calculation Box --}}
            <div class="na-calc-box">
                <div class="na-waterfall-line">
                    <span>1. Total Gross Salary (Basic & Allowances)</span>
                    <span style="font-family: 'Space Mono', monospace; font-weight: 700;">Rs. {{ number_format($latestPayroll->total_gross_salary_npr, 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line">
                    <span>2. Statutory Social Security (SSF Employee Contribution)</span>
                    <span style="font-family: 'Space Mono', monospace; color: #DC2626;">- Rs. {{ number_format($latestPayroll->total_ssf_employee_npr ?? 0, 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line">
                    <span>3. Statutory TDS Withholding (Inland Revenue Department)</span>
                    <span style="font-family: 'Space Mono', monospace; color: #DC2626;">- Rs. {{ number_format($latestPayroll->total_tds_tax_npr ?? 0, 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line">
                    <span>4. Employee Provident / Retirement Contribution</span>
                    <span style="font-family: 'Space Mono', monospace; color: #DC2626;">- Rs. {{ number_format($latestPayroll->total_provident_employee_npr ?? 0, 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line" style="background: rgba(16, 185, 129, 0.08); font-weight: 700;">
                    <span>➡️ Total Net Disbursed (Bank Transfer Payout)</span>
                    <span style="font-family: 'Space Mono', monospace; color: #059669; font-size: 15px;">Rs. {{ number_format($latestPayroll->total_net_payout_npr, 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line" style="margin-top: 8px; border-top: 1px dashed #E5E7EB;">
                    <span>+ Employer Statutory & Social Security Contribution</span>
                    <span style="font-family: 'Space Mono', monospace;">+ Rs. {{ number_format(($latestPayroll->total_pension_employer_npr ?? 0) + ($latestPayroll->total_provident_employer_npr ?? 0), 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line">
                    <span>+ Leave & Gratuity Accrual</span>
                    <span style="font-family: 'Space Mono', monospace;">+ Rs. {{ number_format($latestPayroll->total_leave_liability_npr ?? 0, 2, '.', ',') }}</span>
                </div>
                <div class="na-waterfall-line na-waterfall-total" style="background: rgba(10, 46, 35, 0.05);">
                    <span>💼 Total Employer Cost</span>
                    <span style="font-family: 'Space Mono', monospace; color: #0A2E23; font-size: 16px;">Rs. {{ number_format($latestPayroll->total_employer_cost_npr, 2, '.', ',') }}</span>
                </div>
            </div>
            @else
            <div style="text-align: center; padding: 40px 20px; color: #9CA3AF;">
                <span style="font-size: 32px;">📑</span>
                <p style="margin: 8px 0 0 0; font-size: 14px;">No payroll runs currently available.</p>
                <a href="{{ route('filament.admin.resources.payroll-runs.create') }}" class="na-btn na-btn-primary" style="margin-top: 14px; display: inline-block;">
                    Initialize First Payroll Run
                </a>
            </div>
            @endif
        </div>

        {{-- RIGHT COLUMN: DEPARTMENT STAFFING MATRIX & STATUTORY COMPLIANCE --}}
        <div style="display: flex; flex-direction: column; gap: 20px;">
            {{-- Department Structure Card --}}
            <div class="na-card" style="padding: 20px;">
                <h3 style="font-family: 'Cinzel', serif; font-size: 16px; font-weight: 700; color: #0A2E23; margin: 0 0 14px 0;">
                    Department Staffing (विभागहरू)
                </h3>
                <div style="display: flex; flex-direction: column; gap: 10px;">
                    @forelse($departments as $dept)
                    <div style="display: flex; justify-content: space-between; align-items: center; padding: 8px 12px; background: rgba(0,0,0,0.02); border-radius: 8px;">
                        <div>
                            <div style="font-weight: 600; font-size: 13px; color: #1F2937;">{{ $dept->name }}</div>
                            <div style="font-size: 11px; color: #6B7280;">{{ $dept->location ?? 'Headquarters' }}</div>
                        </div>
                        <span class="na-badge" style="font-family: 'Space Mono', monospace;">
                            {{ $dept->employees_count }} Staff
                        </span>
                    </div>
                    @empty
                    <p style="font-size: 12px; color: #9CA3AF;">No active departments.</p>
                    @endforelse
                </div>
            </div>

            {{-- Nepal Legal Framework Compliance Card --}}
            <div class="na-card" style="padding: 20px; border-left: 4px solid #C5A059;">
                <h4 style="font-size: 13px; font-weight: 700; color: #0A2E23; margin: 0 0 8px 0;">
                    ⚖️ Nepal Labour & IRD Compliance
                </h4>
                <ul style="font-size: 12px; color: #4B5563; padding-left: 16px; margin: 0; line-height: 1.6;">
                    <li><strong>Nepal Labour Act (2074):</strong> Statutory annual, festival, and sick leave entitlements.</li>
                    <li><strong>Statutory Deductions:</strong> Provident Fund, CIT, and Social Security deductions.</li>
                    <li><strong>TDS Taxation:</strong> Inland Revenue Department progressive salary tax brackets.</li>
                    <li><strong>Bank Transfers:</strong> Direct commercial bank clearing in Nepalese Rupees (NPR).</li>
                </ul>
            </div>
        </div>
    </div>

    {{-- BOTTOM TABLES: PENDING APPROVAL QueueS --}}
    <div class="na-grid-2" style="margin-bottom: 24px;">
        {{-- Pending Leaves --}}
        <div class="na-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="font-family: 'Cinzel', serif; font-size: 16px; font-weight: 700; color: #0A2E23; margin: 0;">
                    Leave & Absence Requests (बिदा निवेदन)
                </h3>
                <a href="{{ route('filament.admin.resources.leave-requests.index') }}" style="font-size: 12px; color: #C5A059; font-weight: 600; text-decoration: none;">View All &rarr;</a>
            </div>

            @if($pendingLeaves->count() > 0)
            <div class="na-table-container">
                <table class="na-table">
                    <thead>
                        <tr>
                            <th>Employee</th>
                            <th>Type</th>
                            <th>Dates</th>
                            <th>Days</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingLeaves as $leave)
                        <tr>
                            <td style="font-weight: 600;">{{ $leave->employee?->full_name ?? 'Employee' }}</td>
                            <td>
                                <span class="na-badge">{{ $leave->leave_type }}</span>
                            </td>
                            <td style="font-size: 12px;">{{ $leave->start_date->format('d/m') }} - {{ $leave->end_date->format('d/m') }}</td>
                            <td style="font-weight: bold;">{{ $leave->days_count }}</td>
                            <td>
                                <span class="na-badge" style="background: rgba(217, 119, 6, 0.1); color: #D97706;">Pending</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p style="font-size: 13px; color: #9CA3AF; text-align: center; padding: 20px 0;">No pending leave requests. Everything up to date! ✨</p>
            @endif
        </div>

        {{-- Pending Expense Claims --}}
        <div class="na-card" style="padding: 20px;">
            <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 14px;">
                <h3 style="font-family: 'Cinzel', serif; font-size: 16px; font-weight: 700; color: #0A2E23; margin: 0;">
                    Pending Expense Claims (Udlæg til refusion)
                </h3>
                <a href="{{ route('filament.admin.resources.expense-claims.index') }}" style="font-size: 12px; color: #C5A059; font-weight: 600; text-decoration: none;">View All &rarr;</a>
            </div>

            @if($pendingExpenses->count() > 0)
            <div class="na-table-container">
                <table class="na-table">
                    <thead>
                        <tr>
                            <th>Claim #</th>
                            <th>Employee</th>
                            <th>Title</th>
                            <th>Gross (NPR)</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($pendingExpenses as $exp)
                        <tr>
                            <td style="font-family: 'Space Mono', monospace; font-weight: 700;">{{ $exp->claim_number }}</td>
                            <td>{{ $exp->employee?->full_name ?? 'Employee' }}</td>
                            <td style="font-size: 12px;">{{ Str::limit($exp->title, 25) }}</td>
                            <td style="font-family: 'Space Mono', monospace; font-weight: 700;">Rs. {{ number_format($exp->gross_amount_npr, 2, '.', ',') }}</td>
                            <td>
                                <span class="na-badge" style="background: rgba(217, 119, 6, 0.1); color: #D97706;">Submitted</span>
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
            @else
            <p style="font-size: 13px; color: #9CA3AF; text-align: center; padding: 20px 0;">No outstanding expense claims requiring reimbursement. ✨</p>
            @endif
        </div>
    </div>
</x-filament-panels::page>