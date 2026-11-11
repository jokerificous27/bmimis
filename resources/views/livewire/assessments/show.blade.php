@php
    $a = $assessment;
    $person = $a->personnel;
    $user = auth()->user();
@endphp

<div>
    <x-page-header :title="__('BMI Assessment')" :subtitle="$person->rank_name.' · '.$a->assessed_on->format('F j, Y')">
        <x-slot:actions>
            <flux:badge :color="$a->status->color()">{{ $a->status->label() }}</flux:badge>
            @can('print', $a)
                <flux:button size="sm" icon="printer" :href="route('print.bmi-form', $a)" target="_blank">{{ __('Print BMI Form') }}</flux:button>
                <flux:button size="sm" icon="printer" :href="route('print.monitoring-form', ['personnel' => $person, 'year' => $a->assessed_on->year, 'assessment' => $a->id])" target="_blank">{{ __('Monitoring Form') }}</flux:button>
            @endcan
            @can('update', $a)
                <flux:button size="sm" icon="pencil-square" :href="route('assessments.edit', $a)" wire:navigate>{{ $a->isOfficial() ? __('Correct') : __('Edit draft') }}</flux:button>
            @endcan
            @can('certify', $a)
                <flux:modal.trigger name="return-assessment"><flux:button size="sm" variant="ghost">{{ __('Return') }}</flux:button></flux:modal.trigger>
                <flux:modal.trigger name="certify-assessment"><flux:button size="sm" variant="primary" icon="check-badge">{{ __('Certify correct') }}</flux:button></flux:modal.trigger>
            @endcan
        </x-slot:actions>
    </x-page-header>

    @if ($a->status === \App\Enums\AssessmentStatus::Superseded)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6" :heading="__('This assessment was superseded')">
            <flux:callout.text>
                {{ __('It is kept for historical reference.') }}
                @if ($a->supersededBy)
                    <a class="underline" href="{{ $isMine && ! $user->isStaff() ? route('my.assessments.show', $a->supersededBy) : route('assessments.show', $a->supersededBy) }}" wire:navigate>{{ __('Open the replacement assessment') }}</a>
                @endif
            </flux:callout.text>
        </flux:callout>
    @endif

    @if ($a->status === \App\Enums\AssessmentStatus::Submitted && $user->hasPermission(\App\Enums\Permission::CertifyAssessments) && $a->encoded_by === $user->id && ! $user->isSuperAdmin())
        <flux:callout icon="information-circle" class="mb-6">
            <flux:callout.text>{{ __('You encoded this assessment, so another authorized officer must certify it (segregation of duties).') }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-panel :heading="__('Personnel information')">
                <dl class="grid grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                    <div><dt class="text-xs text-zinc-500">{{ __('Rank (at assessment)') }}</dt><dd class="font-medium">{{ $a->rank?->abbreviation ?? $person->rank?->abbreviation }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-zinc-500">{{ __('Name') }}</dt><dd class="font-medium">
                        @if ($isMine && ! $user->isStaff())
                            {{ $person->formal_name }}
                        @else
                            <a href="{{ route('personnel.show', $person) }}" wire:navigate class="text-navy-800 hover:underline dark:text-navy-200">{{ $person->formal_name }}</a>
                        @endif
                    </dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Employee No.') }}</dt><dd class="font-medium">{{ $person->employee_number }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Age') }}</dt><dd class="font-medium">{{ $a->age_at_assessment ?? '—' }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Sex') }}</dt><dd class="font-medium">{{ $a->sex?->label() ?? $person->sex->label() }}</dd></div>
                    <div class="col-span-2"><dt class="text-xs text-zinc-500">{{ __('Office (at assessment)') }}</dt><dd class="font-medium">{{ $a->unit?->name ?? '—' }}</dd></div>
                </dl>
            </x-panel>

            <x-panel :heading="__('Measurements and results')" :padding="false">
                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead class="bg-zinc-50 text-start text-xs uppercase tracking-wide text-zinc-500 dark:bg-zinc-800">
                            <tr><th class="px-4 py-2 text-start">{{ __('Charts') }}</th><th class="px-4 py-2 text-end">{{ __('Result') }}</th><th class="px-4 py-2 text-start">{{ __('Remarks') }}</th></tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                            <tr><td class="px-4 py-2">{{ __('Height (cm)') }}</td><td class="px-4 py-2 text-end font-semibold tabular-nums">{{ number_format($a->height_cm, 1) }}</td><td class="px-4 py-2" rowspan="3"><strong>IBW:</strong> {{ $a->ibwRangeText() }}<div class="text-xs text-zinc-500">{{ $a->ibw_method->label() }}</div></td></tr>
                            <tr><td class="px-4 py-2">{{ __('Weight (kilogram)') }}</td><td class="px-4 py-2 text-end font-semibold tabular-nums">{{ number_format($a->weight_kg, 2) }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('Waist (cm)') }}</td><td class="px-4 py-2 text-end font-semibold tabular-nums">{{ $a->waist_cm ?? '—' }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('Hip (cm)') }}</td><td class="px-4 py-2 text-end font-semibold tabular-nums">{{ $a->hip_cm ?? '—' }}</td><td class="px-4 py-2" rowspan="2"><strong>{{ __('Weight to lose') }}:</strong> {{ $a->weightActionText() }}</td></tr>
                            <tr><td class="px-4 py-2">{{ __('Wrist (cm)') }}</td><td class="px-4 py-2 text-end font-semibold tabular-nums">{{ $a->wrist_cm ?? '—' }}</td></tr>
                            <tr class="bg-navy-50/50 dark:bg-navy-900/20">
                                <td class="px-4 py-3 font-semibold">{{ __('BMI Result') }}</td>
                                <td class="px-4 py-3 text-end text-2xl font-bold tabular-nums">{{ number_format($a->bmi, 1) }}</td>
                                <td class="px-4 py-3 space-y-1">
                                    <div><span class="text-xs text-zinc-500">PNP:</span> <x-bmi-badge :label="$a->pnp_classification" :category="$a->pnp_category" /></div>
                                    <div><span class="text-xs text-zinc-500">WHO:</span> <x-bmi-badge :label="$a->who_classification" :category="$a->who_category" /></div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </x-panel>

            @if (array_filter($a->photoPaths()))
                <x-panel :heading="__('Photos')">
                    <div class="grid grid-cols-3 gap-3">
                        @foreach (['right' => __('Right view'), 'front' => __('Front view'), 'left' => __('Left view')] as $view => $label)
                            <figure>
                                @if ($a->photoPaths()[$view])
                                    <img src="{{ route('assessments.photo', [$a, $view]) }}" alt="{{ $label }}" class="h-40 w-full rounded-lg object-cover">
                                @else
                                    <div class="flex h-40 items-center justify-center rounded-lg bg-zinc-100 text-xs text-zinc-500 dark:bg-zinc-800">{{ __('No photo') }}</div>
                                @endif
                                <figcaption class="mt-1 text-center text-xs text-zinc-500">{{ $label }}</figcaption>
                            </figure>
                        @endforeach
                    </div>
                </x-panel>
            @endif

            @if ($history->isNotEmpty())
                <x-panel :heading="__('Record history')" :subheading="__('Every change to this assessment, including corrections and certification.')" :padding="false">
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($history as $log)
                            <div class="p-4 text-sm" x-data="{ open: false }">
                                <div class="flex flex-wrap items-center justify-between gap-2">
                                    <div><span class="font-medium">{{ $log->description }}</span> · <span class="text-zinc-500">{{ $log->user?->name ?? __('System') }}, {{ $log->created_at->format('M j, Y g:i A') }}</span></div>
                                    @if ($log->old_values || $log->new_values)
                                        <flux:button size="xs" variant="ghost" x-on:click="open = ! open">{{ __('Changes') }}</flux:button>
                                    @endif
                                </div>
                                @if ($log->remarks)<div class="mt-1 text-xs text-amber-700 dark:text-amber-400">{{ $log->remarks }}</div>@endif
                                <div x-show="open" x-cloak class="mt-2">@include('livewire.admin.partials.diff', ['log' => $log])</div>
                            </div>
                        @endforeach
                    </div>
                </x-panel>
            @endif
        </div>

        <div class="space-y-6">
            <x-panel :heading="__('Intervention package')">
                <div class="font-medium">{{ $a->interventionPackage ? $a->interventionPackage->displayName().' — '.$a->interventionPackage->name : __('None') }}</div>
                <flux:text size="sm" class="mt-1">{{ $a->requires_intervention ? __('The PNP classification requires intervention.') : __('No intervention required by the PNP classification.') }}</flux:text>
                @if ($a->interventionPackage?->description)
                    <flux:text size="sm" class="mt-2">{{ $a->interventionPackage->description }}</flux:text>
                @endif
            </x-panel>

            <x-panel :heading="__('Signatories')">
                <dl class="space-y-3 text-sm">
                    <div><dt class="text-xs text-zinc-500">{{ __('Unit Representative') }}</dt><dd class="font-medium">{{ $a->unit_representative_name ?: '—' }}</dd><dd class="text-xs text-zinc-500">{{ $a->unit_representative_designation }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Health Service Representative') }}</dt><dd class="font-medium">{{ $a->health_service_representative_name ?: '—' }}</dd><dd class="text-xs text-zinc-500">{{ $a->health_service_representative_designation }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Encoder') }}</dt><dd class="font-medium">{{ $a->encoder?->name ?? '—' }}</dd><dd class="text-xs text-zinc-500">{{ $a->submitted_at ? __('Submitted :date', ['date' => $a->submitted_at->format('M j, Y g:i A')]) : __('Not yet submitted') }}</dd></div>
                    <div><dt class="text-xs text-zinc-500">{{ __('Certified correct') }}</dt><dd class="font-medium">{{ $a->certifier?->name ?? '—' }}</dd><dd class="text-xs text-zinc-500">{{ $a->certified_at?->format('M j, Y g:i A') }} {{ $a->certification_remarks ? '· '.$a->certification_remarks : '' }}</dd></div>
                </dl>
            </x-panel>

            @if ($a->remarks)
                <x-panel :heading="__('Remarks')"><flux:text>{{ $a->remarks }}</flux:text></x-panel>
            @endif
        </div>
    </div>

    <flux:modal name="certify-assessment" class="md:w-[28rem]">
        <form wire:submit="certify" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Certify assessment as correct') }}</flux:heading>
                <flux:text class="mt-1">{{ __('You certify that the measurements and results of this BMI assessment are correct. This action is recorded in the audit trail.') }}</flux:text>
            </div>
            <flux:textarea wire:model="certificationRemarks" :label="__('Remarks (optional)')" rows="2" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Certify correct') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="return-assessment" class="md:w-[28rem]">
        <form wire:submit="returnToDraft" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Return to encoder') }}</flux:heading>
                <flux:text class="mt-1">{{ __('The assessment goes back to draft so the encoder can fix it.') }}</flux:text>
            </div>
            <flux:textarea wire:model="returnReason" :label="__('Reason')" rows="2" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="danger">{{ __('Return as draft') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
