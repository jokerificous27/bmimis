@php
    $record = $this->record;
    $baseline = $record->baselineAssessment;
    $change = (float) $record->bmi_change;
    $last = collect(['waist' => $baseline?->waist_cm, 'hip' => $baseline?->hip_cm, 'wrist' => $baseline?->wrist_cm])
        ->map(fn ($value, $name) => __($name).' '.($value !== null ? number_format($value, 1).' cm' : '—'))
        ->implode(' · ');
@endphp

<div>
    @if ($outcome === 'updated')
        <div class="flex flex-wrap items-center gap-2 rounded-xl border border-green-300 bg-green-50 px-4 py-3 text-sm text-green-900 dark:border-green-800 dark:bg-green-950/40 dark:text-green-200">
            <flux:icon.check-circle variant="mini" class="shrink-0" />
            <span>{{ __('Waist, hip and wrist updated for :name.', ['name' => $record->personnel->rank_name]) }}</span>
            @if ($assessmentId)
                <flux:link :href="route('assessments.show', $assessmentId)" wire:navigate class="text-sm">{{ __('Open the new assessment') }}</flux:link>
            @endif
        </div>
    @elseif ($outcome === 'declined')
        <div class="rounded-xl border border-zinc-200 bg-zinc-50 px-4 py-3 text-sm text-zinc-700 dark:border-zinc-700 dark:bg-zinc-800 dark:text-zinc-300">
            {{ __('No measurement update for :name. The previous waist, hip and wrist stay on record.', ['name' => $record->personnel->rank_name]) }}
        </div>
    @elseif ($record->needsMeasurementReview())
        <div class="rounded-xl border-2 border-amber-400 bg-amber-50 p-4 dark:bg-amber-950/30">
            <div class="flex items-start gap-3">
                <flux:icon.arrows-up-down class="mt-0.5 size-6 shrink-0 text-amber-600" />
                <div class="min-w-0 flex-1">
                    <div class="font-semibold text-amber-950 dark:text-amber-100">
                        {{ __(':name — BMI changed by :change', ['name' => $record->personnel->rank_name, 'change' => ($change > 0 ? '+' : '').number_format($change, 1)]) }}
                    </div>
                    <div class="mt-0.5 text-sm text-amber-900 dark:text-amber-200">
                        {{ __('From :old at the assessment of :date to :new at the :month weigh-in.', [
                            'old' => $baseline ? number_format($baseline->bmi, 1) : '—',
                            'date' => $baseline?->assessed_on->format('M j, Y') ?? '—',
                            'new' => number_format((float) $record->bmi, 1),
                            'month' => $record->monthName().' '.$record->year,
                        ]) }}
                        {{ __('Last measured: :measurements.', ['measurements' => $last]) }}
                    </div>

                    @if (! $this->canReview)
                        <div class="mt-2 text-sm text-amber-900 dark:text-amber-200">{{ __('The Health Service has been asked whether to update the waist, hip and wrist.') }}</div>
                    @elseif (! $editing)
                        <div class="mt-2 text-sm font-medium text-amber-950 dark:text-amber-100">{{ __('Do you want to update the waist, hip and wrist too?') }}</div>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <flux:button size="sm" variant="primary" icon="pencil-square" wire:click="startUpdate">{{ __('Yes, update measurements') }}</flux:button>
                            <flux:button size="sm" wire:click="decline" wire:confirm="{{ __('Keep the previous waist, hip and wrist on record?') }}">{{ __('No update needed') }}</flux:button>
                        </div>
                    @else
                        <form wire:submit="save" class="mt-3 space-y-3">
                            <div class="grid gap-3 sm:grid-cols-3">
                                <flux:input wire:model="waist_cm" :label="__('Waist (cm)')" type="number" step="0.1" inputmode="decimal" />
                                <flux:input wire:model="hip_cm" :label="__('Hip (cm)')" type="number" step="0.1" inputmode="decimal" />
                                <flux:input wire:model="wrist_cm" :label="__('Wrist (cm)')" type="number" step="0.1" inputmode="decimal" />
                            </div>
                            <flux:text size="xs">{{ __('The last values are filled in; change the ones you measured again. They are saved as a new assessment dated :date with the weigh-in weight of :weight kg, so the earlier assessment stays unchanged.', ['date' => $record->measurementDate()->format('M j, Y'), 'weight' => number_format($record->weight_kg, 2)]) }}</flux:text>
                            <flux:error name="assessed_on" />
                            <div class="flex flex-wrap gap-2">
                                <flux:button type="submit" size="sm" variant="primary" icon="check">{{ __('Save measurements') }}</flux:button>
                                <flux:button size="sm" variant="ghost" wire:click="cancelUpdate">{{ __('Cancel') }}</flux:button>
                            </div>
                        </form>
                    @endif
                </div>
            </div>
        </div>
    @endif
</div>
