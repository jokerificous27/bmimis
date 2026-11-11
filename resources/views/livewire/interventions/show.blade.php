@php
    $i = $intervention;
    $ongoing = $i->status === \App\Enums\InterventionStatus::Ongoing;
@endphp

<div>
    <x-page-header :title="$i->package->displayName().' — '.$i->package->name" :subtitle="$i->personnel->rank_name.' · '.($i->personnel->unit?->name ?? '')">
        <x-slot:breadcrumbs><flux:link :href="route('interventions.index')" wire:navigate class="text-sm">← {{ __('Intervention Management') }}</flux:link></x-slot:breadcrumbs>
        <x-slot:actions>
            <flux:badge :color="$i->status->color()">{{ $i->status->label() }}</flux:badge>
            <flux:button size="sm" variant="ghost" :href="route('personnel.show', $i->personnel)" wire:navigate>{{ __('Open profile') }}</flux:button>
            @if ($canManage && $ongoing)
                <flux:modal.trigger name="close-intervention"><flux:button size="sm">{{ __('Complete / discontinue') }}</flux:button></flux:modal.trigger>
                <flux:modal.trigger name="record-follow-up"><flux:button size="sm" variant="primary" icon="plus">{{ __('Record follow-up') }}</flux:button></flux:modal.trigger>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Assigned')" :value="$i->assigned_on->format('M j, Y')" icon="calendar" :hint="__('by :name', ['name' => $i->assigner?->name ?? '—'])" />
        <x-stat-card :label="__('Next follow-up')" :value="$i->next_follow_up_on?->format('M j, Y') ?? '—'" icon="calendar-days" :tone="$i->isFollowUpOverdue() ? 'red' : 'navy'" :hint="$i->isFollowUpOverdue() ? __('Overdue') : null" />
        <x-stat-card :label="__('Starting weight')" :value="$i->starting_weight_kg ? number_format($i->starting_weight_kg, 2).' kg' : '—'" icon="scale" tone="zinc" />
        <x-stat-card :label="__('Target weight')" :value="$i->target_weight_kg ? number_format($i->target_weight_kg, 2).' kg' : '—'" icon="check-badge" tone="green" />
    </div>

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-panel :heading="__('Progress')">
                <x-chart :config="$chart" height="14rem" />
            </x-panel>

            <x-panel :heading="__('Follow-up records')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($i->followUps as $followUp)
                        <div class="flex flex-wrap items-start justify-between gap-2 p-4 text-sm">
                            <div>
                                <div class="font-medium">{{ $followUp->follow_up_on->format('F j, Y') }}</div>
                                @if ($followUp->notes)<div class="mt-1 text-zinc-600 dark:text-zinc-300">{{ $followUp->notes }}</div>@endif
                                <div class="mt-1 text-xs text-zinc-500">{{ __('Recorded by :name', ['name' => $followUp->recorder?->name ?? '—']) }}</div>
                            </div>
                            <div class="font-semibold tabular-nums">{{ $followUp->weight_kg ? number_format($followUp->weight_kg, 2).' kg' : '—' }}</div>
                        </div>
                    @empty
                        <x-empty-state icon="calendar-days" :heading="__('No follow-ups recorded yet')" />
                    @endforelse
                </div>
            </x-panel>

            @if (! $ongoing)
                <x-panel :heading="__('Outcome')">
                    <flux:text>{{ __(':status on :date', ['status' => $i->status->label(), 'date' => $i->completed_on?->format('F j, Y') ?? '—']) }}</flux:text>
                    <p class="mt-2 text-sm">{{ $i->outcome }}</p>
                </x-panel>
            @endif
        </div>

        <div class="space-y-6">
            <x-panel :heading="__('Package details')">
                <dl class="space-y-3 text-sm">
                    @foreach ([__('Description') => $i->package->description, __('Requirements') => $i->package->requirements, __('Recommended activity') => $i->package->recommended_activity, __('Nutrition guidance') => $i->package->nutrition_guidance] as $label => $value)
                        @if ($value)
                            <div><dt class="text-xs text-zinc-500">{{ $label }}</dt><dd>{{ $value }}</dd></div>
                        @endif
                    @endforeach
                    <div><dt class="text-xs text-zinc-500">{{ __('Follow-up schedule') }}</dt><dd>{{ $i->package->follow_up_interval_days ? __('Every :days days', ['days' => $i->package->follow_up_interval_days]) : '—' }}</dd></div>
                    @if ($i->assessment)
                        <div><dt class="text-xs text-zinc-500">{{ __('Based on assessment') }}</dt><dd><a href="{{ route('assessments.show', $i->assessment) }}" wire:navigate class="text-navy-800 underline dark:text-navy-200">{{ $i->assessment->assessed_on->format('M j, Y') }} · BMI {{ number_format($i->assessment->bmi, 1) }}</a></dd></div>
                    @endif
                </dl>
            </x-panel>

            <x-panel :heading="__('Health Service notes')">
                <form wire:submit="saveNotes" class="space-y-3">
                    <flux:textarea wire:model="healthServiceNotes" rows="4" :disabled="! $canManage" :label="__('Notes')" />
                    <flux:textarea wire:model="remarks" rows="2" :disabled="! $canManage" :label="__('Remarks')" />
                    @if ($canManage)
                        <div class="text-end"><flux:button type="submit" size="sm">{{ __('Save notes') }}</flux:button></div>
                    @endif
                </form>
            </x-panel>
        </div>
    </div>

    <flux:modal name="record-follow-up" class="md:w-[28rem]">
        <form wire:submit="recordFollowUp" class="space-y-5">
            <flux:heading size="lg">{{ __('Record follow-up') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model="followUpOn" :label="__('Follow-up date')" required />
                <flux:input wire:model="followUpWeight" type="number" step="0.01" :label="__('Weight (kg)')" />
            </div>
            <flux:textarea wire:model="followUpNotes" :label="__('Progress notes')" rows="3" />
            <flux:input type="date" wire:model="nextFollowUpOn" :label="__('Next follow-up')" :description="__('Leave blank if no further follow-up is scheduled')" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="close-intervention" class="md:w-[28rem]">
        <form wire:submit="close" class="space-y-5">
            <flux:heading size="lg">{{ __('Close intervention') }}</flux:heading>
            <flux:radio.group wire:model="closeStatus" :label="__('Result')">
                <flux:radio value="completed" :label="__('Completed')" />
                <flux:radio value="discontinued" :label="__('Discontinued')" />
            </flux:radio.group>
            <flux:input type="date" wire:model="closedOn" :label="__('Date')" required />
            <flux:textarea wire:model="outcome" :label="__('Outcome')" rows="3" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Close intervention') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
