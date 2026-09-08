<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>{{ __('attendance::print.payslip.title') }} — {{ $slip->employee->name }} — {{ $slip->year }}-{{ str_pad($slip->month, 2, '0', STR_PAD_LEFT) }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 13px; color: #222; margin: 2rem; }
        h1 { font-size: 18px; margin-bottom: 0; }
        .muted { color: #666; }
        table { width: 100%; border-collapse: collapse; margin-top: 1.5rem; }
        th, td { border: 1px solid #ccc; padding: 6px 10px; text-align: left; }
        th { background: #f4f4f4; width: 40%; }
        .totals td { font-weight: bold; }
        .net { font-size: 16px; }
        @media print {
            body { margin: 0.5cm; }
            .no-print { display: none; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">{{ __('attendance::print.print_button') }}</button>

    <h1>{{ __('attendance::print.payslip.title') }}</h1>
    <p class="muted">{{ $slip->employee->name }} ({{ $slip->employee->employee_code }}) — {{ \Carbon\Carbon::create($slip->year, $slip->month, 1)->format('F Y') }}</p>

    <table>
        <tr><th>{{ __('attendance::print.payslip.basic_salary') }}</th><td>{{ number_format($slip->basic_salary, 2) }}</td></tr>
        @foreach ($slip->allowances ?? [] as $name => $amount)
            <tr><th>{{ ucwords(str_replace('_', ' ', $name)) }}</th><td>{{ number_format($amount, 2) }}</td></tr>
        @endforeach
        <tr><th>{{ __('attendance::print.payslip.present_days') }}</th><td>{{ $slip->present_days }}</td></tr>
        <tr><th>{{ __('attendance::print.payslip.absent_days') }}</th><td>{{ $slip->absent_days }}</td></tr>
        <tr><th>{{ __('attendance::print.payslip.late_days') }}</th><td>{{ $slip->late_days }}</td></tr>
        <tr><th>{{ __('attendance::print.payslip.leave_days') }}</th><td>{{ $slip->leave_days }}</td></tr>
        <tr><th>{{ __('attendance::print.payslip.overtime') }}</th><td>{{ $slip->overtime_hours }}h — {{ number_format($slip->overtime_amount, 2) }}</td></tr>
        <tr><th>{{ __('attendance::print.payslip.special_pay') }}</th><td>{{ number_format($slip->special_pay_amount, 2) }}</td></tr>
        <tr><th>{{ __('attendance::print.payslip.deductions') }}</th><td>-{{ number_format($slip->deduction_amount, 2) }}</td></tr>
        <tr class="totals"><th>{{ __('attendance::print.payslip.net_salary') }}</th><td class="net">{{ number_format($slip->net_salary, 2) }}</td></tr>
    </table>

    <p class="muted" style="margin-top: 2rem;">{{ __('attendance::print.payslip.generated', ['when' => $slip->generated_at?->format('Y-m-d H:i')]) }}</p>
</body>
</html>
