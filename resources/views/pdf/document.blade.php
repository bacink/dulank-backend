<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <title>{{ $title }}</title>
    <style>
        * { font-family: DejaVu Sans, sans-serif; }
        body { font-size: 12px; color: #1f2937; margin: 24px; }
        .header { border-bottom: 2px solid #0d6efd; padding-bottom: 8px; margin-bottom: 16px; }
        .company { font-size: 16px; font-weight: bold; }
        .doc-title { font-size: 14px; margin-top: 4px; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #d1d5db; padding: 6px 8px; text-align: left; }
        th { background: #f3f4f6; }
        .totals { margin-top: 12px; width: 50%; margin-left: auto; }
        .totals td { text-align: right; }
        .meta { margin: 4px 0; }
        .meta strong { display: inline-block; min-width: 120px; }
        .footer { margin-top: 32px; font-size: 10px; color: #6b7280; }
    </style>
</head>
<body>
    <div class="header">
        <div class="company">PT Dulank Semesta Cida</div>
        <div class="doc-title">{{ $title }}</div>
    </div>

    @foreach ($meta as $label => $value)
        <div class="meta"><strong>{{ $label }}</strong>{{ $value }}</div>
    @endforeach

    @if (! empty($columns))
        <table>
            <thead>
                <tr>
                    @foreach ($columns as $column)
                        <th>{{ $column }}</th>
                    @endforeach
                </tr>
            </thead>
            <tbody>
                @forelse ($rows as $row)
                    <tr>
                        @foreach ($row as $cell)
                            <td>{{ $cell }}</td>
                        @endforeach
                    </tr>
                @empty
                    <tr><td colspan="{{ count($columns) }}">Tidak ada data.</td></tr>
                @endforelse
            </tbody>
        </table>
    @endif

    @if (! empty($totals))
        <table class="totals">
            @foreach ($totals as $label => $value)
                <tr>
                    <td><strong>{{ $label }}</strong></td>
                    <td>{{ $value }}</td>
                </tr>
            @endforeach
        </table>
    @endif

    <div class="footer">
        Dokumen dicetak dari sistem Dulank pada {{ now()->timezone('Asia/Jakarta')->format('d/m/Y H:i') }} WIB.
    </div>
</body>
</html>
