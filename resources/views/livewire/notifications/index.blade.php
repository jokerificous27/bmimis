<div class="max-w-3xl">
    <x-page-header :title="__('Notifications')" :subtitle="__(':count unread', ['count' => $unreadCount])">
        <x-slot:actions>
            <flux:switch wire:model.live="unreadOnly" :label="__('Unread only')" />
            @if ($unreadCount)
                <flux:button size="sm" wire:click="markAllRead">{{ __('Mark all as read') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-panel :padding="false">
        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($notifications as $notification)
                @php
                    $data = $notification->data;
                    $tone = match ($data['level'] ?? 'info') {
                        'success' => 'bg-green-50 text-green-700 dark:bg-green-900/30 dark:text-green-300',
                        'warning' => 'bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
                        default => 'bg-navy-50 text-navy-800 dark:bg-navy-900/40 dark:text-navy-200',
                    };
                @endphp
                <button type="button" wire:click="open('{{ $notification->id }}')" @class(['flex w-full gap-3 p-4 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800', 'bg-navy-50/40 dark:bg-navy-900/10' => ! $notification->read_at])>
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $tone }}">
                        <flux:icon :name="$data['icon'] ?? 'bell'" class="size-5" />
                    </span>
                    <span class="min-w-0 flex-1">
                        <span class="flex items-center justify-between gap-2">
                            <span @class(['text-sm', 'font-semibold' => ! $notification->read_at, 'font-medium' => $notification->read_at])>{{ $data['title'] ?? __('Notification') }}</span>
                            <span class="shrink-0 text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</span>
                        </span>
                        <span class="mt-0.5 block text-sm text-zinc-600 dark:text-zinc-300">{{ $data['message'] ?? '' }}</span>
                    </span>
                </button>
            @empty
                <x-empty-state icon="bell" :heading="__('You have no notifications')" />
            @endforelse
        </div>
        @if ($notifications->hasPages())
            <div class="border-t border-zinc-100 p-4 dark:border-zinc-800">{{ $notifications->links() }}</div>
        @endif
    </x-panel>
</div>
