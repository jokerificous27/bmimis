@props([
    'icon' => 'inbox',
    'heading',
    'text' => null,
])

<div {{ $attributes->class('flex flex-col items-center justify-center px-4 py-10 text-center') }}>
    <span class="flex size-12 items-center justify-center rounded-full bg-zinc-100 text-zinc-500 dark:bg-zinc-800">
        <flux:icon :name="$icon" class="size-6" />
    </span>
    <flux:heading class="mt-3">{{ $heading }}</flux:heading>
    @if ($text)
        <flux:text class="mt-1 max-w-md">{{ $text }}</flux:text>
    @endif
    @if (! $slot->isEmpty())
        <div class="mt-4">{{ $slot }}</div>
    @endif
</div>
