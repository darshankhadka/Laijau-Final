<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Payslip — {{ $item->employee->full_name }} ({{ $item->payslip_number }})</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=Outfit:wght@400;500;600;700&family=JetBrains+Mono:wght@400;600&display=swap" rel="stylesheet">
    <style>
        @page {
            size: A4;
            margin: 15mm;
        }
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }
        body {
            font-family: 'Outfit', sans-serif;
            color: #1e293b;
            background: #ffffff;
            font-size: 13px;
            line-height: 1.5;
            padding: 20px;
        }
        .payslip-container {
            max-width: 800px;
            margin: 0 auto;
            border: 2px solid #0f172a;
            padding: 24px;
            border-radius: 8px;
        }
        .header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            border-bottom: 2px solid #0f172a;
            padding-bottom: 16px;
            margin-bottom: 20px;
        }
        .company-name {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: 1px;
        }
        .company-address {
            font-size: 12px;
            color: #475569;
            margin-top: 2px;
        }
        .payslip-title-box {
            text-align: right;
        }
        .payslip-title {
            font-size: 18px;
            font-weight: 700;
            color: #047857;
            text-transform: uppercase;
        }
        .payslip-ref {
            font-family: 'JetBrains Mono', monospace;
            font-size: 12px;
            font-weight: 600;
            color: #0f172a;
            margin-top: 2px;
        }
        .details-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-bottom: 20px;
        }
        .detail-row {
            display: flex;
            justify-content: space-between;
            padding: 3px 0;
        }
        .detail-label {
            color: #64748b;
            font-size: 12px;
        }
        .detail-value {
            font-weight: 600;
            color: #0f172a;
            font-size: 12px;
        }
        .salary-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }
        .salary-table th, .salary-table td {
            border: 1px solid #cbd5e1;
            padding: 8px 12px;
            font-size: 12px;
        }
        .salary-table th {
            background: #f1f5f9;
            font-weight: 700;
            text-align: left;
            color: #0f172a;
        }
        .text-right {
            text-align: right;
        }
        .font-mono {
            font-family: 'JetBrains Mono', monospace;
        }
        .total-row {
            background: #f8fafc;
            font-weight: 700;
        }
        .net-pay-banner {
            background: #ecfdf5;
            border: 2px solid #10b981;
            border-radius: 6px;
            padding: 14px 20px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
        }
        .net-pay-label {
            font-size: 15px;
            font-weight: 700;
            color: #065f46;
            text-transform: uppercase;
        }
        .net-pay-amount {
            font-size: 24px;
            font-weight: 700;
            color: #047857;
            font-family: 'JetBrains Mono', monospace;
        }
        .employer-box {
            background: #fafafa;
            border: 1px dashed #cbd5e1;
            border-radius: 6px;
            padding: 10px 16px;
            font-size: 11px;
            color: #475569;
            margin-bottom: 40px;
        }
        .signature-section {
            display: flex;
            justify-content: space-between;
            padding-top: 30px;
        }
        .sig-box {
            width: 200px;
            text-align: center;
            border-top: 1px solid #0f172a;
            padding-top: 6px;
            font-size: 11px;
            font-weight: 600;
        }
        @media print {
            body {
                padding: 0;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="no-print" style="max-width: 800px; margin: 0 auto 12px; text-align: right;">
        <button onclick="window.print()" style="padding: 8px 18px; background: #047857; color: #fff; border: none; border-radius: 6px; cursor: pointer; font-weight: 600;">🖨 Print / Download PDF</button>
        <button onclick="window.history.back()" style="padding: 8px 18px; background: #64748b; color: #fff; border: none; border-radius: 6px; cursor: pointer; margin-left: 8px;">← Back</button>
    </div>

    <div class="payslip-container">
        <!-- Header -->
        <div class="header">
            <div>
                <div class="company-name">LAIJAU</div>
                <div class="company-address" style="font-weight: 600; color: #0f172a;">Delta Nine business group</div>
                <div class="company-address">Bohara Tol, Kageshwori Manahara 09, Kathmandu, Nepal • Phone: 9843512095 • info@laijau.com</div>
                <div class="company-address" style="font-weight: 600; color: #0f172a; margin-top: 2px;">VAT No: 604335148 • IRD Registered</div>
            </div>
            <div class="payslip-title-box">
                <div class="payslip-title">Salary Payslip</div>
                <div class="payslip-ref">{{ $item->payslip_number }}</div>
                <div style="font-size: 11px; color: #64748b; margin-top: 2px;">FY {{ $item->payrollRun->fiscal_year }} • {{ $item->payrollRun->name }}</div>
            </div>
        </div>

        <!-- Employee Details -->
        <div class="details-grid">
            <div>
                <div class="detail-row">
                    <span class="detail-label">Employee ID:</span>
                    <span class="detail-value font-mono">{{ $item->employee->employee_number }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Employee Name:</span>
                    <span class="detail-value">{{ $item->employee->full_name }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Designation:</span>
                    <span class="detail-value">{{ $item->employee->position?->title ?? 'Staff' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Department:</span>
                    <span class="detail-value">{{ $item->employee->department?->name ?? 'Operations' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Branch / Hub:</span>
                    <span class="detail-value">{{ $item->employee->branch_location ?? 'Laijau Showroom' }}</span>
                </div>
            </div>
            <div>
                <div class="detail-row">
                    <span class="detail-label">Nepal PAN #:</span>
                    <span class="detail-value font-mono">{{ $item->employee->pan_number ?? 'Pending' }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">SSF Number:</span>
                    <span class="detail-value font-mono">{{ $item->employee->ssf_number ?? ('SSF-' . $item->employee->id) }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Tax Marital Status:</span>
                    <span class="detail-value">{{ ucfirst($item->employee->marital_status ?? 'single') }}</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Bank Account:</span>
                    <span class="detail-value">{{ $item->employee->bank_name ?? 'Nabil Bank' }} ({{ $item->employee->bank_account_number ?? '01000...' }})</span>
                </div>
                <div class="detail-row">
                    <span class="detail-label">Pay Disbursement Date:</span>
                    <span class="detail-value">{{ $item->payrollRun->pay_date ? $item->payrollRun->pay_date->format('d M Y') : date('d M Y') }}</span>
                </div>
            </div>
        </div>

        <!-- Attendance Snapshot -->
        <div style="display: flex; gap: 12px; margin-bottom: 16px; font-size: 12px;">
            <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 4px;">Hours Worked: <strong>{{ number_format((float)$item->hours_worked, 1) }}h</strong></span>
            <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 4px;">Overtime: <strong>{{ number_format((float)$item->overtime_hours, 1) }}h</strong></span>
            <span style="background: #f1f5f9; padding: 4px 10px; border-radius: 4px;">Absent Days: <strong>{{ number_format((float)$item->absent_days, 1) }}</strong></span>
        </div>

        <!-- Salary Earnings & Deductions Breakdown -->
        <table class="salary-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Earnings (Income)</th>
                    <th style="width: 50%;" class="text-right">Amount (NPR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Basic Salary</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->basic_salary, 2) }}</td>
                </tr>
                <tr>
                    <td>Dearness & Living Allowances</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->allowance_amount, 2) }}</td>
                </tr>
                @if($item->overtime_amount > 0)
                <tr>
                    <td>Overtime Pay ({{ $item->overtime_hours }} hrs @ 1.5x)</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->overtime_amount, 2) }}</td>
                </tr>
                @endif
                @if($item->bonus_amount > 0)
                <tr>
                    <td>Festival / Dashain Bonus</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->bonus_amount, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>TOTAL GROSS EARNINGS</td>
                    <td class="text-right font-mono" style="color: #047857;">Rs. {{ number_format((float)$item->gross_salary, 2) }}</td>
                </tr>
            </tbody>
        </table>

        <table class="salary-table">
            <thead>
                <tr>
                    <th style="width: 50%;">Statutory & Other Deductions</th>
                    <th style="width: 50%;" class="text-right">Amount (NPR)</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>Social Security Fund (SSF Employee 11% on Basic)</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->ssf_employee_amount, 2) }}</td>
                </tr>
                <tr>
                    <td>Income Tax Withholding (TDS Sec 87 — {{ number_format((float)$item->tds_tax_rate, 1) }}%)</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->tds_tax_amount, 2) }}</td>
                </tr>
                @if($item->absence_deduction > 0)
                <tr>
                    <td>Unpaid Absence Deduction ({{ $item->absent_days }} days)</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->absence_deduction, 2) }}</td>
                </tr>
                @endif
                @if($item->other_deductions > 0)
                <tr>
                    <td>Other Salary Advances / Deductions</td>
                    <td class="text-right font-mono">Rs. {{ number_format((float)$item->other_deductions, 2) }}</td>
                </tr>
                @endif
                <tr class="total-row">
                    <td>TOTAL DEDUCTIONS</td>
                    <td class="text-right font-mono" style="color: #b91c1c;">Rs. {{ number_format((float)($item->ssf_employee_amount + $item->tds_tax_amount + $item->absence_deduction + $item->other_deductions), 2) }}</td>
                </tr>
            </tbody>
        </table>

        <!-- Net Pay Highlight -->
        <div class="net-pay-banner">
            <div>
                <div class="net-pay-label">Net Take Home Salary</div>
                <div style="font-size: 11px; color: #065f46;">Credited directly to employee bank account</div>
            </div>
            <div class="net-pay-amount">
                Rs. {{ number_format((float)$item->net_salary, 2) }}
            </div>
        </div>

        <!-- Statutory Employer Contribution Note -->
        <div class="employer-box">
            <strong>Statutory Employer Contributions (Not deducted from salary):</strong><br>
            • Social Security Fund (SSF Employer 20%): Rs. {{ number_format((float)$item->ssf_employer_amount, 2) }} (10% PF + 8.33% Gratuity + 1.67% Health/Accident)<br>
            • Total Cost to Company (CTC): Rs. {{ number_format((float)$item->employer_total_cost, 2) }}
        </div>

        <!-- Signatures -->
        <div class="signature-section">
            <div class="sig-box">
                Employee Signature
            </div>
            <div class="sig-box">
                Authorized HR / Finance Signatory
            </div>
        </div>
    </div>
</body>
</html>
