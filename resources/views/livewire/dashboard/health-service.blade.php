<div>
    <x-page-header :title="__('Health Service Dashboard')" :subtitle="__('BMI status of personnel as of :month :year. All figures are computed from live records.', ['month' => $monthName, 'year' => $year])">
        <x-slot:actions>
            @can('reports.generate')
                <flux:button size="sm" icon="printer" :href="route('reports.print', ['report' => 'summary', 'year' => $year, 'month' => $month, 'unit_id' => $unitId])" target="_blank">{{ __('Summary report') }}</flux:button>
            @endcan
            @can('assessments.encode')
                <flux:button size="sm" variant="primary" icon="plus" :href="route('assessments.create')" wire:navigate>{{ __('New assessment') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($provisionalPnp)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6" :heading="__('PNP BMI standard is provisional')">
            <flux:callout.text>
                {{ __('The PNP Acceptable BMI thresholds currently mirror WHO cut-offs. Encode the official values from the applicable PNP Health Service policy in BMI Standards.') }}
                @can('standards.manage')
                    <flux:link :href="route('admin.standards')" wire:navigate>{{ __('Manage BMI standards') }}</flux:link>
                @endcan
            </flux:callout.text>
        </flux:callout>
    @endif

    <div class="mb-6 grid gap-3 rounded-xl border border-zinc-200 bg-white p-4 sm:grid-cols-3 lg:grid-cols-8 dark:border-zinc-700 dark:bg-zinc-900">
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
        <flux:select wire:model.live="unitId" :label="__('Unit / Office')" size="sm">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($this->unitOptions as $unit)
                <flux:select.option :value="$unit->id">{{ $unit->code }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="rankId" :label="__('Rank')" size="sm">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($this->rankOptions as $rank)
                <flux:select.option :value="$rank->id">{{ $rank->abbreviation }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="sex" :label="__('Gender')" size="sm">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($sexes as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
        </flux:select>
        <flux:select wire:model.live="category" :label="__('BMI classification')" size="sm">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            @foreach ($categories as $value => $label)
                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
            @endforeach
            <flux:select.option value="none">{{ __('No assessment') }}</flux:select.option>
        </flux:select>
        <flux:select wire:model.live="compliance" :label="__('Compliance')" size="sm">
            <flux:select.option value="">{{ __('All') }}</flux:select.option>
            <flux:select.option value="complied">{{ __('Recorded this month') }}</flux:select.option>
            <flux:select.option value="pending">{{ __('Pending this month') }}</flux:select.option>
        </flux:select>
        <div class="flex items-end">
            <flux:button size="sm" variant="ghost" icon="arrow-path" wire:click="resetFilters" class="w-full">{{ __('Reset') }}</flux:button>
        </div>
    </div>

    <div wire:loading.class="opacity-60" class="transition">
        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <x-stat-card :label="__('Registered personnel')" :value="number_format($stats['total'])" icon="users" :href="route('personnel.index', ['unitId' => $unitId])" />
            <x-stat-card :label="__('With BMI records')" :value="number_format($stats['with_records'])" icon="clipboard-document-list" :hint="$stats['total'] ? round($stats['with_records'] / $stats['total'] * 100).'% '.__('of personnel') : null" />
            <x-stat-card :label="__('Normal BMI')" :value="number_format($stats['normal'])" icon="check-badge" tone="green" :href="route('personnel.index', ['mode' => 'profiles', 'category' => 'normal', 'unitId' => $unitId])" />
            <x-stat-card :label="__('Requiring intervention')" :value="number_format($stats['requiring_intervention'])" icon="heart" tone="red" :href="route('interventions.index')" />
            <x-stat-card :label="__('Below normal BMI')" :value="number_format($stats['below'])" icon="arrow-trending-down" tone="amber" :href="route('personnel.index', ['mode' => 'profiles', 'category' => 'below', 'unitId' => $unitId])" />
            <x-stat-card :label="__('Above normal BMI')" :value="number_format($stats['above'])" icon="arrow-trending-up" tone="red" :href="route('personnel.index', ['mode' => 'profiles', 'category' => 'above', 'unitId' => $unitId])" />
            <x-stat-card :label="__('Monthly monitoring')" :value="number_format($stats['monthly_completed']).' / '.number_format($stats['total'])" icon="scale" :hint="__(':pending pending · deadline :date', ['pending' => $stats['monthly_pending'], 'date' => $deadline->format('M j')])" :href="route('monitoring.index', ['year' => $year, 'month' => $month, 'unitId' => $unitId, 'show' => 'missing'])" />
            <x-stat-card :label="__('Monthly compliance')" :value="$stats['compliance_percent'].'%'" icon="chart-bar" :tone="$stats['compliance_percent'] >= $target ? 'green' : 'amber'" :hint="__('Target: :target%', ['target' => $target])" :href="route('compliance.index', ['year' => $year, 'month' => $month])" />
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <x-panel :heading="__('PNP BMI classification')" :subheading="__('Latest assessment per personnel')">
                @if ($stats['with_records'])
                    <x-chart :config="$charts['pnp']" height="15rem" />
                @else
                    <x-empty-state icon="chart-bar" :heading="__('No assessments in scope')" />
                @endif
            </x-panel>
            <x-panel :heading="__('WHO BMI classification')">
                @if ($stats['with_records'])
                    <x-chart :config="$charts['who']" height="15rem" />
                @else
                    <x-empty-state icon="chart-bar" :heading="__('No assessments in scope')" />
                @endif
            </x-panel>
            <x-panel :heading="__('Monthly monitoring compliance — :year', ['year' => $year])">
                <x-chart :config="$charts['compliance']" height="15rem" />
            </x-panel>
            <x-panel :heading="__('Average BMI by month')" :subheading="__('From monthly weigh-ins, compared with the previous year')">
                <x-chart :config="$charts['bmi']" height="15rem" />
            </x-panel>
            <x-panel :heading="__('Weight trend over time')">
                <x-chart :config="$charts['weight']" height="15rem" />
            </x-panel>
            <x-panel :heading="__('Intervention package distribution')">
                @if (array_sum($charts['interventions']['data']['datasets'][0]['data']))
                    <x-chart :config="$charts['interventions']" height="15rem" />
                @else
                    <x-empty-state icon="heart" :heading="__('No ongoing interventions')" />
                @endif
            </x-panel>
            <x-panel :heading="__('BMI distribution by unit')" class="lg:col-span-2 xl:col-span-3">
                <x-chart :config="$charts['units']" :height="max(12, count($charts['units']['data']['labels']) * 2.2).'rem'" />
            </x-panel>
        </div>

        <flux:heading size="lg" class="mt-8 mb-3">{{ __('Health Service alerts') }}</flux:heading>
        <div class="grid gap-6 lg:grid-cols-2 xl:grid-cols-3">
            <x-panel :heading="__('Missing monthly monitoring')" :subheading="$alerts['deadline_passed']->first() ? __(':count personnel — deadline passed', ['count' => $alerts['missing_monthly_count']->first()]) : __(':count personnel not yet weighed this month', ['count' => $alerts['missing_monthly_count']->first()])" :padding="false">
                @include('livewire.dashboard.partials.alert-list', ['items' => $alerts['missing_monthly'], 'detail' => fn ($p) => $p->unit?->code, 'person' => fn ($p) => $p, 'empty' => __('Everyone has a weight recorded this month.')])
                <div class="border-t border-zinc-100 p-3 text-end dark:border-zinc-800"><flux:link :href="route('monitoring.index', ['show' => 'missing'])" wire:navigate class="text-sm">{{ __('Open encoding sheet') }}</flux:link></div>
            </x-panel>
            <x-panel :heading="__('Abnormal BMI classification')" :padding="false">
                @include('livewire.dashboard.partials.alert-list', ['items' => $alerts['abnormal'], 'detail' => fn ($a) => number_format($a->bmi, 1).' · '.$a->pnp_classification, 'person' => fn ($a) => $a->personnel, 'empty' => __('No abnormal classifications.')])
            </x-panel>
            <x-panel :heading="__('Follow-ups due (7 days)')" :padding="false">
                @include('livewire.dashboard.partials.alert-list', ['items' => $alerts['follow_ups'], 'detail' => fn ($i) => $i->package->displayName().' · '.$i->next_follow_up_on->format('M j'), 'person' => fn ($i) => $i->personnel, 'empty' => __('No follow-ups due.'), 'danger' => fn ($i) => $i->isFollowUpOverdue()])
            </x-panel>
            <x-panel :heading="__('Outdated or missing assessment')" :padding="false">
                @include('livewire.dashboard.partials.alert-list', ['items' => $alerts['outdated'], 'detail' => fn ($p) => $p->latestAssessment ? __('Last: :date', ['date' => $p->latestAssessment->assessed_on->format('M Y')]) : __('Never assessed'), 'person' => fn ($p) => $p, 'empty' => __('All assessments are current.')])
            </x-panel>
            <x-panel :heading="__('Significant weight changes')" :padding="false">
                @include('livewire.dashboard.partials.alert-list', ['items' => $alerts['weight_changes'], 'detail' => fn ($r) => ($r['change'] > 0 ? '+' : '').number_format($r['change'], 2).' kg'.($r['trend'] ? ' · '.__($r['trend']) : ''), 'person' => fn ($r) => $r['personnel'], 'empty' => __('No significant changes.')])
            </x-panel>
            <x-panel :heading="__('BMI changed: update waist, hip, wrist?')" :subheading="__('Optional. Open a personnel to update the measurements or mark that no update is needed.')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($alerts['measurement_reviews'] as $review)
                        <a href="{{ route('monitoring.personnel', ['personnel' => $review->personnel_id, 'year' => $review->year]) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="min-w-0 truncate font-medium">{{ $review->personnel->rank?->abbreviation }} {{ $review->personnel->formal_name }}</span>
                            <span class="shrink-0 text-xs font-semibold text-amber-700 dark:text-amber-400">{{ __('BMI') }} {{ ($review->bmi_change > 0 ? '+' : '').number_format($review->bmi_change, 1) }} · {{ $review->monthName() }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('No measurements waiting for review.')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>
            <x-panel :heading="__('Units with low compliance')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($alerts['low_compliance_units'] as $row)
                        <a href="{{ route('compliance.index', ['unitId' => $row['unit']->id]) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2.5 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="truncate">{{ $row['unit']->name }}</span>
                            <span class="shrink-0 font-semibold tabular-nums text-red-600">{{ $row['percent'] }}%</span>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('All units meet the target')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>
        </div>
    </div>
</div>
