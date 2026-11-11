<div>
    <x-page-header :title="__('Compliance Monitoring')" :subtitle="__('Monthly weight monitoring compliance for :month :year · deadline :deadline · target :target%', ['month' => $monthName, 'year' => $year, 'deadline' => $deadline->format('F j, Y'), 'target' => $target])">
        <x-slot:actions>
            <flux:select wire:model.live="year" size="sm" class="w-24">
                @foreach ($this->yearOptions() as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="month" size="sm" class="w-36">
                @foreach ($this->monthOptions() as $number => $name)
                    <flux:select.option :value="$number">{{ $name }}</flux:select.option>
                @endforeach
            </flux:select>
            @can('reports.generate')
                <flux:button size="sm" icon="printer" :href="route('reports.print', ['report' => 'unit-compliance', 'year' => $year, 'month' => $month])" target="_blank">{{ __('Print') }}</flux:button>
            @endcan
            @if ($this->canSendReminders())
                <flux:button size="sm" icon="bell" wire:click="sendReminders" wire:confirm="{{ __('Send monthly monitoring reminders now to everyone with a pending weigh-in?') }}">{{ __('Send reminders') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Overall compliance')" :value="$overall['percent'].'%'" icon="chart-bar" :tone="$overall['percent'] >= $target ? 'green' : 'amber'" />
        <x-stat-card :label="__('Recorded')" :value="$overall['recorded'].' / '.$overall['total']" icon="check-badge" tone="green" />
        <x-stat-card :label="__('Pending')" :value="$overall['pending']" icon="exclamation-triangle" :tone="$periodStatus === 'missing' ? 'red' : 'amber'" :hint="$periodStatus === 'missing' ? __('Deadline has passed') : __('Before deadline')" />
        <x-stat-card :label="__('Units meeting target')" :value="$units->where('meets_target', true)->count().' / '.$units->count()" icon="building-office-2" />
    </div>

    <div class="grid gap-6 xl:grid-cols-5">
        <x-panel :heading="__('Compliance by unit / office')" :padding="false" class="xl:col-span-3">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($units as $row)
                    <button type="button" wire:click="selectUnit('{{ $row['unit']->id }}')" @class(['block w-full px-4 py-3 text-start hover:bg-zinc-50 dark:hover:bg-zinc-800', 'bg-navy-50 dark:bg-navy-900/30' => (int) $unitId === $row['unit']->id])>
                        <div class="flex items-center justify-between gap-3 text-sm">
                            <div class="min-w-0">
                                <span class="font-medium">{{ $row['unit']->name }}</span>
                                <span class="text-zinc-500">· {{ $row['unit']->code }}</span>
                            </div>
                            <div class="shrink-0 tabular-nums">
                                <span class="text-zinc-500">{{ $row['recorded'] }}/{{ $row['total'] }}</span>
                                <span @class(['ms-2 font-semibold', 'text-green-700 dark:text-green-400' => $row['meets_target'], 'text-red-600' => ! $row['meets_target']])>{{ $row['percent'] }}%</span>
                            </div>
                        </div>
                        <div class="mt-2 h-2 overflow-hidden rounded-full bg-zinc-100 dark:bg-zinc-800">
                            <div @class(['h-full rounded-full', 'bg-health-500' => $row['meets_target'], 'bg-amber-500' => ! $row['meets_target']]) style="width: {{ $row['percent'] }}%"></div>
                        </div>
                    </button>
                @empty
                    <x-empty-state icon="building-office" :heading="__('No units with active personnel')" />
                @endforelse
            </div>
        </x-panel>

        <div class="space-y-6 xl:col-span-2">
            <x-panel :heading="__('Monthly trend — :scope', ['scope' => $selectedUnit ? $selectedUnit['unit']->code : __('all authorized units')])">
                <x-chart :config="$trend" height="13rem" />
            </x-panel>

            <x-panel :heading="__('Personnel with missing monthly records')" :subheading="__(':count personnel — :scope', ['count' => $missingTotal, 'scope' => $selectedUnit ? $selectedUnit['unit']->name : __('all units')])" :padding="false">
                <x-slot:actions>
                    @if ($unitId !== '' && ! auth()->user()->hasUnitScope())
                        <flux:button size="xs" variant="ghost" wire:click="$set('unitId', '')">{{ __('Show all') }}</flux:button>
                    @endif
                    @can('reports.generate')
                        <flux:button size="xs" icon="printer" :href="route('reports.print', ['report' => 'pending-monitoring', 'year' => $year, 'month' => $month, 'unit_id' => $unitId])" target="_blank" />
                    @endcan
                </x-slot:actions>
                <div class="max-h-96 divide-y divide-zinc-100 overflow-y-auto dark:divide-zinc-800">
                    @forelse ($missing as $person)
                        <a href="{{ route('monitoring.personnel', ['personnel' => $person, 'year' => $year]) }}" wire:navigate class="flex items-center justify-between gap-3 px-4 py-2 text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800">
                            <span class="truncate">{{ $person->rank?->abbreviation }} {{ $person->formal_name }}</span>
                            <span class="text-xs text-zinc-500">{{ $person->unit?->code }}</span>
                        </a>
                    @empty
                        <x-empty-state icon="check-badge" :heading="__('Complete — no missing records')" class="py-6" />
                    @endforelse
                </div>
            </x-panel>
        </div>
    </div>
</div>
