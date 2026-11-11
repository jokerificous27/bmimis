<div class="max-w-4xl">
    <x-page-header :title="__('Rank Management')" :subtitle="__('PNP ranks. Inactive ranks remain on historical records but cannot be assigned.')">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="create">{{ __('Add rank') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :padding="false">
        <div class="overflow-x-auto px-4">
            <flux:table>
                <flux:table.columns>
                    <flux:table.column>{{ __('Order') }}</flux:table.column>
                    <flux:table.column>{{ __('Abbreviation') }}</flux:table.column>
                    <flux:table.column>{{ __('Rank') }}</flux:table.column>
                    <flux:table.column>{{ __('Category') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Personnel') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($ranks as $rank)
                        <flux:table.row :key="$rank->id">
                            <flux:table.cell>{{ $rank->sort_order }}</flux:table.cell>
                            <flux:table.cell class="font-medium">{{ $rank->abbreviation }}</flux:table.cell>
                            <flux:table.cell>{{ $rank->name }}</flux:table.cell>
                            <flux:table.cell>{{ $rank->category }}</flux:table.cell>
                            <flux:table.cell align="end">{{ $rank->personnel_count }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$rank->is_active ? 'green' : 'zinc'">{{ $rank->is_active ? __('Active') : __('Inactive') }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end"><flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $rank->id }})" /></flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </x-panel>

    <flux:modal name="rank-form" class="md:w-[28rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit rank') : __('Add rank') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="abbreviation" :label="__('Abbreviation')" required />
                <flux:input wire:model="sort_order" type="number" :label="__('Display order')" required />
            </div>
            <flux:input wire:model="name" :label="__('Rank name')" required />
            <flux:select wire:model="category" :label="__('Category')">
                @foreach ($categories as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:switch wire:model="is_active" :label="__('Active')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
