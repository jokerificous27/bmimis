<div>
    <x-page-header
        :title="$mode === 'profiles' ? __('BMI Profiles') : __('Personnel Directory')"
        :subtitle="$mode === 'profiles' ? __('Latest BMI result and monthly monitoring status of each personnel.') : __('Register, search and manage personnel records.')"
    >
        <x-slot:actions>
            @can('records.export')
                <flux:button size="sm" icon="arrow-down-tray" :href="route('reports.export', ['report' => 'personnel-directory', 'unit_id' => $unitId, 'category' => $category])">{{ __('Export Excel') }}</flux:button>
            @endcan
            @can('personnel.import')
                <flux:button size="sm" icon="document-arrow-up" :href="route('personnel.import')" wire:navigate>{{ __('Import') }}</flux:button>
            @endcan
            @can('create', \App\Models\Personnel::class)
                <flux:button size="sm" variant="primary" icon="plus" :href="route('personnel.create')" wire:navigate>{{ __('Add personnel') }}</flux:button>
            @endcan
        </x-slot:actions>
    </x-page-header>

    <x-panel :padding="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-6 dark:border-zinc-800">
            <div class="sm:col-span-2">
                <flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or employee no.')" clearable />
            </div>
            <flux:select wire:model.live="unitId">
                <flux:select.option value="">{{ __('All units') }}</flux:select.option>
                @foreach ($this->unitOptions as $unit)
                    <flux:select.option :value="$unit->id">{{ $unit->code }} — {{ $unit->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="rankId">
                <flux:select.option value="">{{ __('All ranks') }}</flux:select.option>
                @foreach ($this->rankOptions as $rank)
                    <flux:select.option :value="$rank->id">{{ $rank->abbreviation }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:select wire:model.live="category">
                <flux:select.option value="">{{ __('All classifications') }}</flux:select.option>
                @foreach ($categories as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
                <flux:select.option value="none">{{ __('No assessment yet') }}</flux:select.option>
            </flux:select>
            <div class="flex gap-2">
                <flux:select wire:model.live="status" class="flex-1">
                    <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                    @foreach ($statuses as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button icon="x-mark" variant="ghost" wire:click="resetFilters" :tooltip="__('Reset filters')" />
            </div>
        </div>

        <div class="overflow-x-auto px-4">
            <flux:table :paginate="$personnel">
                <flux:table.columns>
                    <flux:table.column sortable :sorted="$sortBy === 'last_name'" :direction="$sortDirection" wire:click="sort('last_name')">{{ __('Rank / Name') }}</flux:table.column>
                    <flux:table.column sortable :sorted="$sortBy === 'unit_id'" :direction="$sortDirection" wire:click="sort('unit_id')">{{ __('Unit') }}</flux:table.column>
                    @if ($mode === 'profiles')
                        <flux:table.column>{{ __('Latest assessment') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('BMI') }}</flux:table.column>
                        <flux:table.column>{{ __('PNP') }}</flux:table.column>
                        <flux:table.column>{{ __('WHO') }}</flux:table.column>
                        <flux:table.column>{{ __('Intervention') }}</flux:table.column>
                        <flux:table.column>{{ now()->format('M Y') }}</flux:table.column>
                    @else
                        <flux:table.column sortable :sorted="$sortBy === 'employee_number'" :direction="$sortDirection" wire:click="sort('employee_number')">{{ __('Employee No.') }}</flux:table.column>
                        <flux:table.column>{{ __('Position') }}</flux:table.column>
                        <flux:table.column>{{ __('Sex / Age') }}</flux:table.column>
                        <flux:table.column>{{ __('Account') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                    @endif
                    <flux:table.column></flux:table.column>
                </flux:table.columns>

                <flux:table.rows>
                    @forelse ($personnel as $person)
                        <flux:table.row :key="$person->id">
                            <flux:table.cell>
                                <a href="{{ route('personnel.show', $person) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-navy-200">{{ $person->rank?->abbreviation }} {{ $person->formal_name }}</a>
                                @if ($mode === 'profiles')
                                    <div class="text-xs text-zinc-500">{{ $person->employee_number }}</div>
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $person->unit?->code ?? '—' }}</flux:table.cell>
                            @if ($mode === 'profiles')
                                <flux:table.cell>{{ $person->latestAssessment?->assessed_on->format('M j, Y') ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums font-medium">{{ $person->latestAssessment ? number_format($person->latestAssessment->bmi, 1) : '—' }}</flux:table.cell>
                                <flux:table.cell><x-bmi-badge :label="$person->latestAssessment?->pnp_classification" :category="$person->latestAssessment?->pnp_category" /></flux:table.cell>
                                <flux:table.cell><x-bmi-badge :label="$person->latestAssessment?->who_classification" :category="$person->latestAssessment?->who_category" /></flux:table.cell>
                                <flux:table.cell>{{ $person->activeIntervention?->package?->displayName() ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($record = $person->monthlyWeights->first())
                                        <flux:badge size="sm" color="green">{{ number_format($record->weight_kg, 2) }} kg</flux:badge>
                                    @else
                                        <x-month-status :status="app(\App\Services\MonthlyMonitoringService::class)->periodStatus(today()->year, today()->month)" />
                                    @endif
                                </flux:table.cell>
                            @else
                                <flux:table.cell class="tabular-nums">{{ $person->employee_number }}</flux:table.cell>
                                <flux:table.cell>{{ $person->position ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $person->sex->initial() }} / {{ $person->age ?? '—' }}</flux:table.cell>
                                <flux:table.cell>
                                    @if ($person->user)
                                        <flux:badge size="sm" :color="$person->user->is_active ? 'green' : 'zinc'">{{ $person->user->is_active ? __('Active') : __('Disabled') }}</flux:badge>
                                    @else
                                        <span class="text-xs text-zinc-400">{{ __('None') }}</span>
                                    @endif
                                </flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$person->status->color()">{{ $person->status->label() }}</flux:badge></flux:table.cell>
                            @endif
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="eye" :href="route('personnel.show', $person)" wire:navigate :tooltip="__('Open profile')" />
                                @can('update', $person)
                                    <flux:button size="xs" variant="ghost" icon="pencil-square" :href="route('personnel.edit', $person)" wire:navigate :tooltip="__('Edit')" />
                                @endcan
                            </flux:table.cell>
                        </flux:table.row>
                    @empty
                        <flux:table.row>
                            <flux:table.cell colspan="9">
                                <x-empty-state icon="users" :heading="__('No personnel found')" :text="__('Try adjusting the filters or search term.')" />
                            </flux:table.cell>
                        </flux:table.row>
                    @endforelse
                </flux:table.rows>
            </flux:table>
        </div>
    </x-panel>
</div>
