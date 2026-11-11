<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ __('My weigh-in QR') }}</title>
    @vite(['resources/js/app.js'])
    <style>
        html, body { margin: 0; height: 100%; background: #fff; color: #111; font-family: system-ui, -apple-system, 'Segoe UI', sans-serif; }
        main { min-height: 100%; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 14px; padding: 16px; box-sizing: border-box; text-align: center; }
        canvas { image-rendering: pixelated; }
        .name { font-size: 18px; font-weight: 700; }
        .meta { font-size: 13px; color: #555; }
        .tip { font-size: 13px; color: #1d3461; max-width: 22rem; }
        a { color: #1d3461; font-size: 13px; }
    </style>
</head>
<body>
    <main>
        <canvas id="qr" aria-label="{{ __('My weigh-in QR code') }}"></canvas>
        <div>
            <div class="name">{{ $personnel->rank?->abbreviation }} {{ $personnel->formal_name }}</div>
            <div class="meta">{{ $personnel->employee_number }} · {{ $personnel->unit?->code }}</div>
        </div>
        <p class="tip">{{ __('Turn screen brightness up, then place the phone in the holder beside the scale display, screen facing the camera.') }}</p>
        <a href="{{ route('my.dashboard') }}">{{ __('Back to my dashboard') }}</a>
    </main>

    <script type="module">
        const canvas = document.getElementById('qr');
        const payload = @js($payload);

        // As large as the screen allows: bigger squares are easier to read in a full-body photo.
        const draw = () => window.renderWeighInQr(canvas, payload, Math.floor(Math.min(window.innerWidth, window.innerHeight * 0.7) - 32));
        draw();
        window.addEventListener('resize', draw);

        // Keep the screen on while the phone sits in the holder (supported browsers, HTTPS only).
        const keepAwake = () => navigator.wakeLock?.request('screen').catch(() => {});
        keepAwake();
        document.addEventListener('visibilitychange', () => document.visibilityState === 'visible' && keepAwake());
    </script>
</body>
</html>
