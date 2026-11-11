@php
    $tabs = ['comparison' => __('Annual comparison'), 'trends' => __('Health trends & follow-up'), 'quality' => __('Data quality')];
@endphp

<div>
    <x-page-header :title="__('BMI Analytics')" :subtitle="__('Year-over-year comparison, personnel health trends and data quality monitoring.')">
        <x-slot:actions>
            <flux:select wire:model.live="unitId" size="sm" class="w-44">
                <flux:select.option value="">{{ __('All units') }}</flux:select.option>
                @foreach ($this->unitOptions as $unit)
                    <flux:select.option :value="$unit->id">{{ $unit->code }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="year" size="sm" class="w-24">
                @foreach ($this->yearOptions() as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
        </x-slot:actions>
    </x-page-header>

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
        @foreach ($tabs as $key => $label)
            <button type="button" wire:click="$set('tab', '{{ $key }}')" @class([
                'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium',
                'border-navy-800 text-navy-800 dark:border-navy-200 dark:text-navy-200' => $tab === $key,
                'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    @if ($tab === 'comparison')
        <div class="grid gap-6 lg:grid-cols-2">
            <x-panel :heading="__('PNP classification by year')" :subheading="__('Latest official assessment of each personnel within the year')"><x-chart :config="$comparisonChart" /></x-panel>
            <x-panel :heading="__('Average BMI by year')"><x-chart :config="$averageChart" /></x-panel>
        </div>
        <x-panel class="mt-6" :padding="false">
            <div class="overflow-x-auto px-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Year') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Assessed') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Average BMI') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Below normal') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Normal') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Above normal') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Normal %') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Monthly records') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Average weight') }}</flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @foreach ($comparison as $compareYear => $row)
                            <flux:table.row :key="$compareYear">
                                <flux:table.cell class="font-medium">{{ $compareYear }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['assessed'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['average_bmi'] ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['below'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['normal'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['above'] }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['assessed'] ? round($row['normal'] / $row['assessed'] * 100, 1).'%' : '—' }}</flux:table.cell>
                                <flux:table.cell align="end">{{ number_format($row['monthly_records']) }}</flux:table.cell>
                                <flux:table.cell align="end">{{ $row['average_weight'] ? $row['average_weight'].' kg' : '—' }}</flux:table.cell>
                            </flux:table.row>
                        @endforeach
                    </flux:table.rows>
                </flux:table>
            </div>
        </x-panel>
    @endif

    @if ($tab === 'trends')
        <div class="grid gap-6 lg:grid-cols-2">
            <x-panel :heading="__('Consistent or significant weight changes')" :subheading="__('Thresholds are configurable in System Settings.')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($trends as $row)
                        <a href="{{ route('personnel.show', $row['personnel']) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div class="min-w-0">
                                <div class="truncate font-medium">{{ $row['personnel']->rank?->abbreviation }} {{ $row['personnel']->formal_name }}</div>
                                <div class="text-xs text-zinc-500">{{ $row['personnel']->unit?->code }} · {{ __('latest') }} {{ $row['latest']->monthName() }} {{ $row['latest']->year }}: {{ number_format($row['latest']->weight_kg, 2) }} kg</div>
                            </div>
                            <div class="shrink-0 text-end">
                                <div @class(['font-semibold tabular-nums', 'text-red-600' => $row['change'] > 0, 'text-green-700' => $row['change'] < 0])>{{ $row['change'] > 0 ? '+' : '' }}{{ number_format($row['change'], 2) }} kg</div>
                                @if ($row['trend'])<flux:badge size="sm" :color="$row['trend'] === 'increasing' ? 'red' : 'green'">{{ __('Consistently :trend', ['trend' => $row['trend']]) }}</flux:badge>@endif
                            </div>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('No notable trends')" />
                    @endforelse
                </div>
            </x-panel>
            <x-panel :heading="__('Follow-up list: reassessment needed')" :subheading="__('Active personnel without a current assessment (validity period in System Settings).')" :padding="false">
                <div class="max-h-[32rem] divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                    @forelse ($outdated as $person)
                        <a href="{{ route('personnel.show', $person) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="truncate">{{ $person->rank?->abbreviation }} {{ $person->formal_name }} <span class="text-zinc-500">· {{ $person->unit?->code }}</span></span>
                            <span class="shrink-0 text-xs text-zinc-500">{{ $person->latestAssessment ? $person->latestAssessment->assessed_on->format('M Y') : __('Never assessed') }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('All assessments are current')" />
                    @endforelse
                </div>
            </x-panel>
        </div>
    @endif

    @if ($tab === 'quality')
        <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-6">
            <x-stat-card :label="__('Incomplete profiles')" :value="$quality['incomplete_profiles']->count()" icon="identification" :tone="$quality['incomplete_profiles']->isEmpty() ? 'green' : 'amber'" />
            <x-stat-card :label="__('Missing measurements')" :value="$quality['missing_measurements']->count()" icon="clipboard-document-list" :tone="$quality['missing_measurements']->isEmpty() ? 'green' : 'amber'" />
            <x-stat-card :label="__('Possible duplicates')" :value="$quality['possible_duplicates']->count()" icon="users" :tone="$quality['possible_duplicates']->isEmpty() ? 'green' : 'red'" />
            <x-stat-card :label="__('Unusual BMI values')" :value="$quality['unusual_bmi']->count()" icon="exclamation-triangle" :tone="$quality['unusual_bmi']->isEmpty() ? 'green' : 'red'" />
            <x-stat-card :label="__('Unusual monthly changes')" :value="$quality['unusual_changes']->count()" icon="arrow-trending-up" :tone="$quality['unusual_changes']->isEmpty() ? 'green' : 'red'" />
            <x-stat-card :label="__('Monthly without height')" :value="$quality['monthly_without_height']->count()" icon="scale" :tone="$quality['monthly_without_height']->isEmpty() ? 'green' : 'amber'" />
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <x-panel :heading="__('Incomplete personnel profiles')" :padding="false">
                <div class="max-h-80 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                    @forelse ($quality['incomplete_profiles'] as $row)
                        <a href="{{ route('personnel.edit', $row['personnel']) }}" wire:navigate class="block px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <div class="font-medium">{{ $row['personnel']->rank?->abbreviation }} {{ $row['personnel']->formal_name }}</div>
                            <div class="text-xs text-amber-700 dark:text-amber-400">{{ __('Missing') }}: {{ implode(', ', $row['missing']) }}</div>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('All profiles complete')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>

            <x-panel :heading="__('Assessments with missing waist / hip / wrist')" :padding="false">
                <div class="max-h-80 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                    @forelse ($quality['missing_measurements'] as $assessment)
                        <a href="{{ route('assessments.show', $assessment) }}" wire:navigate class="flex justify-between px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span>{{ $assessment->personnel->rank?->abbreviation }} {{ $assessment->personnel->formal_name }}</span>
                            <span class="text-xs text-zinc-500">{{ $assessment->assessed_on->format('M j, Y') }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('No missing measurements')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>

            <x-panel :heading="__('Possible duplicate personnel records')" :subheading="__('Same name and date of birth')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($quality['possible_duplicates'] as $group)
                        <div class="px-4 py-2.5 text-sm">
                            @foreach ($group as $person)
                                <a href="{{ route('personnel.show', $person) }}" wire:navigate class="block hover:underline">{{ $person->formal_name }} · {{ $person->employee_number }}</a>
                            @endforeach
                        </div>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('No duplicates detected')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>

            <x-panel :heading="__('Unusual entries')" :subheading="__('BMI below 15 or above 45, and month-to-month changes over twice the significant-change threshold (possible unit errors)')" :padding="false">
                <div class="max-h-80 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                    @foreach ($quality['unusual_bmi'] as $assessment)
                        <a href="{{ route('assessments.show', $assessment) }}" wire:navigate class="flex justify-between px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span>{{ $assessment->personnel->formal_name }}</span><span class="font-semibold text-red-600">BMI {{ number_format($assessment->bmi, 1) }}</span>
                        </a>
                    @endforeach
                    @foreach ($quality['unusual_changes'] as $row)
                        <a href="{{ route('monitoring.personnel', ['personnel' => $row['record']->personnel_id, 'year' => $row['record']->year]) }}" wire:navigate class="flex justify-between px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span>{{ $row['record']->personnel?->formal_name }} · {{ $row['previous']->monthName() }} → {{ $row['record']->monthName() }}</span><span class="font-semibold text-red-600">{{ $row['change'] > 0 ? '+' : '' }}{{ number_format($row['change'], 2) }} kg</span>
                        </a>
                    @endforeach
                    @if ($quality['unusual_bmi']->isEmpty() && $quality['unusual_changes']->isEmpty())
                        <x-empty-state icon="check-badge" :heading="__('No unusual entries')" class="py-6" />
                    @endif
                </div>
            </x-panel>
        </div>
    @endif
</div>
