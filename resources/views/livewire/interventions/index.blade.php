<div>
    <x-page-header :title="__('Intervention Management')" :subtitle="__('Assign Health Service intervention packages, schedule follow-ups and record outcomes.')">
        <x-slot:actions>
            @can('reports.generate')
                <flux:button size="sm" icon="printer" :href="route('reports.print', ['report' => 'intervention', 'unit_id' => $unitId])" target="_blank">{{ __('Report') }}</flux:button>
            @endcan
            @if ($this->canManage)
                <flux:button size="sm" variant="primary" icon="plus" wire:click="openAssign">{{ __('Assign intervention') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="grid gap-6 xl:grid-cols-4">
        <div class="xl:col-span-3">
            <x-panel :padding="false">
                <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-2 lg:grid-cols-5 dark:border-zinc-800">
                    <div class="lg:col-span-2"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search personnel')" clearable /></div>
                    <flux:select wire:model.live="status">
                        <flux:select.option value="">{{ __('Any status') }}</flux:select.option>
                        @foreach ($statuses as $option)
                            <flux:select.option :value="$option->value">{{ $option->label() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="packageId">
                        <flux:select.option value="">{{ __('All packages') }}</flux:select.option>
                        @foreach ($allPackages as $package)
                            <flux:select.option :value="$package->id">{{ $package->displayName() }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:select wire:model.live="unitId">
                        <flux:select.option value="">{{ __('All units') }}</flux:select.option>
                        @foreach ($this->unitOptions as $unit)
                            <flux:select.option :value="$unit->id">{{ $unit->code }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <div class="lg:col-span-5"><flux:switch wire:model.live="overdue" :label="__('Only overdue follow-ups (:count)', ['count' => $overdueCount])" /></div>
                </div>
                <div class="overflow-x-auto px-4">
                    <flux:table :paginate="$interventions">
                        <flux:table.columns>
                            <flux:table.column>{{ __('Personnel') }}</flux:table.column>
                            <flux:table.column>{{ __('Unit') }}</flux:table.column>
                            <flux:table.column>{{ __('Package') }}</flux:table.column>
                            <flux:table.column>{{ __('Assigned') }}</flux:table.column>
                            <flux:table.column>{{ __('Next follow-up') }}</flux:table.column>
                            <flux:table.column align="end">{{ __('Start / target (kg)') }}</flux:table.column>
                            <flux:table.column>{{ __('Status') }}</flux:table.column>
                            <flux:table.column></flux:table.column>
                        </flux:table.columns>
                        <flux:table.rows>
                            @forelse ($interventions as $intervention)
                                <flux:table.row :key="$intervention->id">
                                    <flux:table.cell><a href="{{ route('personnel.show', $intervention->personnel) }}" wire:navigate class="font-medium text-navy-800 hover:underline dark:text-navy-200">{{ $intervention->personnel->rank?->abbreviation }} {{ $intervention->personnel->formal_name }}</a></flux:table.cell>
                                    <flux:table.cell>{{ $intervention->personnel->unit?->code }}</flux:table.cell>
                                    <flux:table.cell>{{ $intervention->package->displayName() }}</flux:table.cell>
                                    <flux:table.cell>{{ $intervention->assigned_on->format('M j, Y') }}</flux:table.cell>
                                    <flux:table.cell>
                                        @if ($intervention->next_follow_up_on)
                                            <span @class(['font-semibold text-red-600' => $intervention->isFollowUpOverdue()])>{{ $intervention->next_follow_up_on->format('M j, Y') }}</span>
                                        @else
                                            —
                                        @endif
                                    </flux:table.cell>
                                    <flux:table.cell align="end" class="tabular-nums">{{ $intervention->starting_weight_kg ? number_format($intervention->starting_weight_kg, 1) : '—' }} / {{ $intervention->target_weight_kg ? number_format($intervention->target_weight_kg, 1) : '—' }}</flux:table.cell>
                                    <flux:table.cell><flux:badge size="sm" :color="$intervention->status->color()">{{ $intervention->status->label() }}</flux:badge></flux:table.cell>
                                    <flux:table.cell align="end"><flux:button size="xs" variant="ghost" icon="eye" :href="route('interventions.show', $intervention)" wire:navigate /></flux:table.cell>
                                </flux:table.row>
                            @empty
                                <flux:table.row><flux:table.cell colspan="8"><x-empty-state icon="heart" :heading="__('No interventions found')" /></flux:table.cell></flux:table.row>
                            @endforelse
                        </flux:table.rows>
                    </flux:table>
                </div>
            </x-panel>
        </div>

        <x-panel :heading="__('Needs assignment')" :subheading="__(':count personnel whose PNP classification requires intervention but have none ongoing.', ['count' => $unassignedCount])" :padding="false">
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($unassigned as $person)
                    <div class="flex items-center justify-between gap-2 px-4 py-2.5 text-sm">
                        <div class="min-w-0">
                            <a href="{{ route('personnel.show', $person) }}" wire:navigate class="block truncate font-medium hover:underline">{{ $person->rank?->abbreviation }} {{ $person->formal_name }}</a>
                            <div class="text-xs text-zinc-500">{{ number_format($person->latestAssessment?->bmi, 1) }} · {{ $person->latestAssessment?->pnp_classification }}</div>
                        </div>
                        @if ($this->canManage)
                            <flux:button size="xs" wire:click="openAssign({{ $person->id }})">{{ __('Assign') }}</flux:button>
                        @endif
                    </div>
                @empty
                    <x-empty-state icon="check-badge" :heading="__('Everyone is covered')" class="py-6" />
                @endforelse
            </div>
        </x-panel>
    </div>

    <flux:modal name="assign-intervention" class="md:w-[32rem]">
        <form wire:submit="assign" class="space-y-5">
            <flux:heading size="lg">{{ __('Assign intervention package') }}</flux:heading>

            @if ($this->assignPersonnel)
                <div class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                    <div>
                        <div class="font-medium">{{ $this->assignPersonnel->rank_name }}</div>
                        <div class="text-xs text-zinc-500">{{ __('Latest BMI') }}: {{ $this->assignPersonnel->latestAssessment ? number_format($this->assignPersonnel->latestAssessment->bmi, 1).' · '.$this->assignPersonnel->latestAssessment->pnp_classification : '—' }}</div>
                    </div>
                    <flux:button size="xs" variant="ghost" wire:click="$set('assignPersonnelId', null)">{{ __('Change') }}</flux:button>
                </div>
            @else
                <div class="relative">
                    <flux:input wire:model.live.debounce.250ms="assignSearch" :label="__('Personnel')" icon="magnifying-glass" :placeholder="__('Search name or employee no.')" autocomplete="off" />
                    <flux:error name="assignPersonnelId" />
                    @if ($this->assignResults->isNotEmpty())
                        <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                            @foreach ($this->assignResults as $result)
                                <button type="button" wire:click="chooseAssignPersonnel({{ $result->id }})" class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">{{ $result->rank?->abbreviation }} {{ $result->formal_name }} <span class="text-zinc-500">· {{ $result->unit?->code }}</span></button>
                            @endforeach
                        </div>
                    @endif
                </div>
            @endif

            <flux:select wire:model.live="assignPackageId" :label="__('Intervention package')" required>
                <flux:select.option value="">{{ __('Select package') }}</flux:select.option>
                @foreach ($this->packageOptions as $package)
                    <flux:select.option :value="$package->id">{{ $package->displayName() }} — {{ $package->name }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:error name="intervention_package_id" />
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input type="date" wire:model.live="assignedOn" :label="__('Date assigned')" required />
                <flux:input type="date" wire:model="nextFollowUpOn" :label="__('First follow-up')" :description="__('Defaults to the package interval')" />
            </div>
            <flux:input wire:model="targetWeight" type="number" step="0.1" :label="__('Target weight (kg)')" :description="__('Optional; defaults to the IBW limit from the latest assessment')" />
            <flux:textarea wire:model="assignRemarks" :label="__('Remarks')" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Assign') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
