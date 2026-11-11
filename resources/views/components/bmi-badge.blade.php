@props([
    'label' => null,
    'category' => null,
    'size' => 'sm',
])

@php
    $category = $category instanceof \App\Enums\ClassificationCategory ? $category : \App\Enums\ClassificationCategory::tryFrom((string) $category);
@endphp

@if ($label)
    <flux:badge :size="$size" :color="$category?->color() ?? 'zinc'" {{ $attributes }}>{{ $label }}</flux:badge>
@else
    <span class="text-zinc-400">—</span>
@endif
