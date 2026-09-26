<x-filament-panels::page class="w-full max-w-full">

    @php
        $summary = $this->getAttendanceSummary();
        $todayEvents = $this->getTodayEvents();
        $employees = $this->getEmployeesList();
        $locations = $this->getLocations();
        $auditLogs = $this->getAuditLogs();
        $mapEmployees = $this->getCheckedInEmployeesForMap();
        $defaultLoc = $locations->where('is_default', true)->first() ?? $locations->first();
    @endphp

    <style>
        /* =====================================================================
           LAIJAU ATTENDANCE CONTROL CENTER — MASTER STYLESHEET
           ===================================================================== */
        .att-adm-wrap {
            width: 100%;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            color: #0f172a;
            box-sizing: border-box;
        }
        .dark .att-adm-wrap {
            color: #f8fafc;
        }

        /* Hero Banner */
        .att-adm-hero {
            background: linear-gradient(135deg, #001b48 0%, #002566 50%, #001438 100%);
            border: 1px solid rgba(255, 255, 255, 0.12);
            border-radius: 1rem;
            padding: 1.5rem 1.75rem;
            color: #ffffff;
            box-shadow: 0 4px 20px rgba(0, 27, 72, 0.25);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .att-adm-hero-top {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }
        @media (min-width: 1024px) {
            .att-adm-hero-top {
                flex-direction: row;
                align-items: center;
                justify-content: space-between;
            }
        }
        .att-adm-pill {
            display: inline-flex;
            align-items: center;
            gap: 0.35rem;
            padding: 0.2rem 0.6rem;
            border-radius: 9999px;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            background: rgba(255, 255, 255, 0.12);
            border: 1px solid rgba(255, 255, 255, 0.18);
            color: #ffffff;
        }
        .att-adm-pill.emerald {
            background: rgba(16, 185, 129, 0.2);
            border-color: rgba(16, 185, 129, 0.4);
            color: #6ee7b7;
        }

        /* KPI Grid */
        .att-adm-kpi-grid {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 0.875rem;
            padding-top: 1.25rem;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        @media (min-width: 768px) {
            .att-adm-kpi-grid {
                grid-template-columns: repeat(3, minmax(0, 1fr));
            }
        }
        @media (min-width: 1024px) {
            .att-adm-kpi-grid {
                grid-template-columns: repeat(6, minmax(0, 1fr));
            }
        }
        .att-adm-kpi-card {
            background: rgba(0, 15, 43, 0.5);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }
        .att-adm-kpi-val {
            font-size: 1.5rem;
            font-weight: 900;
            color: #ffffff;
            font-family: monospace;
            line-height: 1;
        }
        .att-adm-kpi-label {
            font-size: 0.65rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: #94a3b8;
        }

        /* Navigation Tabs */
        .att-adm-tabs {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.75rem;
            padding: 0.375rem;
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        .dark .att-adm-tabs {
            background: #111827;
            border-color: #1f2937;
        }
        .att-adm-tab-btn {
            flex: 1 1 auto;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 0.5rem;
            padding: 0.625rem 0.875rem;
            border-radius: 0.5rem;
            font-size: 0.75rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.04em;
            color: #64748b;
            background: transparent;
            border: none;
            cursor: pointer;
            transition: all 0.15s ease;
            white-space: nowrap;
        }
        .dark .att-adm-tab-btn { color: #94a3b8; }
        .att-adm-tab-btn:hover { background: #f1f5f9; color: #0f172a; }
        .dark .att-adm-tab-btn:hover { background: #1f2937; color: #f8fafc; }
        .att-adm-tab-btn.active {
            background: #001b48;
            color: #ffffff;
            box-shadow: 0 2px 6px rgba(0, 27, 72, 0.25);
        }
        .dark .att-adm-tab-btn.active { background: #1e3a5f; color: #ffffff; }

        /* Cards */
        .att-adm-card {
            background: #ffffff;
            border: 1px solid #e2e8f0;
            border-radius: 0.875rem;
            padding: 1.5rem;
            box-shadow: 0 1px 3px rgba(0, 0, 0, 0.03);
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
        }
        .dark .att-adm-card {
            background: #111827;
            border-color: #1f2937;
        }
        .att-adm-card-head {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding-bottom: 1rem;
            border-bottom: 1px solid #f1f5f9;
        }
        .dark .att-adm-card-head { border-bottom-color: #1f2937; }

        /* Tables */
        .att-adm-table-wrap {
            width: 100%;
            overflow-x: auto;
            border: 1px solid #e2e8f0;
            border-radius: 0.625rem;
        }
        .dark .att-adm-table-wrap { border-color: #1f2937; }
        .att-adm-table {
            width: 100%;
            border-collapse: collapse;
            text-align: left;
            font-size: 0.75rem;
        }
        .att-adm-table th {
            background: #f8fafc;
            padding: 0.625rem 0.875rem;
            font-weight: 700;
            text-transform: uppercase;
            font-size: 0.65rem;
            letter-spacing: 0.05em;
            color: #64748b;
            border-bottom: 1px solid #e2e8f0;
            white-space: nowrap;
        }
        .dark .att-adm-table th { background: #111827; color: #94a3b8; border-bottom-color: #1f2937; }
        .att-adm-table td {
            padding: 0.75rem 0.875rem;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        .dark .att-adm-table td { border-bottom-color: #1f2937; color: #cbd5e1; }
        .att-adm-table tr:hover td { background: #f8fafc; }
        .dark .att-adm-table tr:hover td { background: #1e293b; }

        /* Buttons */
        .att-adm-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 0.35rem;
            padding: 0.4rem 0.85rem;
            border-radius: 0.5rem;
            font-size: 0.6875rem;
            font-weight: 700;
            text-transform: uppercase;
            cursor: pointer;
            border: 1px solid transparent;
            transition: all 0.15s ease;
            text-decoration: none;
            white-space: nowrap;
        }
        .att-adm-btn-primary { background: #001b48; color: #ffffff; }
        .att-adm-btn-primary:hover { background: #002566; }
        .att-adm-btn-emerald { background: #059669; color: #ffffff; }
        .att-adm-btn-emerald:hover { background: #047857; }
        .att-adm-btn-outline { background: #ffffff; border-color: #d1d5db; color: #374151; }
        .att-adm-btn-outline:hover { background: #f9fafb; }
        .dark .att-adm-btn-outline { background: #1f2937; border-color: #374151; color: #e5e7eb; }

        /* Badges */
        .att-adm-badge {
            display: inline-flex;
            align-items: center;
            gap: 0.25rem;
            padding: 0.2rem 0.5rem;
            border-radius: 0.375rem;
            font-size: 0.6875rem;
            font-weight: 700;
            white-space: nowrap;
        }
        .att-adm-badge.emerald { background: #ecfdf5; color: #047857; }
        .att-adm-badge.rose { background: #fff1f2; color: #be123c; }
        .att-adm-badge.amber { background: #fffbeb; color: #b45309; }
        .att-adm-badge.blue { background: #eff6ff; color: #1d4ed8; }
        .att-adm-badge.gray { background: #f1f5f9; color: #475569; }

        .dark .att-adm-badge.emerald { background: #064e3b; color: #a7f3d0; }
        .dark .att-adm-badge.rose { background: #881337; color: #fecdd3; }
        .dark .att-adm-badge.amber { background: #78350f; color: #fde68a; }
        .dark .att-adm-badge.blue { background: #1e3a8a; color: #bfdbfe; }
        .dark .att-adm-badge.gray { background: #334155; color: #cbd5e1; }

        /* Map styling */
        #att-leaflet-map {
            width: 100%;
            height: 480px;
            border-radius: 0.75rem;
            border: 1px solid #e2e8f0;
            z-index: 10;
        }
        .dark #att-leaflet-map { border-color: #1f2937; }
    </style>

    <!-- Leaflet Assets (Free, Open-Source) -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin=""/>
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js" integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>

    <div class="att-adm-wrap">

        <!-- ================================================================= -->
        <!-- 1. HERO BANNER & REAL-TIME KPI SUMMARY -->
        <!-- ================================================================= -->
        <div class="att-adm-hero">
            <div class="att-adm-hero-top">
                <div>
                    <div style="display:flex;align-items:center;gap:0.5rem;margin-bottom:0.35rem;">
                        <span class="att-adm-pill emerald">
                            <span style="width:6px;height:6px;border-radius:9999px;background:#34d399;"></span>
                            Live System Active
                        </span>
                        <span class="att-adm-pill">PIN-Locked PWA</span>
                        <span style="font-size:0.6875rem;color:#cbd5e1;">Showroom & Warehouse Operations</span>
                    </div>
                    <h1 style="font-size:1.5rem;font-weight:900;letter-spacing:-0.02em;margin:0;">
                        Employee Attendance Control Center
                    </h1>
                    <p style="font-size:0.8125rem;color:#cbd5e1;margin-top:0.25rem;">
                        Monitor real-time showroom clock-ins, track live GPS locations, and generate unique employee PIN credentials.
                    </p>
                </div>

                <div style="display:flex;gap:0.5rem;">
                    <button
                        type="button"
                        wire:click="$refresh"
                        class="att-adm-btn att-adm-btn-emerald"
                        style="padding:0.625rem 1.125rem;">
                        <span>🔄 Refresh Live Data</span>
                    </button>
                </div>
            </div>

            <!-- KPI Cards -->
            <div class="att-adm-kpi-grid">
                <div class="att-adm-kpi-card">
                    <span class="att-adm-kpi-val" style="color:#34d399;">{{ $summary['currently_checked_in'] }}</span>
                    <span class="att-adm-kpi-label">Currently Checked In</span>
                </div>
                <div class="att-adm-kpi-card">
                    <span class="att-adm-kpi-val">{{ $summary['checked_out_today'] }}</span>
                    <span class="att-adm-kpi-label">Checked Out Today</span>
                </div>
                <div class="att-adm-kpi-card">
                    <span class="att-adm-kpi-val" style="color:#fbbf24;">{{ $summary['not_checked_in_today'] }}</span>
                    <span class="att-adm-kpi-label">Not Checked In</span>
                </div>
                <div class="att-adm-kpi-card">
                    <span class="att-adm-kpi-val" style="color:#f87171;">{{ $summary['late_arrivals'] }}</span>
                    <span class="att-adm-kpi-label">Late Arrivals (Post 10am)</span>
                </div>
                <div class="att-adm-kpi-card">
                    <span class="att-adm-kpi-val">{{ $summary['total_punches_today'] }}</span>
                    <span class="att-adm-kpi-label">Today's Total Punches</span>
                </div>
                <div class="att-adm-kpi-card">
                    <span class="att-adm-kpi-val" style="color:#60a5fa;">{{ $summary['total_active'] }}</span>
                    <span class="att-adm-kpi-label">Active Employees</span>
                </div>
            </div>
        </div>

        <!-- ================================================================= -->
        <!-- 2. NAVIGATION TAB BAR -->
        <!-- ================================================================= -->
        <div class="att-adm-tabs">
            <button
                type="button"
                wire:click="setTab('overview')"
                class="att-adm-tab-btn {{ $activeTab === 'overview' ? 'active' : '' }}">
                <span>📋 Overview & Live Punches</span>
                <span class="att-adm-badge gray">{{ $todayEvents->count() }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('map')"
                class="att-adm-tab-btn {{ $activeTab === 'map' ? 'active' : '' }}">
                <span>🗺️ Live GPS Map</span>
                <span class="att-adm-badge emerald">{{ $mapEmployees->count() }} active</span>
            </button>

            <button
                type="button"
                wire:click="setTab('employees')"
                class="att-adm-tab-btn {{ $activeTab === 'employees' ? 'active' : '' }}">
                <span>👥 Employees & PIN Management</span>
                <span class="att-adm-badge gray">{{ $employees->count() }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('locations')"
                class="att-adm-tab-btn {{ $activeTab === 'locations' ? 'active' : '' }}">
                <span>📍 Geofence Locations</span>
                <span class="att-adm-badge gray">{{ $locations->count() }}</span>
            </button>

            <button
                type="button"
                wire:click="setTab('settings')"
                class="att-adm-tab-btn {{ $activeTab === 'settings' ? 'active' : '' }}">
                <span>⚙️ Attendance Rules</span>
            </button>

            <button
                type="button"
                wire:click="setTab('audit')"
                class="att-adm-tab-btn {{ $activeTab === 'audit' ? 'active' : '' }}">
                <span>🛡️ Audit Trail</span>
                <span class="att-adm-badge gray">{{ $auditLogs->count() }}</span>
            </button>
        </div>

        <!-- ================================================================= -->
        <!-- TAB 1: OVERVIEW & LIVE ATTENDANCE PUNCH STREAM -->
        <!-- ================================================================= -->
        @if($activeTab === 'overview')
            <div class="att-adm-card">
                <div class="att-adm-card-head">
                    <div>
                        <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">Today's Attendance Events</h3>
                        <p style="font-size:0.75rem;color:#64748b;margin:0;">Authoritative server timestamps and GPS geofence verification results</p>
                    </div>
                </div>

                <div class="att-adm-table-wrap">
                    <table class="att-adm-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Type</th>
                                <th>Server Timestamp</th>
                                <th>Location & Distance</th>
                                <th>GPS Accuracy</th>
                                <th>Geofence</th>
                                <th>Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($todayEvents as $ev)
                                <tr>
                                    <td>
                                        <div style="font-weight:800;color:#0f172a;">{{ $ev->employee?->full_name }}</div>
                                        <div style="font-size:0.65rem;color:#64748b;font-family:monospace;">{{ $ev->employee?->employee_number }}</div>
                                    </td>
                                    <td>
                                        @if($ev->type === 'check_in')
                                            <span class="att-adm-badge emerald">Clock In</span>
                                        @else
                                            <span class="att-adm-badge rose">Clock Out</span>
                                        @endif
                                    </td>
                                    <td>
                                        <div style="font-weight:700;color:#0f172a;font-family:monospace;">
                                            {{ $ev->server_recorded_at->format('h:i:s A') }}
                                        </div>
                                    </td>
                                    <td>
                                        <div>{{ $ev->location?->name ?? 'Laijau Showroom' }}</div>
                                        @if($ev->distance_from_location_meters !== null)
                                            <div style="font-size:0.65rem;color:#64748b;font-family:monospace;">
                                                Distance: {{ round($ev->distance_from_location_meters) }}m
                                            </div>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ev->accuracy_meters !== null)
                                            <span style="font-family:monospace;">±{{ round($ev->accuracy_meters) }}m</span>
                                        @else
                                            <span style="color:#94a3b8;">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if($ev->geofence_passed)
                                            <span class="att-adm-badge emerald">Inside Geofence</span>
                                        @else
                                            <span class="att-adm-badge rose">Outside</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="att-adm-badge blue">{{ strtoupper($ev->verification_method ?? 'PIN') }}</span>
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="7" style="padding:2.5rem;text-align:center;color:#64748b;">
                                        No attendance punches recorded yet today.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 2: LIVE SHOWROOM GPS MAP -->
        <!-- ================================================================= -->
        @if($activeTab === 'map')
            <div class="att-adm-card">
                <div class="att-adm-card-head">
                    <div>
                        <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">Live Showroom Workforce Map</h3>
                        <p style="font-size:0.75rem;color:#64748b;margin:0;">Locations of currently clocked-in employees. Stale markers indicate device inactive for &gt;15 minutes.</p>
                    </div>

                    <div style="display:flex;align-items:center;gap:0.75rem;font-size:0.6875rem;">
                        <span style="display:inline-flex;align-items:center;gap:0.25rem;">
                            <span style="width:8px;height:8px;border-radius:9999px;background:#10b981;"></span>
                            Active (&lt;15m)
                        </span>
                        <span style="display:inline-flex;align-items:center;gap:0.25rem;">
                            <span style="width:8px;height:8px;border-radius:9999px;background:#f59e0b;"></span>
                            Stale (&gt;15m)
                        </span>
                        <span style="display:inline-flex;align-items:center;gap:0.25rem;">
                            <span style="width:8px;height:8px;border-radius:9999px;background:#059669;opacity:0.3;"></span>
                            Workplace Geofence
                        </span>
                    </div>
                </div>

                <div id="att-leaflet-map" wire:ignore></div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 3: EMPLOYEES & PIN MANAGEMENT -->
        <!-- ================================================================= -->
        @if($activeTab === 'employees')
            <div class="att-adm-card">
                <div class="att-adm-card-head">
                    <div>
                        <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">Employee Attendance PINs</h3>
                        <p style="font-size:0.75rem;color:#64748b;margin:0;">Generate or reset unique attendance PINs for staff members. PINs are securely hashed at rest.</p>
                    </div>
                </div>

                <div class="att-adm-table-wrap">
                    <table class="att-adm-table">
                        <thead>
                            <tr>
                                <th>Employee</th>
                                <th>Phone</th>
                                <th>Department &amp; Position</th>
                                <th>Attendance PIN</th>
                                <th>Access Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($employees as $emp)
                                <tr>
                                    <td>
                                        <div style="font-weight:800;color:#0f172a;">{{ $emp['name'] }}</div>
                                        <div style="font-size:0.65rem;color:#64748b;font-family:monospace;">{{ $emp['code'] }}</div>
                                        @if($emp['can_punch_from_anywhere'])
                                            <span class="att-adm-badge blue" style="font-size:0.6rem;margin-top:0.25rem;">📍 Punch From Anywhere Authorized</span>
                                        @endif
                                    </td>
                                    <td style="font-family:monospace;">{{ $emp['phone'] }}</td>
                                    <td>
                                        <div>{{ $emp['department'] }}</div>
                                        <div style="font-size:0.65rem;color:#64748b;">{{ $emp['position'] }}</div>
                                    </td>
                                    <td>
                                        @if($emp['has_pin'])
                                            <span class="att-adm-badge emerald">PIN Active</span>
                                            <div style="font-size:0.65rem;color:#94a3b8;">Configured: {{ $emp['pin_set_at'] }}</div>
                                        @else
                                            <span class="att-adm-badge amber">No PIN Assigned</span>
                                        @endif
                                    </td>
                                    <td>
                                        @if(!$emp['access_enabled'])
                                            <span class="att-adm-badge rose">Access Disabled</span>
                                        @elseif($emp['is_locked'])
                                            <span class="att-adm-badge amber">Locked (Failed Attempts)</span>
                                        @else
                                            <span class="att-adm-badge emerald">Enabled</span>
                                        @endif
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:inline-flex;gap:0.35rem;align-items:center;">
                                            <!-- Auto-Generate Unique PIN -->
                                            <button
                                                type="button"
                                                wire:click="generatePinForEmployee({{ $emp['id'] }})"
                                                class="att-adm-btn att-adm-btn-emerald"
                                                title="Generate a random unique PIN">
                                                ⚡ Generate PIN
                                            </button>

                                            <!-- Custom PIN -->
                                            <button
                                                type="button"
                                                wire:click="openPinModal({{ $emp['id'] }})"
                                                class="att-adm-btn att-adm-btn-primary"
                                                title="Set custom PIN">
                                                Custom PIN
                                            </button>

                                            <!-- Unlock if locked -->
                                            @if($emp['is_locked'])
                                                <button
                                                    type="button"
                                                    wire:click="unlockEmployee({{ $emp['id'] }})"
                                                    class="att-adm-btn att-adm-btn-outline"
                                                    style="color:#d97706;border-color:#d97706;">
                                                    Unlock
                                                </button>
                                            @endif

                                            <!-- Enable / Disable -->
                                            <button
                                                type="button"
                                                wire:click="toggleEmployeeAccess({{ $emp['id'] }})"
                                                class="att-adm-btn att-adm-btn-outline">
                                                {{ $emp['access_enabled'] ? 'Disable' : 'Enable' }}
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 4: GEOFENCE LOCATIONS -->
        <!-- ================================================================= -->
        @if($activeTab === 'locations')
            <div class="att-adm-card">
                <div class="att-adm-card-head">
                    <div>
                        <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">Showroom &amp; Warehouse Geofence Locations</h3>
                        <p style="font-size:0.75rem;color:#64748b;margin:0;">Configure latitude, longitude, and allowed radius for physical attendance validation</p>
                    </div>

                    <button
                        type="button"
                        wire:click="openLocationModal()"
                        class="att-adm-btn att-adm-btn-emerald">
                        <span>➕ Add Location</span>
                    </button>
                </div>

                <div class="att-adm-table-wrap">
                    <table class="att-adm-table">
                        <thead>
                            <tr>
                                <th>Location Name</th>
                                <th>Code</th>
                                <th>Coordinates (Lat, Lon)</th>
                                <th>Allowed Radius</th>
                                <th>Address</th>
                                <th>Status</th>
                                <th style="text-align:right;">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach($locations as $loc)
                                <tr>
                                    <td>
                                        <div style="font-weight:800;color:#0f172a;">{{ $loc->name }}</div>
                                        @if($loc->is_default)
                                            <span class="att-adm-badge emerald" style="font-size:0.6rem;">Headquarters Default</span>
                                        @endif
                                    </td>
                                    <td style="font-family:monospace;">{{ $loc->code }}</td>
                                    <td style="font-family:monospace;">{{ $loc->latitude }}, {{ $loc->longitude }}</td>
                                    <td style="font-weight:700;">{{ $loc->radius_meters }} meters</td>
                                    <td style="color:#64748b;">{{ $loc->address ?: '—' }}</td>
                                    <td>
                                        <span class="att-adm-badge {{ $loc->is_active ? 'emerald' : 'gray' }}">
                                             {{ $loc->is_active ? 'Active' : 'Inactive' }}
                                        </span>
                                    </td>
                                    <td style="text-align:right;">
                                        <div style="display:inline-flex;gap:0.35rem;">
                                            @if(!$loc->is_default)
                                                <button
                                                    type="button"
                                                    wire:click="setDefaultLocation({{ $loc->id }})"
                                                    class="att-adm-btn att-adm-btn-emerald"
                                                    style="font-size:0.65rem;">
                                                    Set Default
                                                </button>
                                            @endif
                                            <button
                                                type="button"
                                                wire:click="openLocationModal({{ $loc->id }})"
                                                class="att-adm-btn att-adm-btn-outline">
                                                Edit
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 5: ATTENDANCE SETTINGS -->
        <!-- ================================================================= -->
        @if($activeTab === 'settings')
            <div class="att-adm-card">
                <div class="att-adm-card-head">
                    <div>
                        <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">Attendance Rules &amp; Security Policies</h3>
                        <p style="font-size:0.75rem;color:#64748b;margin:0;">Control GPS accuracy tolerances, geofence radius, and PIN lockout parameters</p>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr;gap:1.25rem;">
                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:1rem;">
                        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;">
                            <input type="checkbox" wire:model="attEnabled" style="width:18px;height:18px;">
                            <strong>Attendance System Enabled</strong>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;">
                            <input type="checkbox" wire:model="gpsRequired" style="width:18px;height:18px;">
                            <strong>GPS Coordinates Required</strong>
                        </label>
                        <label style="display:flex;align-items:center;gap:0.5rem;font-size:0.8125rem;cursor:pointer;">
                            <input type="checkbox" wire:model="geofencingEnabled" style="width:18px;height:18px;">
                            <strong>Geofence Radius Enforcement</strong>
                        </label>
                    </div>

                    <hr style="border:0;border-top:1px solid #e2e8f0;">

                    <div style="display:grid;grid-template-columns:repeat(4, minmax(0, 1fr));gap:1rem;">
                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Showroom Geofence Radius (Meters)</label>
                            <input type="number" wire:model="showroomGeofenceRadius" min="10" max="1000" style="width:100%;height:2.5rem;border:1px solid #d1d5db;border-radius:0.5rem;padding:0 0.75rem;margin-top:0.25rem;">
                            <span style="font-size:0.65rem;color:#94a3b8;">Max distance allowed from Showroom (27.6977° N, 85.3664° E)</span>
                        </div>

                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Max Acceptable GPS Accuracy (Meters)</label>
                            <input type="number" wire:model="maxGpsAccuracy" min="10" max="500" style="width:100%;height:2.5rem;border:1px solid #d1d5db;border-radius:0.5rem;padding:0 0.75rem;margin-top:0.25rem;">
                            <span style="font-size:0.65rem;color:#94a3b8;">Rejects readings with accuracy &gt; this threshold (e.g. 100m)</span>
                        </div>

                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Max Failed PIN Attempts</label>
                            <input type="number" wire:model="maxFailedAttempts" min="3" max="10" style="width:100%;height:2.5rem;border:1px solid #d1d5db;border-radius:0.5rem;padding:0 0.75rem;margin-top:0.25rem;">
                            <span style="font-size:0.65rem;color:#94a3b8;">Consecutive failed entries before lockout</span>
                        </div>

                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Lockout Duration (Minutes)</label>
                            <input type="number" wire:model="lockoutMinutes" min="5" max="120" style="width:100%;height:2.5rem;border:1px solid #d1d5db;border-radius:0.5rem;padding:0 0.75rem;margin-top:0.25rem;">
                            <span style="font-size:0.65rem;color:#94a3b8;">Lockout period before retry is permitted</span>
                        </div>
                    </div>

                    <div style="padding-top:1rem;border-top:1px solid #e2e8f0;">
                        <button
                            type="button"
                            wire:click="saveSettings"
                            class="att-adm-btn att-adm-btn-primary"
                            style="padding:0.625rem 1.25rem;font-size:0.75rem;">
                            Save Attendance Configuration
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- TAB 6: AUDIT TRAIL -->
        <!-- ================================================================= -->
        @if($activeTab === 'audit')
            <div class="att-adm-card">
                <div class="att-adm-card-head">
                    <div>
                        <h3 style="font-size:1rem;font-weight:800;color:#0f172a;margin:0;">Immutable Attendance Audit Trail</h3>
                        <p style="font-size:0.75rem;color:#64748b;margin:0;">Log of PIN generation, access changes, and security events</p>
                    </div>
                </div>

                <div class="att-adm-table-wrap">
                    <table class="att-adm-table">
                        <thead>
                            <tr>
                                <th>Timestamp</th>
                                <th>Admin User</th>
                                <th>Employee</th>
                                <th>Action</th>
                                <th>Details</th>
                                <th>IP Address</th>
                            </tr>
                        </thead>
                        <tbody>
                            @forelse($auditLogs as $log)
                                <tr>
                                    <td style="color:#64748b;font-family:monospace;white-space:nowrap;">
                                        {{ $log->created_at->format('d M Y, H:i:s') }}
                                    </td>
                                    <td>{{ $log->user?->name ?? 'System' }}</td>
                                    <td>
                                        @if($log->employee)
                                            <strong>{{ $log->employee->full_name }}</strong> ({{ $log->employee->employee_number }})
                                        @else
                                            <span style="color:#94a3b8;">—</span>
                                        @endif
                                    </td>
                                    <td>
                                        <span class="att-adm-badge blue">{{ str_replace('_', ' ', $log->action) }}</span>
                                    </td>
                                    <td style="color:#475569;">{{ $log->details }}</td>
                                    <td style="font-family:monospace;color:#64748b;">{{ $log->ip_address }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="6" style="padding:2.5rem;text-align:center;color:#64748b;">
                                        No audit events logged yet.
                                    </td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- MODAL: DISPLAY GENERATED UNIQUE PIN -->
        <!-- ================================================================= -->
        @if($showGeneratedPinModal)
            <div style="position:fixed;inset:0;background:rgba(0,15,43,0.65);z-index:100;display:flex;align-items:center;justify-content:center;padding:1rem;">
                <div style="width:100%;max-width:420px;background:#ffffff;border-radius:1rem;padding:1.75rem;display:flex;flex-direction:column;gap:1.25rem;box-shadow:0 15px 35px rgba(0,0,0,0.25);text-align:center;">
                    <div style="width:3.5rem;height:3.5rem;border-radius:9999px;background:#ecfdf5;color:#059669;display:inline-flex;align-items:center;justify-content:center;font-size:1.75rem;margin:0 auto;">
                        🔑
                    </div>

                    <div>
                        <h3 style="font-size:1.25rem;font-weight:900;color:#001b48;margin:0;">Unique PIN Generated</h3>
                        <p style="font-size:0.8125rem;color:#64748b;margin-top:0.35rem;">
                            For: <strong>{{ $generatedPinEmployeeName }}</strong>
                        </p>
                    </div>

                    <div style="padding:1rem;background:#f8fafc;border:2px dashed #059669;border-radius:0.75rem;">
                        <div style="font-size:0.75rem;color:#64748b;text-transform:uppercase;letter-spacing:0.05em;font-weight:700;">Employee Attendance PIN</div>
                        <div style="font-size:2.25rem;font-weight:900;font-family:monospace;letter-spacing:0.25em;color:#001b48;margin-top:0.25rem;">
                            {{ $generatedPin }}
                        </div>
                    </div>

                    <p style="font-size:0.75rem;color:#dc2626;font-weight:600;margin:0;line-height:1.4;">
                        ⚠️ Please share this PIN with the employee immediately. For security, PINs are hashed and cannot be viewed again.
                    </p>

                    <div>
                        <button
                            type="button"
                            wire:click="closeGeneratedPinModal"
                            class="att-adm-btn att-adm-btn-primary"
                            style="width:100%;padding:0.75rem;font-size:0.875rem;">
                            Done / Close
                        </button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- MODAL: SET CUSTOM EMPLOYEE PIN -->
        <!-- ================================================================= -->
        @if($showPinModal)
            <div style="position:fixed;inset:0;background:rgba(0,15,43,0.6);z-index:100;display:flex;align-items:center;justify-content:center;padding:1rem;">
                <div style="width:100%;max-width:400px;background:#ffffff;border-radius:1rem;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <h3 style="font-size:1rem;font-weight:800;color:#001b48;margin:0;">Set Custom Attendance PIN</h3>
                        <button type="button" wire:click="closePinModal" style="border:none;background:none;font-size:1.25rem;cursor:pointer;">✕</button>
                    </div>

                    <p style="font-size:0.75rem;color:#64748b;margin:0;">
                        Enter a unique 4 to 8-digit numeric PIN for the employee. The PIN is hashed at rest.
                    </p>

                    <div>
                        <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">New PIN (4-8 digits)</label>
                        <input type="password" wire:model="newPin" maxlength="8" style="width:100%;height:2.5rem;border:1px solid #d1d5db;border-radius:0.5rem;padding:0 0.75rem;font-family:monospace;letter-spacing:0.2em;font-size:1.25rem;text-align:center;">
                    </div>

                    <div>
                        <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Confirm New PIN</label>
                        <input type="password" wire:model="newPinConfirmation" maxlength="8" style="width:100%;height:2.5rem;border:1px solid #d1d5db;border-radius:0.5rem;padding:0 0.75rem;font-family:monospace;letter-spacing:0.2em;font-size:1.25rem;text-align:center;">
                    </div>

                    <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                        <button type="button" wire:click="closePinModal" class="att-adm-btn att-adm-btn-outline" style="flex:1;">Cancel</button>
                        <button type="button" wire:click="saveEmployeePin" class="att-adm-btn att-adm-btn-primary" style="flex:2;">Save PIN</button>
                    </div>
                </div>
            </div>
        @endif

        <!-- ================================================================= -->
        <!-- MODAL: ADD / EDIT GEOFENCE LOCATION -->
        <!-- ================================================================= -->
        @if($showLocationModal)
            <div style="position:fixed;inset:0;background:rgba(0,15,43,0.6);z-index:100;display:flex;align-items:center;justify-content:center;padding:1rem;">
                <div style="width:100%;max-width:480px;background:#ffffff;border-radius:1rem;padding:1.5rem;display:flex;flex-direction:column;gap:1rem;box-shadow:0 10px 25px rgba(0,0,0,0.2);">
                    <div style="display:flex;align-items:center;justify-content:space-between;">
                        <h3 style="font-size:1rem;font-weight:800;color:#001b48;margin:0;">
                            {{ $editingLocationId ? 'Edit Geofence Location' : 'Add Geofence Location' }}
                        </h3>
                        <button type="button" wire:click="closeLocationModal" style="border:none;background:none;font-size:1.25rem;cursor:pointer;">✕</button>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr;gap:0.75rem;">
                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Location Name *</label>
                            <input type="text" wire:model="locName" style="width:100%;height:2.25rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0 0.5rem;">
                        </div>
                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Code</label>
                            <input type="text" wire:model="locCode" style="width:100%;height:2.25rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0 0.5rem;font-family:monospace;">
                        </div>
                    </div>

                    <div style="display:grid;grid-template-columns:1fr 1fr 1fr;gap:0.75rem;">
                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Latitude</label>
                            <input type="number" step="0.0000001" wire:model="locLatitude" style="width:100%;height:2.25rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0 0.5rem;font-family:monospace;">
                        </div>
                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Longitude</label>
                            <input type="number" step="0.0000001" wire:model="locLongitude" style="width:100%;height:2.25rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0 0.5rem;font-family:monospace;">
                        </div>
                        <div>
                            <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Radius (m)</label>
                            <input type="number" min="10" max="1000" wire:model="locRadius" style="width:100%;height:2.25rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0 0.5rem;">
                        </div>
                    </div>

                    <div>
                        <label style="font-size:0.6875rem;font-weight:700;text-transform:uppercase;color:#475569;">Physical Address</label>
                        <input type="text" wire:model="locAddress" placeholder="e.g. Bohara Tol, Kathmandu" style="width:100%;height:2.25rem;border:1px solid #d1d5db;border-radius:0.375rem;padding:0 0.5rem;">
                    </div>

                    <div style="display:flex;gap:0.5rem;margin-top:0.5rem;">
                        <button type="button" wire:click="closeLocationModal" class="att-adm-btn att-adm-btn-outline" style="flex:1;">Cancel</button>
                        <button type="button" wire:click="saveLocation" class="att-adm-btn att-adm-btn-primary" style="flex:2;">Save Location</button>
                    </div>
                </div>
            </div>
        @endif

    </div>

    <!-- Leaflet Map Script -->
    <script>
        document.addEventListener('livewire:navigated', initLeafletMap);
        document.addEventListener('DOMContentLoaded', initLeafletMap);

        let attMap = null;

        function initLeafletMap() {
            const mapContainer = document.getElementById('att-leaflet-map');
            if (!mapContainer) return;

            if (attMap) {
                attMap.remove();
                attMap = null;
            }

            const defaultLat = {{ $defaultLoc ? $defaultLoc->latitude : 27.6976748 }};
            const defaultLon = {{ $defaultLoc ? $defaultLoc->longitude : 85.3664331 }};
            const geofenceRadius = {{ $defaultLoc ? $defaultLoc->radius_meters : 100 }};

            attMap = L.map('att-leaflet-map').setView([defaultLat, defaultLon], 16);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                maxZoom: 19,
                attribution: '© OpenStreetMap'
            }).addTo(attMap);

            // Workplace Geofence circle
            L.circle([defaultLat, defaultLon], {
                color: '#059669',
                fillColor: '#10b981',
                fillOpacity: 0.15,
                radius: geofenceRadius
            }).addTo(attMap).bindPopup("<strong>Laijau Showroom</strong><br>Permitted Geofence: " + geofenceRadius + "m");

            // Employee active markers
            const employees = @json($mapEmployees);
            employees.forEach(emp => {
                const color = emp.is_stale ? '#f59e0b' : '#10b981';
                const marker = L.circleMarker([emp.latitude, emp.longitude], {
                    radius: 8,
                    fillColor: color,
                    color: '#ffffff',
                    weight: 2,
                    opacity: 1,
                    fillOpacity: 0.9
                }).addTo(attMap);

                marker.bindPopup(`
                    <div style="font-family:sans-serif;font-size:12px;line-height:1.4;">
                        <strong>${emp.name}</strong> (${emp.code})<br>
                        Clocked In: ${emp.check_in_time}<br>
                        Last seen: ${emp.last_updated_human}<br>
                        Accuracy: ±${emp.accuracy || '?'}m
                    </div>
                `);
            });
        }
    </script>

</x-filament-panels::page>
