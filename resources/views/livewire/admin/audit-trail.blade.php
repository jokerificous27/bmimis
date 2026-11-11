<div>
    <x-page-header :title="__('Audit Trail')" :subtitle="__('Read-only record of sign-ins, record creation, corrections, certifications, exports and configuration changes.')" />

    @if ($filteredPersonnel)
        <flux:callout icon="funnel" class="mb-4">
            <flux:callout.text>{{ __('Showing entries for :name.', ['name' => $filteredPersonnel->formal_name.' ('.$filteredPersonnel->employee_number.')']) }} <flux:link wire:click="$set('personnelId', '')" class="cursor-pointer">{{ __('Clear') }}</flux:link></flux:callout.text>
        </flux:callout>
    @endif

    <x-panel :padding="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-zinc-800">
            <div class="lg:col-span-2"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search description or remarks')" clearable /></div>
            <flux:select wire:model.live="action">
                <flux:select.option value="">{{ __('All actions') }}</flux:select.option>
                @foreach ($actions as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="userId">
                <flux:select.option value="">{{ __('All users') }}</flux:select.option>
                @foreach ($users as $account)
                    <flux:select.option :value="$account->id">{{ $account->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:input type="date" wire:model.live="from" :aria-label="__('From')" />
            <flux:input type="date" wire:model.live="to" :aria-label="__('To')" />
        </div>

        <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
            @forelse ($logs as $log)
                <div class="px-4 py-3 text-sm" x-data="{ open: false }" wire:key="log-{{ $log->id }}">
                    <div class="flex flex-wrap items-start justify-between gap-2">
                        <div class="min-w-0">
                            <div class="font-medium">{{ $log->description }}</div>
                            <div class="mt-0.5 flex flex-wrap gap-x-3 text-xs text-zinc-500">
                                <span>{{ $log->created_at->format('M j, Y g:i:s A') }}</span>
                                <span>{{ $log->user?->name ?? __('System') }}</span>
                                <span class="font-mono">{{ $log->action }}</span>
                                @if ($log->subject_type)<span class="font-mono">{{ class_basename($log->subject_type) }} #{{ $log->subject_id }}</span>@endif
                                @if ($log->personnel)<a href="{{ route('personnel.show', $log->personnel_id) }}" wire:navigate class="text-navy-700 hover:underline dark:text-navy-200">{{ $log->personnel->formal_name }}</a>@endif
                                @if ($log->ip_address)<span>{{ $log->ip_address }}</span>@endif
                            </div>
                            @if ($log->remarks)<div class="mt-1 text-xs text-amber-700 dark:text-amber-400">{{ $log->remarks }}</div>@endif
                        </div>
                        @if ($log->old_values || $log->new_values)
                            <flux:button size="xs" variant="ghost" x-on:click="open = ! open" icon="eye">{{ __('Details') }}</flux:button>
                        @endif
                    </div>
                    <div x-show="open" x-cloak class="mt-2">@include('livewire.admin.partials.diff', ['log' => $log])</div>
                </div>
            @empty
                <x-empty-state icon="shield-check" :heading="__('No audit entries match the filters')" />
            @endforelse
        </div>
        @if ($logs->hasPages())
            <div class="border-t border-zinc-100 p-4 dark:border-zinc-800">{{ $logs->links() }}</div>
        @endif
    </x-panel>
</div>
