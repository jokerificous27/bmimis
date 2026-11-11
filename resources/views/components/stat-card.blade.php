@props([
    'label',
    'value',
    'icon' => null,
    'tone' => 'navy',
    'hint' => null,
    'href' => null,
])

@php
    $tones = [
        'navy' => 'bg-navy-50 text-navy-800 dark:bg-navy-900/40 dark:text-navy-200',
        'green' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
        'amber' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
        'red' => 'bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300',
        'zinc' => 'bg-zinc-100 text-zinc-600 dark:bg-zinc-700 dark:text-zinc-300',
    ];
    $tag = $href ? 'a' : 'div';
@endphp

<{{ $tag }} @if ($href) href="{{ $href }}" wire:navigate @endif {{ $attributes->class(['flex items-start gap-3 rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900', 'transition hover:border-navy-200 hover:shadow-sm' => $href]) }}>
    @if ($icon)
        <span class="flex size-10 shrink-0 items-center justify-center rounded-lg {{ $tones[$tone] ?? $tones['navy'] }}">
            <flux:icon :name="$icon" class="size-5" />
        </span>
    @endif
    <div class="min-w-0">
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500 dark:text-zinc-400">{{ $label }}</div>
        <div class="mt-1 text-2xl font-semibold tabular-nums text-zinc-900 dark:text-white">{{ $value }}</div>
        @if ($hint)
            <div class="mt-0.5 text-xs text-zinc-500 dark:text-zinc-400">{{ $hint }}</div>
        @endif
    </div>
</{{ $tag }}>
