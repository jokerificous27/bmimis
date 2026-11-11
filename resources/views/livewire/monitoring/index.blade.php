<div>
    <x-page-header :title="__('Monthly Weight Monitoring')" :subtitle="__('Encode the monthly weigh-in of personnel. Each personnel can have only one record per month.')">
        <x-slot:actions>
            @can('reports.generate')
                <flux:button size="sm" icon="printer" :href="route('reports.print', ['report' => 'monthly-monitoring', 'year' => $year, 'month' => $month, 'unit_id' => $unitId])" target="_blank">{{ __('Monthly report') }}</flux:button>
            @endcan
            @can('monitoring.encode')
                <flux:button size="sm" icon="qr-code" :href="route('print.qr-cards', array_filter(['unit_id' => $unitId]))" target="_blank">{{ __('Print QR cards') }}</flux:button>
                <flux:button size="sm" variant="primary" icon="camera" :href="route('monitoring.station')" wire:navigate>{{ __('Weigh-in Station') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        <x-stat-card :label="__('Active personnel')" :value="$summary['total']" icon="users" />
        <x-stat-card :label="__('Recorded')" :value="$summary['recorded']" icon="check-badge" tone="green" />
        <x-stat-card :label="__('Not yet recorded')" :value="$summary['pending']" icon="exclamation-triangle" :tone="$periodStatus === 'missing' ? 'red' : 'amber'" />
        <x-stat-card :label="__('Compliance')" :value="$summary['percent'].'%'" icon="chart-bar" :hint="__('Deadline: :date', ['date' => $deadline->format('M j, Y')])" />
    </div>

    <x-panel :padding="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-zinc-800">
            <flux:select wire:model.live="year" :label="__('Year')">
                @foreach ($this->yearOptions() as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="month" :label="__('Month')">
                @foreach ($this->monthOptions() as $number => $name)
                    <flux:select.option :value="$number">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="unitId" :label="__('Unit')">
                <flux:select.option value="">{{ __('All authorized units') }}</flux:select.option>
                @foreach ($this->unitOptions as $unit)
                    <flux:select.option :value="$unit->id">{{ $unit->code }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="show" :label="__('Show')">
                <flux:select.option value="all">{{ __('All personnel') }}</flux:select.option>
                <flux:select.option value="missing">{{ __('Not yet recorded') }}</flux:select.option>
                <flux:select.option value="recorded">{{ __('Recorded') }}</flux:select.option>
            </flux:select>
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :label="__('Search')" :placeholder="__('Name or employee no.')" clearable />
            </div>
        </div>

        @if ($isFuture)
            <div class="p-4"><flux:callout variant="warning" icon="exclamation-triangle" :heading="__(':month :year has not started yet.', ['month' => $monthName, 'year' => $year])" /></div>
        @endif

        <form wire:submit="saveBatch">
            <div class="overflow-x-auto px-4">
                <flux:table :paginate="$personnel">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank / Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Unit') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Height (cm)') }}</flux:table.column>
                        <flux:table.column align="end">{{ $previous->format('M') }} {{ __('weight') }}</flux:table.column>
                        <flux:table.column>{{ $monthName }} {{ __('weight (kg)') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Change') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('BMI') }}</flux:table.column>
                        <flux:table.column>{{ __('PNP') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($personnel as $person)
                            @php
                                $current = $person->monthlyWeights->first(fn ($m) => $m->year === $year && $m->month === $month);
                                $prior = $person->monthlyWeights->first(fn ($m) => $m->year === $previous->year && $m->month === $previous->month);
                                $difference = $current && $prior ? round($current->weight_kg - $prior->weight_kg, 2) : null;
                            @endphp
                            <flux:table.row :key="'p-'.$person->id">
                                <flux:table.cell>
                                    <a href="{{ route('monitoring.personnel', ['personnel' => $person, 'year' => $year]) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-navy-200">{{ $person->rank?->abbreviation }} {{ $person->formal_name }}</a>
                                </flux:table.cell>
                                <flux:table.cell>{{ $person->unit?->code }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $current?->height_cm ?? $person->latestAssessment?->height_cm ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums text-zinc-500">{{ $prior ? number_format($prior->weight_kg, 2) : '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($current)
                                        <span class="font-semibold tabular-nums">{{ number_format($current->weight_kg, 2) }}</span>
                                    @elseif ($this->canEncode() && ! $isFuture)
                                        <div class="w-28">
                                            <flux:input size="sm" wire:model="weights.{{ $person->id }}" type="number" step="0.01" inputmode="decimal" placeholder="0.00" :aria-label="__('Weight for :name', ['name' => $person->formal_name])" />
                                            <flux:error name="weights.{{ $person->id }}" />
                                        </div>
                                    @else
                                        <x-month-status :status="$periodStatus" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">
                                    <x-weight-trend :difference="$difference" :trend="$difference === null ? null : ($difference > 0 ? 'up' : ($difference < 0 ? 'down' : 'same'))" />
                                </flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $current?->bmi !== null ? number_format($current->bmi, 1) : '—' }}</flux:table.cell>
                                <flux:table.cell><x-bmi-badge :label="$current?->pnp_classification" :category="$current?->pnp_category" /></flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="xs" variant="ghost" icon="eye" :href="route('monitoring.personnel', ['personnel' => $person, 'year' => $year])" wire:navigate :tooltip="__('Open year sheet')" />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="9"><x-empty-state icon="scale" :heading="__('No personnel to show')" /></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>

            @if ($this->canEncode() && ! $isFuture)
                <div class="flex flex-col gap-3 border-t border-zinc-100 p-4 sm:flex-row sm:items-end sm:justify-between dark:border-zinc-800">
                    <div class="w-full sm:w-56">
                        <flux:input type="date" wire:model="measuredOn" :label="__('Date measured (applies to entries on this page)')" />
                    </div>
                    <flux:button type="submit" variant="primary" icon="check">{{ __('Save entered weights') }}</flux:button>
                </div>
            @endif
        </form>
    </x-panel>
</div>
