@props([
    'difference' => null,
    'trend' => null,
])

@if ($trend === 'up')
    <span {{ $attributes->class('inline-flex items-center gap-1 text-red-600 dark:text-red-400') }}>
        <flux:icon.arrow-trending-up variant="micro" /> +{{ number_format($difference, 2) }}
    </span>
@elseif ($trend === 'down')
    <span {{ $attributes->class('inline-flex items-center gap-1 text-green-700 dark:text-green-400') }}>
        <flux:icon.arrow-trending-down variant="micro" /> {{ number_format($difference, 2) }}
    </span>
@elseif ($trend === 'same')
    <span {{ $attributes->class('inline-flex items-center gap-1 text-zinc-500') }}>
        <flux:icon.minus variant="micro" /> 0.00
    </span>
@else
    <span class="text-zinc-400">—</span>
@endif
