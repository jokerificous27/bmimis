<x-layouts::print :title="__('Weigh-in QR cards')" orientation="portrait">
    <x-slot:head>
        @vite(['resources/js/app.js'])
        <style>
            .cards { display: grid; grid-template-columns: repeat(2, 1fr); gap: 6mm; }
            .card { border: 1px dashed #777; border-radius: 3mm; padding: 4mm; display: flex; gap: 4mm; align-items: center; break-inside: avoid; page-break-inside: avoid; height: 62mm; }
            .card canvas { width: 40mm !important; height: 40mm !important; flex-shrink: 0; }
            .card .org { font-size: 8px; text-transform: uppercase; letter-spacing: .04em; color: #1d3461; font-weight: 700; }
            .card .title { font-size: 10px; font-weight: 700; margin: 1mm 0 3mm; }
            .card .name { font-size: 13px; font-weight: 700; line-height: 1.2; }
            .card .meta { font-size: 10px; color: #333; margin-top: 1mm; }
            .card .hint { font-size: 8px; color: #555; margin-top: 3mm; }
        </style>
    </x-slot:head>

    <div class="sheet portrait">
        <div class="cards">
            @forelse ($cards as $personnel)
                <div class="card">
                    <canvas data-qr="{{ $personnel->qrPayload() }}" aria-label="{{ __('Weigh-in QR code') }}"></canvas>
                    <div>
                        <div class="org">{{ $settings->get('organization_name') }}</div>
                        <div class="title">MONTHLY WEIGH-IN QR</div>
                        <div class="name">{{ $personnel->rank?->abbreviation }} {{ $personnel->formal_name }}</div>
                        <div class="meta">{{ $personnel->employee_number }}</div>
                        <div class="meta">{{ $personnel->unit?->code }} — {{ $personnel->unit?->name }}</div>
                        <div class="hint">Hold this card next to the scale display when the Health Service takes your weigh-in photo. Report a lost card so it can be re-issued.</div>
                    </div>
                </div>
            @empty
                <p class="muted">{{ __('No personnel to print.') }}</p>
            @endforelse
        </div>
    </div>

    <script type="module">
        document.querySelectorAll('canvas[data-qr]').forEach((canvas) => window.renderWeighInQr(canvas, canvas.dataset.qr, 320));
    </script>
</x-layouts::print>
