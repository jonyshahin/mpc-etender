<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Evaluation Report — {{ $tender->reference_number }}</title>
    <style>
        body { font-family: DejaVu Sans, sans-serif; font-size: 12px; color: #333; }
        h1 { font-size: 18px; border-bottom: 2px solid #1a56db; padding-bottom: 8px; }
        h2 { font-size: 14px; margin-top: 24px; color: #1a56db; }
        table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        th, td { border: 1px solid #ddd; padding: 6px 8px; text-align: left; }
        th { background-color: #f3f4f6; font-weight: bold; }
        .rank-1 { background-color: #dcfce7; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .meta { color: #666; font-size: 11px; }
    </style>
</head>
<body>
    <h1>Evaluation Report</h1>
    <p><strong>Tender:</strong> {{ $tender->reference_number }} — {{ $tender->title_en }}</p>
    <p class="meta">Generated: {{ now()->format('Y-m-d H:i') }}</p>

    <h2>Final Ranking</h2>
    <table>
        <thead>
            <tr>
                <th class="text-center">Rank</th>
                <th>Vendor</th>
                <th class="text-right">Technical Score</th>
                <th class="text-right">Financial Score</th>
                <th class="text-right">Final Score</th>
            </tr>
        </thead>
        <tbody>
            @foreach($ranking as $row)
            <tr class="{{ $row['rank'] === 1 ? 'rank-1' : '' }}">
                <td class="text-center">{{ $row['rank'] }}</td>
                <td>{{ $row['vendor_name'] }}</td>
                <td class="text-right">{{ number_format($row['technical_score'], 2) }}</td>
                <td class="text-right">{{ number_format($row['financial_score'], 2) }}</td>
                <td class="text-right"><strong>{{ number_format($row['final_score'], 2) }}</strong></td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <p class="meta" style="margin-top: 24px;">This report is system-generated. Scores represent the weighted average across all committee evaluators.</p>
</body>
</html>
