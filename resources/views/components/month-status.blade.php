@props(['status'])

@php
    [$color, $label] = match ($status) {
        'recorded' => ['green', __('Recorded')],
        'pending' => ['amber', __('Pending')],
        'missing' => ['red', __('Missing')],
        default => ['zinc', __('Upcoming')],
    };
@endphp

<flux:badge size="sm" :color="$color" {{ $attributes }}>{{ $label }}</flux:badge>
