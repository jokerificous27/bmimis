<div>
    <x-page-header :title="__('Unit Management')" :subtitle="__('Organizational units and offices.')">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="create">{{ __('Add unit') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:error name="delete" class="mb-4" />

    <x-panel :padding="false">
        <div class="border-b border-zinc-100 p-4 dark:border-zinc-800"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search units')" class="max-w-sm" clearable /></div>
        <div class="overflow-x-auto px-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Code') }}</flux:table.column>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Active personnel') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Representatives') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($units as $unit)
                        <flux:table.row :key="$unit->id">
                            <flux:table.cell class="font-mono">{{ $unit->code }}</flux:table.cell>
                            <flux:table.cell><div class="font-medium">{{ $unit->name }}</div><div class="text-xs text-zinc-500">{{ $unit->description }}</div></flux:table.cell>
                            <flux:table.cell align="end">{{ $unit->active_personnel_count }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $unit->representatives_count }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$unit->is_active ? 'green' : 'zinc'">{{ $unit->is_active ? __('Active') : __('Inactive') }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $unit->id }})" />
                                @if ($unit->personnel_count === 0 && $unit->representatives_count === 0)
                                    <flux:button size="xs" variant="ghost" icon="trash" wire:click="delete({{ $unit->id }})" wire:confirm="{{ __('Remove this unit?') }}" />
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="6"><x-empty-state icon="building-office" :heading="__('No units')" /></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-panel>

    <flux:modal name="unit-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit unit') : __('Add unit') }}</flux:heading>
            <flux:input wire:model="code" :label="__('Code')" required />
            <flux:input wire:model="name" :label="__('Name')" required />
            <flux:textarea wire:model="description" :label="__('Description')" rows="2" />
            <flux:switch wire:model="is_active" :label="__('Active')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
