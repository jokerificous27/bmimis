<div>
    <x-page-header :title="__('Intervention Packages')" :subtitle="__('Encode the official package descriptions and requirements issued by the PNP Health Service.')">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="create">{{ __('Add package') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-4 md:grid-cols-2 xl:grid-cols-3">
        @forelse ($packages as $package)
            <x-panel :heading="$package->displayName().' — '.$package->name">
                <x-slot:actions>
                    <flux:badge size="sm" :color="$package->is_active ? 'green' : 'zinc'">{{ $package->is_active ? __('Active') : __('Inactive') }}</flux:badge>
                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $package->id }})" />
                </x-slot:actions>
                <dl class="space-y-2 text-sm">
                    <div><dt class="text-xs text-zinc-500">{{ __('Description') }}</dt><dd>{{ $package->description ?: '—' }}</dd></div>
                    @if ($package->recommended_activity)<div><dt class="text-xs text-zinc-500">{{ __('Recommended activity') }}</dt><dd>{{ $package->recommended_activity }}</dd></div>@endif
                    @if ($package->nutrition_guidance)<div><dt class="text-xs text-zinc-500">{{ __('Nutrition guidance') }}</dt><dd>{{ $package->nutrition_guidance }}</dd></div>@endif
                    <div class="flex gap-6 pt-1 text-xs text-zinc-500">
                        <span>{{ __('Follow-up every :days days', ['days' => $package->follow_up_interval_days ?? '—']) }}</span>
                        <span>{{ __(':count ongoing / :total total', ['count' => $package->ongoing_count, 'total' => $package->assignments_count]) }}</span>
                    </div>
                </dl>
            </x-panel>
        @empty
            <x-panel class="md:col-span-2 xl:col-span-3"><x-empty-state icon="rectangle-stack" :heading="__('No intervention packages')" /></x-panel>
        @endforelse
    </div>

    <flux:modal name="package-form" class="md:w-[36rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit package') : __('Add package') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="code" :label="__('Code')" placeholder="B" required />
                <div class="sm:col-span-2"><flux:input wire:model="name" :label="__('Name')" required /></div>
            </div>
            <flux:textarea wire:model="description" :label="__('Official description')" rows="3" />
            <flux:textarea wire:model="requirements" :label="__('Requirements')" rows="2" />
            <flux:textarea wire:model="recommended_activity" :label="__('Recommended activity')" rows="2" />
            <flux:textarea wire:model="nutrition_guidance" :label="__('Nutrition guidance')" rows="2" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="follow_up_interval_days" type="number" :label="__('Follow-up interval (days)')" />
                <flux:input wire:model="duration_weeks" type="number" :label="__('Duration (weeks)')" />
            </div>
            <flux:switch wire:model="is_active" :label="__('Active (can be assigned)')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
