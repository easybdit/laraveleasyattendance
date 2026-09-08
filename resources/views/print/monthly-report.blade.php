<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Attendance Sheet — {{ $monthLabel }}</title>
    <style>
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #222; margin: 1.5rem; }
        h1 { font-size: 16px; margin-bottom: 0.25rem; }
        table { width: 100%; border-collapse: collapse; margin-top: 1rem; }
        th, td { border: 1px solid #ccc; padding: 3px 5px; text-align: center; }
        th { background: #f4f4f4; }
        td.name { text-align: left; white-space: nowrap; }
        .status-present, .status-late { color: #2a7d2a; }
        .status-absent { color: #b02a2a; }
        .status-leave { color: #2a5db0; }
        .status-holiday, .status-day_off { color: #999; }
        @media print {
            body { margin: 0.3cm; }
            .no-print { display: none; }
            table { font-size: 9px; }
        }
    </style>
</head>
<body>
    <button class="no-print" onclick="window.print()">Print / Save as PDF</button>

    <h1>Attendance Sheet — {{ $monthLabel }}</h1>

    <table>
        <thead>
            <tr>
                <th>Employee</th>
                @foreach ($days as $day)
                    <th>{{ $day }}</th>
                @endforeach
                <th>Present</th>
                <th>Absent</th>
                <th>Late</th>
                <th>Leave</th>
            </tr>
        </thead>
        <tbody>
            @foreach ($employees as $row)
                <tr>
                    <td class="name">{{ $row['employee']->name }} ({{ $row['employee']->employee_code }})</td>
                    @foreach ($days as $day)
                        @php($cell = $row['byDay'][$day] ?? null)
                        <td class="status-{{ $cell['status'] ?? '' }}">{{ $cell['label'] ?? '—' }}</td>
                    @endforeach
                    <td>{{ $row['present'] }}</td>
                    <td>{{ $row['absent'] }}</td>
                    <td>{{ $row['late'] }}</td>
                    <td>{{ $row['leave'] }}</td>
                </tr>
            @endforeach
        </tbody>
    </table>
</body>
</html>
