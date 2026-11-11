@props([
    'heading' => null,
    'subheading' => null,
    'padding' => true,
])

<section {{ $attributes->class('rounded-xl border border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900') }}>
    @if ($heading || isset($actions))
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-zinc-100 px-4 py-3 dark:border-zinc-800">
            <div>
                @if ($heading)
                    <flux:heading>{{ $heading }}</flux:heading>
                @endif
                @if ($subheading)
                    <flux:text size="sm">{{ $subheading }}</flux:text>
                @endif
            </div>
            @isset($actions)
                <div class="flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </header>
    @endif
    <div @class(['p-4' => $padding])>
        {{ $slot }}
    </div>
</section>
