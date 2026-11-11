<div>
    <x-page-header :title="$mine ? __('My Assessment History') : __('BMI Assessments')" :subtitle="$mine ? __('Every BMI assessment recorded for you by the Health Service.') : __('Full BMI assessments based on the PNP Health Service BMI Form.')">
        <x-slot:actions>
            @if (! $mine)
                @if ($canCertify && $pendingCertification)
                    <flux:button size="sm" icon="check-badge" wire:click="$set('status', 'submitted')">{{ __(':count awaiting certification', ['count' => $pendingCertification]) }}</flux:button>
                @endif
                @can('create', \App\Models\BmiAssessment::class)
                    <flux:button size="sm" variant="primary" icon="plus" :href="route('assessments.create')" wire:navigate>{{ __('New assessment') }}</flux:button>
                @endcan
            @endif
        </x-slot:actions>
    </x-page-header>

    <x-panel :padding="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-zinc-800">
            @unless ($mine)
                <div class="sm:col-span-2">
                    <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search personnel')" clearable />
                </div>
                <flux:select wire:model.live="unitId">
                    <flux:select.option value="">{{ __('All units') }}</flux:select.option>
                    @foreach ($this->unitOptions as $unit)
                        <flux:select.option :value="$unit->id">{{ $unit->code }}</flux:select.option>
                    @endforeach
                </flux:select>
            @endunless
            <flux:select wire:model.live="status">
                <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                @foreach ($statuses as $option)
                    <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="category">
                <flux:select.option value="">{{ __('All classifications') }}</flux:select.option>
                @foreach ($categories as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
            <div class="flex gap-2">
                <flux:input type="date" wire:model.live="from" :aria-label="__('From')" />
                <flux:input type="date" wire:model.live="to" :aria-label="__('To')" />
            </div>
        </div>

        <div class="overflow-x-auto px-4">
            <flux:table :paginate="$assessments">
                <flux:table.columns>
                    <flux:table.column>{{ __('Date') }}</flux:table.column>
                    @unless ($mine)
                        <flux:table.column>{{ __('Personnel') }}</flux:table.column>
                        <flux:table.column>{{ __('Unit') }}</flux:table.column>
                    @endunless
                    <flux:table.column align="end">{{ __('Height') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('Weight') }}</flux:table.column>
                    <flux:table.column align="end">{{ __('BMI') }}</flux:table.column>
                    <flux:table.column>{{ __('PNP') }}</flux:table.column>
                    <flux:table.column>{{ __('WHO') }}</flux:table.column>
                    <flux:table.column>{{ __('Weight to lose') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    @unless ($mine)
                        <flux:table.column>{{ __('Encoder') }}</flux:table.column>
                    @endunless
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @forelse ($assessments as $assessment)
                        <flux:table.row :key="$assessment->id">
                            <flux:table.cell>{{ $assessment->assessed_on->format('M j, Y') }}</flux:table.cell>
                            @unless ($mine)
                                <flux:table.cell><a href="{{ $this->personnelUrl($assessment->personnel) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-navy-200">{{ $assessment->personnel->rank?->abbreviation }} {{ $assessment->personnel->formal_name }}</a></flux:table.cell>
                                <flux:table.cell>{{ $assessment->unit?->code ?? '—' }}</flux:table.cell>
                            @endunless
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($assessment->height_cm, 1) }}</flux:table.cell>
                            <flux:table.cell align="end" class="tabular-nums">{{ number_format($assessment->weight_kg, 2) }}</flux:table.cell>
                            <flux:table.cell align="end" class="font-semibold tabular-nums">{{ number_format($assessment->bmi, 1) }}</flux:table.cell>
                            <flux:table.cell><x-bmi-badge :label="$assessment->pnp_classification" :category="$assessment->pnp_category" /></flux:table.cell>
                            <flux:table.cell><x-bmi-badge :label="$assessment->who_classification" :category="$assessment->who_category" /></flux:table.cell>
                            <flux:table.cell>{{ $assessment->weightActionText() }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$assessment->status->color()">{{ $assessment->status->label() }}</flux:badge></flux:table.cell>
                            @unless ($mine)
                                <flux:table.cell>{{ $assessment->encoder?->name ?? '—' }}</flux:table.cell>
                            @endunless
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="eye" :href="$this->showUrl($assessment)" wire:navigate :tooltip="__('View')" />
                                @can('print', $assessment)
                                    <flux:button size="xs" variant="ghost" icon="printer" :href="route('print.bmi-form', $assessment)" target="_blank" :tooltip="__('Print BMI Form')" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row><flux:table.cell colspan="12"><x-empty-state icon="clipboard-document-list" :heading="__('No assessments found')" /></flux:table.cell></flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-panel>
</div>
