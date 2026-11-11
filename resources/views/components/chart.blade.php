@props([
    'config',
    'height' => '16rem',
])

{{-- Keyed by a hash of the configuration so Livewire re-creates the chart whenever the underlying data changes. --}}
<div wire:ignore wire:key="chart-{{ md5(json_encode($config)) }}" x-data="bmiChart(@js($config))" {{ $attributes->class('relative w-full') }} style="height: {{ $height }}">
    <canvas x-ref="canvas" role="img" aria-label="{{ $attributes->get('aria-label', __('Chart')) }}"></canvas>
</div>
