@php
    /** @var \App\Models\Personnel $personnel */
    $latest = $this->latest;
    $user = auth()->user();
    $canPrint = $user->can('printReports', $personnel);
    $tabs = [
        'overview' => __('Overview'),
        'progress' => __('BMI Progress'),
        'assessments' => __('Assessment History'),
        'monitoring' => __('Monthly Monitoring'),
        'interventions' => __('Interventions'),
        'history' => __('Assignments & Audit'),
    ];
    $categoryTone = match ($latest?->pnp_category?->value) {
        'normal' => 'border-green-500 bg-green-50 text-green-800 dark:bg-green-950/40 dark:text-green-300',
        'below' => 'border-amber-500 bg-amber-50 text-amber-800 dark:bg-amber-950/40 dark:text-amber-300',
        'above' => 'border-red-500 bg-red-50 text-red-800 dark:bg-red-950/40 dark:text-red-300',
        default => 'border-zinc-300 bg-zinc-50 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300',
    };
    $bmiPosition = $latest ? max(0, min(100, ($latest->bmi - 15) / (40 - 15) * 100)) : null;
@endphp

<div>
    {{-- Profile header --}}
    <div class="mb-6 rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-900">
        <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex items-center gap-4">
                <div class="flex size-14 shrink-0 items-center justify-center rounded-full bg-navy-800 text-lg font-semibold text-white">
                    {{ \Illuminate\Support\Str::substr($personnel->first_name, 0, 1) }}{{ \Illuminate\Support\Str::substr($personnel->last_name, 0, 1) }}
                </div>
                <div class="min-w-0">
                    <flux:heading size="xl" level="1" class="text-navy-900 dark:text-white">{{ $personnel->rank?->abbreviation }} {{ $personnel->formal_name }}</flux:heading>
                    <div class="mt-1 flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-zinc-500 dark:text-zinc-400">
                        <span>{{ $personnel->employee_number }}</span>
                        <span>·</span>
                        <span>{{ $personnel->unit?->name ?? __('No unit') }}</span>
                        @if ($personnel->position)
                            <span>·</span><span>{{ $personnel->position }}</span>
                        @endif
                        <flux:badge size="sm" :color="$personnel->status->color()">{{ $personnel->status->label() }}</flux:badge>
                    </div>
                </div>
            </div>

            <div class="flex flex-wrap gap-2">
                @can('encodeAssessment', $personnel)
                    <flux:button size="sm" variant="primary" icon="plus" :href="route('assessments.create', ['personnel' => $personnel->id])" wire:navigate>{{ __('New assessment') }}</flux:button>
                @endcan
                @can('encodeMonitoring', $personnel)
                    <flux:button size="sm" icon="scale" :href="route('monitoring.personnel', $personnel)" wire:navigate>{{ __('Monthly weights') }}</flux:button>
                @endcan
                @if ($canPrint)
                    <flux:dropdown>
                        <flux:button size="sm" icon="printer" icon:trailing="chevron-down">{{ __('Print') }}</flux:button>
                        <flux:menu>
                            @if ($latest)
                                <flux:menu.item icon="document-text" :href="route('print.bmi-form', $latest)" target="_blank">{{ __('BMI Form (latest)') }}</flux:menu.item>
                            @endif
                            <flux:menu.item icon="document-text" :href="route('print.monitoring-form', ['personnel' => $personnel, 'year' => $year])" target="_blank">{{ __('BMI Monitoring Form') }} ({{ $year }})</flux:menu.item>
                            <flux:menu.item icon="document-text" :href="route('print.profile', ['personnel' => $personnel, 'year' => $year])" target="_blank">{{ __('Consolidated profile') }}</flux:menu.item>
                            <flux:menu.item icon="qr-code" :href="route('print.qr-cards', ['personnel_id' => $personnel->id])" target="_blank">{{ __('Weigh-in QR card') }}</flux:menu.item>
                            @can('update', $personnel)
                                <flux:menu.separator />
                                <flux:menu.item icon="arrow-path" wire:click="reissueQr" wire:confirm="{{ __('Issue a new QR code? The current card will stop working.') }}">{{ __('Re-issue QR code (lost card)') }}</flux:menu.item>
                            @endcan
                        </flux:menu>
                    </flux:dropdown>
                @endif
                @can('update', $personnel)
                    <flux:button size="sm" variant="ghost" icon="pencil-square" :href="route('personnel.edit', $personnel)" wire:navigate>{{ __('Edit') }}</flux:button>
                @endcan
            </div>
        </div>

        <nav class="-mb-5 mt-5 flex gap-1 overflow-x-auto border-t border-zinc-100 pt-2 dark:border-zinc-800" aria-label="{{ __('Profile sections') }}">
            @foreach ($tabs as $key => $label)
                <button type="button" wire:click="$set('tab', '{{ $key }}')"
                    @class([
                        'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium transition',
                        'border-navy-800 text-navy-800 dark:border-navy-200 dark:text-navy-200' => $tab === $key,
                        'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $tab !== $key,
                    ])>{{ $label }}</button>
            @endforeach
        </nav>
    </div>

    @if ($tab === 'overview')
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="space-y-6 lg:col-span-2">
                <x-panel :heading="__('Current BMI information')" :subheading="$latest ? __('Latest assessment: :date', ['date' => $latest->assessed_on->format('F j, Y')]) : null">
                    @if ($latest)
                        <x-slot:actions>
                            <flux:badge size="sm" :color="$latest->status->color()">{{ $latest->status->label() }}</flux:badge>
                        </x-slot:actions>

                        <div class="grid gap-6 md:grid-cols-[14rem_1fr]">
                            <div class="flex flex-col items-center justify-center rounded-xl border-2 p-4 text-center {{ $categoryTone }}">
                                <div class="text-xs font-semibold uppercase tracking-wide opacity-80">{{ __('BMI') }}</div>
                                <div class="text-5xl font-bold tabular-nums">{{ number_format($latest->bmi, 1) }}</div>
                                <div class="mt-1 text-sm font-semibold">{{ $latest->pnp_classification ?? __('Unclassified') }}</div>
                                <div class="text-xs opacity-80">{{ __('PNP classification') }}</div>
                            </div>

                            <div>
                                <div class="mb-4">
                                    <div class="relative h-3 overflow-hidden rounded-full bg-gradient-to-r from-amber-300 via-green-400 via-45% to-red-500"></div>
                                    @if ($bmiPosition !== null)
                                        <div class="relative h-0">
                                            <div class="absolute -top-4 h-5 w-1 -translate-x-1/2 rounded bg-zinc-900 dark:bg-white" style="left: {{ $bmiPosition }}%"></div>
                                        </div>
                                    @endif
                                    <div class="mt-1 flex justify-between text-[10px] text-zinc-500"><span>15</span><span>18.5</span><span>25</span><span>30</span><span>40</span></div>
                                </div>

                                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-3">
                                    <div><dt class="text-xs text-zinc-500">{{ __('Height') }}</dt><dd class="font-medium tabular-nums">{{ number_format($latest->height_cm, 1) }} cm</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Weight') }}</dt><dd class="font-medium tabular-nums">{{ number_format($latest->weight_kg, 2) }} kg</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('WHO classification') }}</dt><dd><x-bmi-badge :label="$latest->who_classification" :category="$latest->who_category" /></dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Waist') }}</dt><dd class="font-medium tabular-nums">{{ $latest->waist_cm ? number_format($latest->waist_cm, 1).' cm' : '—' }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Hip') }}</dt><dd class="font-medium tabular-nums">{{ $latest->hip_cm ? number_format($latest->hip_cm, 1).' cm' : '—' }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Wrist') }}</dt><dd class="font-medium tabular-nums">{{ $latest->wrist_cm ? number_format($latest->wrist_cm, 1).' cm' : '—' }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Ideal body weight') }}</dt><dd class="font-medium">{{ $latest->ibwRangeText() }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Weight to lose / maintain') }}</dt><dd class="font-medium">{{ $latest->weightActionText() }}</dd></div>
                                    <div><dt class="text-xs text-zinc-500">{{ __('Waist–hip ratio') }}</dt><dd class="font-medium tabular-nums">{{ $latest->waist_hip_ratio ? number_format($latest->waist_hip_ratio, 2) : '—' }}</dd></div>
                                    <div class="col-span-2 sm:col-span-3"><dt class="text-xs text-zinc-500">{{ __('Intervention package') }}</dt>
                                        <dd class="font-medium">{{ $personnel->activeIntervention?->package?->displayName() ?? $latest->interventionPackage?->displayName() ?? __('None') }}
                                            @if ($personnel->activeIntervention)
                                                <span class="text-xs font-normal text-zinc-500">— {{ $personnel->activeIntervention->package->name }} · {{ __('next follow-up') }} {{ $personnel->activeIntervention->next_follow_up_on?->format('M j, Y') ?? '—' }}</span>
                                            @endif
                                        </dd>
                                    </div>
                                </dl>
                            </div>
                        </div>
                    @else
                        <x-empty-state icon="clipboard-document-list" :heading="__('No BMI assessment yet')" :text="__('A full BMI assessment from the Health Service is required to establish the BMI profile.')">
                            @can('encodeAssessment', $personnel)
                                <flux:button size="sm" variant="primary" :href="route('assessments.create', ['personnel' => $personnel->id])" wire:navigate>{{ __('Encode first assessment') }}</flux:button>
                            @endcan
                        </x-empty-state>
                    @endif
                </x-panel>

                <x-panel :heading="__('Monthly weight trend — :year', ['year' => $year])">
                    <x-chart :config="$this->charts['weight']" height="15rem" :aria-label="__('Monthly weight trend')" />
                </x-panel>
            </div>

            <div class="space-y-6">
                <x-panel :heading="__('Personal information')">
                    <dl class="space-y-2.5 text-sm">
                        @foreach ([
                            __('Personnel ID') => $personnel->employee_number,
                            __('Rank') => $personnel->rank?->name,
                            __('Last name') => $personnel->last_name,
                            __('First name') => $personnel->first_name,
                            __('Middle name') => $personnel->middle_name,
                            __('Suffix') => $personnel->suffix,
                            __('Gender') => $personnel->sex->label(),
                            __('Date of birth') => $personnel->date_of_birth?->format('F j, Y'),
                            __('Age') => $personnel->age,
                            __('Unit / Office') => $personnel->unit?->name,
                            __('Position') => $personnel->position,
                            __('Contact') => $personnel->contact_number,
                            __('Account status') => $personnel->user ? ($personnel->user->is_active ? __('Active') : __('Disabled')) : __('No login account'),
                        ] as $label => $value)
                            <div class="flex justify-between gap-4 border-b border-zinc-100 pb-2 last:border-0 dark:border-zinc-800">
                                <dt class="text-zinc-500">{{ $label }}</dt>
                                <dd class="text-end font-medium">{{ filled($value) ? $value : '—' }}</dd>
                            </div>
                        @endforeach
                    </dl>
                </x-panel>

                <x-panel :heading="__('This month')">
                    @php($thisMonth = $this->sheet->firstWhere('month', (int) today()->format('n')))
                    <div class="flex items-center justify-between">
                        <div>
                            <div class="text-sm text-zinc-500">{{ today()->format('F Y') }}</div>
                            <div class="text-2xl font-semibold tabular-nums">{{ $year === (int) today()->format('Y') && $thisMonth['record'] ? number_format($thisMonth['record']->weight_kg, 2).' kg' : '—' }}</div>
                        </div>
                        <x-month-status :status="$year === (int) today()->format('Y') ? $thisMonth['status'] : $monitoringStatus" />
                    </div>
                    <flux:text size="sm" class="mt-2">{{ __('Deadline: :date', ['date' => app(\App\Services\MonthlyMonitoringService::class)->deadlineFor((int) today()->format('Y'), (int) today()->format('n'))->format('F j, Y')]) }}</flux:text>
                </x-panel>

                <flux:callout icon="information-circle" variant="secondary">
                    <flux:callout.text>{{ __('BMI results and intervention recommendations are informational and subject to Health Service evaluation. This system is not a medical diagnostic tool.') }}</flux:callout.text>
                </flux:callout>
            </div>
        </div>
    @endif

    @if ($tab === 'progress')
        <div class="mb-4 flex items-center gap-3">
            <flux:select wire:model.live="year" class="max-w-40">
                @foreach ($this->availableYears as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            <flux:text size="sm">{{ __('Select a year to view its monthly records.') }}</flux:text>
        </div>
        <div class="grid gap-6 lg:grid-cols-2">
            <x-panel :heading="__('Monthly weight trend — :year', ['year' => $year])"><x-chart :config="$this->charts['weight']" /></x-panel>
            <x-panel :heading="__('Monthly BMI — :year', ['year' => $year])"><x-chart :config="$this->charts['monthlyBmi']" /></x-panel>
            <x-panel :heading="__('BMI trend over time (assessments)')"><x-chart :config="$this->charts['bmiTrend']" /></x-panel>
            <x-panel :heading="__('Waist and hip measurement trend')"><x-chart :config="$this->charts['bodyMeasurements']" /></x-panel>
            <x-panel :heading="__('Historical comparison by year')" class="lg:col-span-2"><x-chart :config="$this->charts['yearComparison']" height="18rem" /></x-panel>
        </div>
    @endif

    @if ($tab === 'assessments')
        <x-panel :padding="false">
            <div class="overflow-x-auto px-4">
                <flux:table>
                    <flux:table.columns>
                        <flux:table.column>{{ __('Assessment date') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Weight') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Height') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('BMI') }}</flux:table.column>
                        <flux:table.column>{{ __('PNP') }}</flux:table.column>
                        <flux:table.column>{{ __('WHO') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Waist') }}</flux:table.column>
                        <flux:table.column align="end">{{ __('Hip') }}</flux:table.column>
                        <flux:table.column>{{ __('Package') }}</flux:table.column>
                        <flux:table.column>{{ __('Status') }}</flux:table.column>
                        <flux:table.column>{{ __('Recorded by') }}</flux:table.column>
                        <flux:table.column></flux:table.column>
                    </flux:table.columns>
                    <flux:table.rows>
                        @forelse ($this->assessments as $assessment)
                            <flux:table.row :key="$assessment->id">
                                <flux:table.cell>{{ $assessment->assessed_on->format('M j, Y') }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($assessment->weight_kg, 2) }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ number_format($assessment->height_cm, 1) }}</flux:table.cell>
                                <flux:table.cell align="end" class="font-medium tabular-nums">{{ number_format($assessment->bmi, 1) }}</flux:table.cell>
                                <flux:table.cell><x-bmi-badge :label="$assessment->pnp_classification" :category="$assessment->pnp_category" /></flux:table.cell>
                                <flux:table.cell><x-bmi-badge :label="$assessment->who_classification" :category="$assessment->who_category" /></flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $assessment->waist_cm ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end" class="tabular-nums">{{ $assessment->hip_cm ?? '—' }}</flux:table.cell>
                                <flux:table.cell>{{ $assessment->interventionPackage?->code ?? '—' }}</flux:table.cell>
                                <flux:table.cell><flux:badge size="sm" :color="$assessment->status->color()">{{ $assessment->status->label() }}</flux:badge></flux:table.cell>
                                <flux:table.cell>{{ $assessment->encoder?->name ?? '—' }}</flux:table.cell>
                                <flux:table.cell align="end">
                                    <flux:button size="xs" variant="ghost" icon="eye" :href="$isOwnProfile && ! $user->isStaff() ? route('my.assessments.show', $assessment) : route('assessments.show', $assessment)" wire:navigate />
                                </flux:table.cell>
                            </flux:table.row>
                        @empty
                            <flux:table.row><flux:table.cell colspan="12"><x-empty-state icon="clipboard-document-list" :heading="__('No assessments recorded')" /></flux:table.cell></flux:table.row>
                        @endforelse
                    </flux:table.rows>
                </flux:table>
            </div>
        </x-panel>
    @endif

    @if ($tab === 'monitoring')
        <x-panel :heading="__('Monthly weight monitoring')" :padding="false">
            <x-slot:actions>
                <flux:select wire:model.live="year" size="sm" class="w-28">
                    @foreach ($this->availableYears as $option)
                        <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:button size="sm" :href="$isOwnProfile && ! $user->isStaff() ? route('my.monitoring', ['year' => $year]) : route('monitoring.personnel', ['personnel' => $personnel, 'year' => $year])" wire:navigate>{{ __('Open monitoring sheet') }}</flux:button>
            </x-slot:actions>
            @include('livewire.monitoring.partials.year-table', ['sheet' => $this->sheet, 'editable' => false])
        </x-panel>
    @endif

    @if ($tab === 'interventions')
        <x-panel :heading="__('Interventions')" :padding="false">
            @can('manageInterventions', $personnel)
                <x-slot:actions>
                    <flux:button size="sm" icon="plus" :href="route('interventions.index', ['assign' => $personnel->id])" wire:navigate>{{ __('Assign intervention') }}</flux:button>
                </x-slot:actions>
            @endcan
            <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                @forelse ($interventions as $intervention)
                    <div class="flex flex-col gap-2 p-4 sm:flex-row sm:items-center sm:justify-between">
                        <div>
                            <div class="font-medium">{{ $intervention->package->displayName() }} — {{ $intervention->package->name }}</div>
                            <flux:text size="sm">{{ __('Assigned :date by :name', ['date' => $intervention->assigned_on->format('M j, Y'), 'name' => $intervention->assigner?->name ?? '—']) }}
                                @if ($intervention->next_follow_up_on) · {{ __('Next follow-up') }} {{ $intervention->next_follow_up_on->format('M j, Y') }} @endif</flux:text>
                            @if ($intervention->package->recommended_activity)
                                <flux:text size="sm" class="mt-1">{{ __('Recommended activity') }}: {{ $intervention->package->recommended_activity }}</flux:text>
                            @endif
                            @if ($intervention->outcome)
                                <flux:text size="sm" class="mt-1">{{ __('Outcome') }}: {{ $intervention->outcome }}</flux:text>
                            @endif
                        </div>
                        <div class="flex items-center gap-2">
                            <flux:badge size="sm" :color="$intervention->status->color()">{{ $intervention->status->label() }}</flux:badge>
                            @if ($intervention->isFollowUpOverdue())
                                <flux:badge size="sm" color="red">{{ __('Follow-up overdue') }}</flux:badge>
                            @endif
                            @can('view', $intervention)
                                @if ($user->isStaff() || $user->hasUnitScope())
                                    <flux:button size="xs" variant="ghost" icon="eye" :href="route('interventions.show', $intervention)" wire:navigate />
                                @endif
                            @endcan
                        </div>
                    </div>
                @empty
                    <x-empty-state icon="heart" :heading="__('No interventions assigned')" />
                @endforelse
            </div>
        </x-panel>
    @endif

    @if ($tab === 'history')
        <div class="grid gap-6 lg:grid-cols-2">
            <x-panel :heading="__('Assignment history')" :subheading="__('Transfers and rank changes. BMI records keep the unit and rank at the time they were taken.')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($assignments as $assignment)
                        <div class="p-4 text-sm">
                            <div class="font-medium">{{ $assignment->rank?->abbreviation }} · {{ $assignment->unit?->name ?? '—' }}</div>
                            <div class="text-zinc-500">{{ $assignment->effective_from->format('M j, Y') }} — {{ $assignment->effective_to?->format('M j, Y') ?? __('Present') }} @if ($assignment->position) · {{ $assignment->position }} @endif</div>
                            @if ($assignment->remarks)<div class="text-xs text-zinc-500">{{ $assignment->remarks }}</div>@endif
                        </div>
                    @empty
                        <x-empty-state icon="building-office" :heading="__('No assignment history')" />
                    @endforelse
                </div>
            </x-panel>

            @can('audit.view')
                <x-panel :heading="__('Recent audit trail')" :padding="false">
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @forelse ($activity as $log)
                            <div class="p-4 text-sm">
                                <div class="font-medium">{{ $log->description }}</div>
                                <div class="text-zinc-500">{{ $log->created_at->format('M j, Y g:i A') }} · {{ $log->user?->name ?? __('System') }}</div>
                                @if ($log->remarks)<div class="text-xs text-amber-700 dark:text-amber-400">{{ $log->remarks }}</div>@endif
                            </div>
                        @empty
                            <x-empty-state icon="shield-check" :heading="__('No audit entries')" />
                        @endforelse
                    </div>
                    <div class="border-t border-zinc-100 p-3 text-end dark:border-zinc-800">
                        <flux:link :href="route('admin.audit', ['personnel' => $personnel->id])" wire:navigate class="text-sm">{{ __('View full audit trail') }}</flux:link>
                    </div>
                </x-panel>
            @endcan
        </div>
    @endif
</div>
