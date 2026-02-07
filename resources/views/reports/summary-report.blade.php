<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
body { font-family: DejaVu Sans, sans-serif; font-size: 9px; margin: 14px; }
h1 { font-size: 12px; margin: 0 0 6px 0; }
</style>
</head>
<body>
<p style="font-weight:bold; font-size:10px;">{{ $summaryHeaderData['businessName'] ?? '' }}</p>
<h1>{{ $summaryHeaderData['title'] ?? 'Summary Report' }}</h1>
<p>Period: {{ $periodLabel }}</p>
<p>Generated: {{ $generatedAt }}</p>

@if(!empty($summaryHeaderData['summaryRows']))
<table style="margin-bottom:16px; border-collapse:collapse;">
    <thead>
        <tr>
            <th style="text-align:left; padding:4px 8px; border:1px solid #ddd;">Summary</th>
            <th style="border:1px solid #ddd;"></th>
        </tr>
    </thead>
    <tbody>
        @foreach($summaryHeaderData['summaryRows'] as $pair)
        <tr>
            <td style="padding:4px 8px; border:1px solid #ddd;">{{ $pair[0] ?? '' }}</td>
            <td style="padding:4px 8px; border:1px solid #ddd;">{{ $pair[1] ?? '' }}</td>
        </tr>
        @endforeach
    </tbody>
</table>
@endif

<table style="width:100%; border-collapse:collapse;">
    <thead>
        <tr>
            @foreach($headers as $header)
            <th style="text-align:left; padding:6px 8px; border:1px solid #ddd; background:#0f172a; color:#fff;">{{ $header }}</th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        @foreach($records as $row)
        <tr>
            @foreach($row as $cell)
            <td style="padding:4px 8px; border:1px solid #ddd;">{{ $cell }}</td>
            @endforeach
        </tr>
        @endforeach
    </tbody>
</table>

<p style="margin-top:12px; font-size:8px;">Generated: {{ $generatedAt }}</p>
</body>
</html>
