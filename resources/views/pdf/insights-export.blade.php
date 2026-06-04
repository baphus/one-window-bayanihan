<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        body { font-family: sans-serif; font-size: 11px; color: #1e293b; padding: 24px; }
        h1 { font-size: 16px; margin-bottom: 4px; }
        .meta { font-size: 10px; color: #64748b; margin-bottom: 16px; }
        table { width: 100%; border-collapse: collapse; margin-top: 8px; }
        th { background-color: #f1f5f9; text-align: left; padding: 6px 8px; font-size: 10px; text-transform: uppercase; letter-spacing: 0.08em; border-bottom: 2px solid #cbd5e1; }
        td { padding: 5px 8px; border-bottom: 1px solid #e2e8f0; }
        tr:nth-child(even) td { background-color: #f8fafc; }
        .footer { margin-top: 20px; font-size: 9px; color: #94a3b8; text-align: center; }
    </style>
</head>
<body>
    <h1>{{ $title }}</h1>
    <div class="meta">
        @if(!empty($chartType))
            <span>Chart: {{ $chartType }} &bull; </span>
        @endif
        Generated: {{ $generatedAt }}
    </div>

    @if(!empty($headers) && count($rows) > 0)
        <table>
            <thead>
                <tr>
                    @foreach($headers as $header)
                        <th>{{ $header }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @foreach($rows as $row)
                    <tr>
                        @foreach($headers as $header)
                            <td>{{ $row[$header] ?? '' }}</td>
                        @endforeach
                    </tr>
                @endforeach
            </tbody>
        </table>
    @else
        <p>No data available for export.</p>
    @endif

    <div class="footer">One Window Bayanihan &mdash; DMW Region VII</div>
</body>
</html>
