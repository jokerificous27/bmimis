<details class="group rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900" @if (! $collapsed) open @endif>
    <summary class="flex cursor-pointer list-none items-center justify-between gap-2 px-4 py-3 text-sm font-medium">
        <span class="flex items-center gap-2"><flux:icon.camera class="size-5 text-navy-700 dark:text-navy-200" /> {{ __('How to take the weigh-in photo') }}</span>
        <flux:icon.chevron-down class="size-4 transition group-open:rotate-180" />
    </summary>

    <div class="grid gap-4 border-t border-zinc-100 p-4 sm:grid-cols-[11rem_1fr] dark:border-zinc-800">
        {{-- Illustration of the expected framing: whole body, scale head with display, phone QR in a holder beside it. --}}
        <svg viewBox="0 0 220 300" class="mx-auto w-44 text-zinc-400" role="img" aria-label="{{ __('Personnel standing on the scale, phone with QR code in a holder beside the scale display') }}">
            <rect x="1" y="1" width="218" height="298" rx="10" fill="none" stroke="currentColor" stroke-dasharray="5 4" />
            <circle cx="100" cy="48" r="20" fill="#cbd5e1" />
            <path d="M68 76h64l8 70h-14l-6 92H86l-6-92H66z" fill="#94a3b8" />
            <rect x="72" y="238" width="56" height="10" rx="2" fill="#475569" />
            <rect x="94" y="200" width="12" height="40" fill="#64748b" />
            <rect x="58" y="176" width="86" height="30" rx="5" fill="#e2e8f0" stroke="#64748b" />
            <rect x="64" y="182" width="36" height="16" rx="2" fill="#111827" />
            <text x="82" y="194" text-anchor="middle" font-size="10" font-family="monospace" fill="#ef4444">68.95</text>
            <rect x="104" y="182" width="34" height="16" rx="2" fill="#111827" />
            <rect x="152" y="166" width="30" height="50" rx="4" fill="#1f2937" />
            <rect x="155" y="172" width="24" height="34" fill="#fff" />
            <g fill="#111827">
                <rect x="157" y="175" width="6" height="6" /><rect x="171" y="175" width="6" height="6" /><rect x="157" y="197" width="6" height="6" />
                <rect x="165" y="183" width="3" height="3" /><rect x="169" y="188" width="4" height="3" /><rect x="164" y="192" width="3" height="4" /><rect x="172" y="198" width="4" height="4" />
            </g>
            <rect x="160" y="216" width="14" height="30" fill="#94a3b8" />
            <path d="M150 168 l-8 -10" stroke="#2f8f5b" stroke-width="2" />
            <text x="110" y="276" text-anchor="middle" font-size="11" fill="currentColor">{{ __('whole body + display + QR') }}</text>
        </svg>

        <ol class="list-decimal space-y-1.5 ps-5 text-sm text-zinc-700 dark:text-zinc-300">
            <li>{{ __('Personnel opens "My Weigh-in QR" on their phone (brightness up) and places it in the holder beside the scale display, screen facing the camera.') }}</li>
            <li>{{ __('Personnel stands on the scale. Wait until the weight stops changing.') }}</li>
            <li>{{ __('Stand about 1.5–2 m in front. Frame the whole body; the scale display and the phone must be clearly visible and not cut off.') }}</li>
            <li>{{ __('Open the Smart camera and tap the scale display on the screen once. Hold the phone straight and tilt it slightly if there is glare, until both labels turn green: "Personnel identified" and "Weight picked up". The photo is then taken automatically (or tap Capture).') }}</li>
        </ol>
    </div>
</details>
