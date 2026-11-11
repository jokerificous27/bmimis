@props([
    'title',
    'subtitle' => null,
])

<div {{ $attributes->class('mb-6 flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between') }}>
    <div class="min-w-0">
        @isset($breadcrumbs)
            <div class="mb-2">{{ $breadcrumbs }}</div>
        @endisset
        <flux:heading size="xl" level="1" class="text-navy-900 dark:text-white">{{ $title }}</flux:heading>
        @if ($subtitle)
            <flux:text class="mt-1">{{ $subtitle }}</flux:text>
        @endif
    </div>

    @isset($actions)
        <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
    @endisset
</div>
