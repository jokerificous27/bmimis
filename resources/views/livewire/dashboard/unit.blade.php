<div>
    <x-page-header :title="$unit ? $unit->name : __('Unit Monitoring')" :subtitle="__('Unit / office BMI monitoring for :month :year. Deadline: :deadline.', ['month' => $monthName, 'year' => $year, 'deadline' => $deadline->format('F j, Y')])">
        <x-slot:actions>
            @can('reports.generate')
                <flux:button size="sm" icon="printer" :href="route('reports.print', ['report' => 'unit-compliance', 'year' => $year, 'month' => $month, 'unit_id' => $unitId])" target="_blank">{{ __('Unit compliance report') }}</flux:button>
                <flux:button size="sm" icon="printer" :href="route('reports.print', ['report' => 'pending-monitoring', 'year' => $year, 'month' => $month, 'unit_id' => $unitId])" target="_blank">{{ __('Pending list') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-3 dark:border-zinc-700 dark:bg-zinc-900">
        <flux:select wire:model.live="unitId" :label="__('Unit / Office')" size="sm" :disabled="$locked">
            @foreach ($this->unitOptions as $option)
                <flux:select.option :value="$option->id">{{ $option->code }} — {{ $option->name }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="year" :label="__('Year')" size="sm">
            @foreach ($this->yearOptions() as $option)
                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="month" :label="__('Month')" size="sm">
            @foreach ($this->monthOptions() as $number => $name)
                <flux:select.option :value="$number">{{ $name }}</flux:select.option>
            @endforeach
        </flux:select>
    </div>

    @if (! $unit)
        <x-panel><x-empty-state icon="building-office" :heading="__('No unit assigned')" :text="__('Ask the system administrator to assign your account to a unit.')" /></x-panel>
    @else
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
            <x-stat-card :label="__('Unit personnel')" :value="$stats['total']" icon="users" />
            <x-stat-card :label="__('With updated BMI records')" :value="$stats['with_records']" icon="clipboard-document-list" />
            <x-stat-card :label="__('Pending monitoring')" :value="$stats['monthly_pending']" icon="exclamation-triangle" :tone="$periodStatus === 'missing' && $stats['monthly_pending'] ? 'red' : 'amber'" />
            <x-stat-card :label="__('Monthly compliance')" :value="$stats['compliance_percent'].'%'" icon="chart-bar" :tone="$stats['monthly_pending'] ? 'amber' : 'green'" />
            <x-stat-card :label="__('Requiring follow-up')" :value="$stats['requiring_intervention']" icon="heart" tone="red" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-3">
            <x-panel :heading="__('BMI classification distribution')">
                @if ($stats['with_records'])
                    <x-chart :config="$charts['pnp']" height="14rem" />
                @else
                    <x-empty-state icon="chart-bar" :heading="__('No assessments yet')" />
                @endif
            </x-panel>
            <x-panel :heading="__('Monthly compliance — :year', ['year' => $year])" class="lg:col-span-2">
                <x-chart :config="$charts['compliance']" height="14rem" />
            </x-panel>
        </div>

        <x-panel class="mt-6" :heading="__('Unit personnel')" :padding="false">
            <x-slot:actions>
                <flux:select wire:model.live="show" size="sm" class="w-48">
                    <flux:select.option value="">{{ __('All personnel') }}</flux:select.option>
                    <flux:select.option value="pending">{{ __('Pending this month') }}</flux:select.option>
                    <flux:select.option value="follow-up">{{ __('Requiring follow-up') }}</flux:select.option>
                </flux:select>
                <flux:input wire:model.live.debounce.300ms="search" size="sm" icon="magnifying-glass" :placeholder="__('Search')" class="w-48" />
            </x-slot:actions>
            <div class="overflow-x-auto px-4">
                <flux:table :paginate="$table">
                    <flux:table.columns>
                        <flux:table.column>{{ __('Rank / Name') }}</flux:table.column>
                        <flux:table.column>{{ __('Latest BMI') }}</flux:table.column>
                        <flux:table.column>{{ __('PNP') }}</flux:table.column>
                        <flux:table.column>{{ __('Assessed') }}</flux:table.column>
                        <flux:table.column>{{ $monthName }}</flux:table.column>
                        <flux:table.column>{{ __('Intervention') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($table as $person)
                            @php($assessment = $latest->get($person->id))
                            <flux:table.row :key="$person->id">
                                <flux:table.cell><a href="{{ route('personnel.show', $person) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-navy-200">{{ $person->rank?->abbreviation }} {{ $person->formal_name }}</a></flux:table.cell>
                                <flux:table.cell class="tabular-nums">{{ $assessment ? number_format($assessment->bmi, 1) : '—' }}</flux:table.cell>
                                <flux:table.cell><x-bmi-badge :label="$assessment?->pnp_classification" :category="$assessment?->pnp_category" /></flux:table.cell>
                                <flux:table.cell>{{ $assessment?->assessed_on->format('M j, Y') ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($record = $person->monthlyWeights->first())
                                        <flux:badge size="sm" color="green">{{ number_format($record->weight_kg, 2) }} kg</flux:badge>
                                    @else
                                        <x-month-status :status="$periodStatus" />
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell>{{ $person->activeIntervention?->package?->displayName() ?? '—' }}</flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="6"><x-empty-state icon="users" :heading="__('No personnel to show')" /></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </x-panel>

        @if ($followUps->isNotEmpty())
            <x-panel class="mt-6" :heading="__('Upcoming Health Service follow-ups (14 days)')" :padding="false">
                @include('livewire.dashboard.partials.alert-list', ['items' => $followUps, 'detail' => fn ($i) => $i->package->displayName().' · '.$i->next_follow_up_on->format('M j'), 'person' => fn ($i) => $i->personnel, 'empty' => '', 'danger' => fn ($i) => $i->isFollowUpOverdue()])
            </x-panel>
        @endif
    @endif
</div>
