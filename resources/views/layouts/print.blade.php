@props([
    'title',
    'orientation' => 'portrait',
    'backUrl' => null,
    'exportUrl' => null,
])
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    <style>
        @page { size: A4 {{ $orientation }}; margin: 12mm; }
        * { box-sizing: border-box; }
        html, body { margin: 0; padding: 0; }
        body { font-family: Arial, Helvetica, sans-serif; font-size: 11px; color: #111; background: #e5e7eb; }
        .toolbar { position: sticky; top: 0; z-index: 10; display: flex; flex-wrap: wrap; gap: 8px; align-items: center; justify-content: space-between; padding: 10px 16px; background: #1d3461; color: #fff; }
        .toolbar .title { font-weight: 600; font-size: 13px; }
        .toolbar .actions { display: flex; gap: 8px; flex-wrap: wrap; }
        .toolbar a, .toolbar button { font: inherit; font-size: 12px; padding: 7px 12px; border-radius: 6px; border: 1px solid rgba(255,255,255,.35); background: transparent; color: #fff; text-decoration: none; cursor: pointer; }
        .toolbar .primary { background: #fff; color: #1d3461; border-color: #fff; font-weight: 600; }
        .toolbar .hint { font-size: 11px; opacity: .75; }
        .sheet { background: #fff; margin: 16px auto; padding: 12mm; box-shadow: 0 1px 4px rgba(0,0,0,.15); }
        .sheet.portrait { width: 210mm; min-height: 297mm; }
        .sheet.landscape { width: 297mm; min-height: 210mm; }
        .page-break { page-break-after: always; break-after: page; }
        .letterhead { text-align: center; line-height: 1.35; margin-bottom: 10px; }
        .letterhead .small { font-size: 11px; }
        .letterhead .org { font-weight: 700; font-size: 12px; letter-spacing: .02em; }
        .letterhead .facility { font-weight: 700; font-size: 12px; margin-top: 6px; }
        .doc-title { text-align: center; font-weight: 700; font-size: 15px; letter-spacing: .04em; margin: 10px 0 12px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { border: 1px solid #333; padding: 4px 6px; vertical-align: top; }
        th { background: #eef1f6; text-align: left; font-weight: 700; }
        .muted { color: #555; }
        .label { font-size: 10px; color: #444; }
        .value { font-size: 12px; font-weight: 600; min-height: 16px; }
        .center { text-align: center; }
        .right { text-align: right; }
        .num { text-align: right; font-variant-numeric: tabular-nums; }
        .signature { padding-top: 26px; text-align: center; }
        .signature .line { border-top: 1px solid #111; margin: 0 12px; padding-top: 3px; font-weight: 700; text-transform: uppercase; }
        .signature .role { font-size: 10px; color: #333; }
        .footer-note { margin-top: 10px; font-size: 9px; color: #555; display: flex; justify-content: space-between; }
        .cut-line { border-top: 1px dashed #777; margin: 16px 0; }
        @media screen and (max-width: 900px) {
            .sheet.portrait, .sheet.landscape { width: auto; min-height: 0; margin: 8px; padding: 12px; overflow-x: auto; }
        }
        @media print {
            body { background: #fff; }
            .toolbar { display: none !important; }
            .sheet { margin: 0; padding: 0; box-shadow: none; width: auto !important; min-height: 0 !important; }
        }
    </style>
    {{ $head ?? '' }}
</head>
<body>
    <div class="toolbar">
        <div>
            <div class="title">{{ __('Print preview') }} — {{ $title }}</div>
            <div class="hint">{{ __('A4 :orientation. Use "Print" and choose "Save as PDF" as the destination to export a PDF.', ['orientation' => $orientation]) }}</div>
        </div>
        <div class="actions">
            @if ($backUrl)
                <a href="{{ $backUrl }}">{{ __('Back') }}</a>
            @else
                <button type="button" onclick="history.length > 1 ? history.back() : window.close()">{{ __('Back') }}</button>
            @endif
            @if ($exportUrl)
                <a href="{{ $exportUrl }}">{{ __('Export Excel') }}</a>
            @endif
            <button type="button" class="primary" onclick="window.print()">{{ __('Print / Save as PDF') }}</button>
        </div>
    </div>

    {{ $slot }}
</body>
</html>
