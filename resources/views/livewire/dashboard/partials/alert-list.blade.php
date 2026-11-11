<div class="divide-y divide-zinc-100 dark:divide-zinc-800">
    @forelse ($items as $item)
        @php($subject = $person($item))
        @if ($subject)
            <a href="{{ route('personnel.show', $subject) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                <span class="min-w-0 truncate font-medium">{{ $subject->rank?->abbreviation }} {{ $subject->formal_name }}</span>
                <span @class(['shrink-0 text-xs', 'text-red-600 font-semibold' => isset($danger) && $danger($item), 'text-zinc-500' => ! (isset($danger) && $danger($item))])>{{ $detail($item) }}</span>
            </a>
        @endif
    @empty
        <x-empty-state icon="check-badge" :heading="$empty" class="py-6" />
    @endforelse
</div>
