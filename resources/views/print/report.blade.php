<x-layouts::print :title="$report['title']" :orientation="$report['orientation']" :export-url="$exportUrl" :back-url="route('reports.index', ['category' => \App\Services\Reports\ReportCatalog::find($report['key'])['category']])">
    <x-slot:head>
        <style>
            .meta { display: flex; flex-wrap: wrap; gap: 4px 18px; margin-bottom: 8px; font-size: 11px; }
            .summary { display: grid; grid-template-columns: repeat(auto-fill, minmax(36mm, 1fr)); gap: 6px; margin: 8px 0 12px; }
            .summary div { border: 1px solid #c7ccd6; padding: 5px 7px; }
            .summary .k { font-size: 9px; color: #444; text-transform: uppercase; }
            .summary .v { font-size: 14px; font-weight: 700; }
            h3 { font-size: 12px; margin: 14px 0 5px; }
            .data th { font-size: 10px; }
            .data td { font-size: 10px; }
            .data tr:nth-child(even) td { background: #fafbfc; }
        </style>
    </x-slot:head>

    <div class="sheet {{ $report['orientation'] }}">
        @include('print.partials.letterhead')
        <div class="doc-title">{{ strtoupper($report['title']) }}</div>
        @if ($report['subtitle'])
            <div class="center" style="margin-top: -6px; margin-bottom: 10px;">{{ $report['subtitle'] }}</div>
        @endif

        <div class="meta">
            @foreach ($report['meta'] as $label => $value)
                <span><strong>{{ $label }}:</strong> {{ $value }}</span>
            @endforeach
            <span><strong>{{ __('Prepared by') }}:</strong> {{ auth()->user()->name }}</span>
            <span><strong>{{ __('Date generated') }}:</strong> {{ now()->format('F j, Y g:i A') }}</span>
        </div>

        @if ($report['summary'])
            <div class="summary">
                @foreach ($report['summary'] as $label => $value)
                    <div><div class="k">{{ $label }}</div><div class="v">{{ $value }}</div></div>
                @endforeach
            </div>
        @endif

        @foreach ($report['sections'] as $section)
            @if ($section['title'])
                <h3>{{ $section['title'] }}</h3>
            @endif
            <table class="data">
                <thead>
                    <tr>
                        @foreach ($section['columns'] as $column)
                            <th>{{ $column }}</th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @forelse ($section['rows'] as $row)
                        <tr>
                            @foreach ($row as $cell)
                                <td @class(['num' => is_int($cell) || is_float($cell)])>{{ is_float($cell) ? rtrim(rtrim(number_format($cell, 2), '0'), '.') : ($cell instanceof \BackedEnum ? $cell->value : $cell) }}</td>
                            @endforeach
                        </tr>
                    @empty
                        <tr><td colspan="{{ count($section['columns']) }}" class="center muted">{{ __('No records match the selected filters.') }}</td></tr>
                    @endforelse
                </tbody>
            </table>
        @endforeach

        <table style="margin-top: 24px; border: none;">
            <tr>
                <td style="border: none; width: 50%;" class="signature"><div class="line">{{ auth()->user()->name }}</div><div class="role">{{ __('Prepared by') }}{{ auth()->user()->designation ? ' — '.auth()->user()->designation : '' }}</div></td>
                <td style="border: none;" class="signature"><div class="line">&nbsp;</div><div class="role">{{ __('Noted by') }}</div></td>
            </tr>
        </table>

        <div class="footer-note">
            <span>{{ config('app.name') }}</span>
            <span>{{ __('Confidential health information — for official use only') }}</span>
        </div>
    </div>
</x-layouts::print>
