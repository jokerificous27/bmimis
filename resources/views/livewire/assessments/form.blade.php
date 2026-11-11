@php
    $person = $this->selectedPersonnel;
    $preview = $this->preview;
@endphp

<div>
    <x-page-header
        :title="$assessment ? ($isCorrection ? __('Correct BMI assessment') : __('Edit draft assessment')) : __('New BMI assessment')"
        :subtitle="__('Digital PNP Health Service Body Mass Index (BMI) Form. Results are computed automatically.')"
    >
        <x-slot:actions>
            <flux:button size="sm" variant="ghost" :href="$assessment ? route('assessments.show', $assessment) : route('assessments.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($isCorrection)
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6" :heading="__('You are correcting an official assessment')">
            <flux:callout.text>{{ __('The previous values and your reason will be kept in the audit trail. A corrected certified assessment returns to "Submitted" and must be certified again.') }}</flux:callout.text>
        </flux:callout>
    @endif

    <form wire:submit="review" class="grid gap-6 xl:grid-cols-3">
        <div class="space-y-6 xl:col-span-2">
            <x-panel :heading="__('Personnel information')">
                @if ($person)
                    <div class="flex flex-wrap items-start justify-between gap-4">
                        <dl class="grid flex-1 grid-cols-2 gap-x-6 gap-y-3 text-sm sm:grid-cols-4">
                            <div><dt class="text-xs text-zinc-500">{{ __('Rank') }}</dt><dd class="font-medium">{{ $person->rank?->abbreviation }}</dd></div>
                            <div class="col-span-2"><dt class="text-xs text-zinc-500">{{ __('Surname, First name MI') }}</dt><dd class="font-medium">{{ $person->formal_name }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Q') }}</dt><dd class="font-medium">{{ $person->qualifier ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Age') }}</dt><dd class="font-medium">{{ $person->ageOn(\Carbon\CarbonImmutable::parse($assessed_on ?: today())) ?? '—' }}</dd></div>
                            <div><dt class="text-xs text-zinc-500">{{ __('Sex') }}</dt><dd class="font-medium">{{ $person->sex->initial() }}</dd></div>
                            <div class="col-span-2"><dt class="text-xs text-zinc-500">{{ __('Office') }}</dt><dd class="font-medium">{{ $person->unit?->name ?? '—' }}</dd></div>
                        </dl>
                        @unless ($assessment)
                            <flux:button size="sm" variant="ghost" icon="x-mark" wire:click="clearPersonnel">{{ __('Change') }}</flux:button>
                        @endunless
                    </div>
                @else
                    <div class="relative">
                        <flux:input wire:model.live.debounce.250ms="personnelSearch" icon="magnifying-glass" :label="__('Search and select personnel')" :placeholder="__('Type a name or employee number')" autocomplete="off" />
                        <flux:error name="personnelId" />
                        @if ($this->personnelResults->isNotEmpty())
                            <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                @foreach ($this->personnelResults as $result)
                                    <button type="button" wire:click="selectPersonnel({{ $result->id }})" class="flex w-full items-center justify-between px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">
                                        <span><span class="font-medium">{{ $result->rank?->abbreviation }} {{ $result->formal_name }}</span> <span class="text-zinc-500">· {{ $result->employee_number }}</span></span>
                                        <span class="text-xs text-zinc-500">{{ $result->unit?->code }}</span>
                                    </button>
                                @endforeach
                            </div>
                        @elseif (mb_strlen(trim($personnelSearch)) >= 2)
                            <flux:text size="sm" class="mt-2">{{ __('No active personnel match your search.') }}</flux:text>
                        @endif
                    </div>
                @endif
            </x-panel>

            <x-panel :heading="__('Measurement information')" :subheading="__('Height and lengths in centimetres; weight in kilograms.')">
                <div class="grid gap-4 sm:grid-cols-3">
                    <flux:input type="date" wire:model.live.blur="assessed_on" :label="__('Assessment date')" required :max="today()->format('Y-m-d')" />
                    <flux:input wire:model.live.debounce.400ms="height_cm" :label="__('Height (cm)')" type="number" step="0.1" inputmode="decimal" placeholder="169" required />
                    <flux:input wire:model.live.debounce.400ms="weight_kg" :label="__('Weight (kg)')" type="number" step="0.01" inputmode="decimal" placeholder="67.70" required />
                    <flux:input wire:model.live.debounce.400ms="waist_cm" :label="__('Waist (cm)')" type="number" step="0.1" inputmode="decimal" />
                    <flux:input wire:model.live.debounce.400ms="hip_cm" :label="__('Hip (cm)')" type="number" step="0.1" inputmode="decimal" />
                    <flux:input wire:model.blur="wrist_cm" :label="__('Wrist (cm)')" type="number" step="0.1" inputmode="decimal" />
                </div>

                @if ($this->ibwIsManual)
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <flux:input wire:model.live.debounce.400ms="ibw_min_kg" :label="__('Official IBW minimum (kg)')" type="number" step="0.1" required />
                        <flux:input wire:model.live.debounce.400ms="ibw_max_kg" :label="__('Official IBW maximum (kg)')" type="number" step="0.1" required />
                    </div>
                    <flux:text size="sm" class="mt-2">{{ __('The IBW method is set to "manually encoded" in System Settings.') }}</flux:text>
                @endif
            </x-panel>

            <x-panel :heading="__('Photos (optional)')" :subheading="__('Right, front and left views for the Individual BMI Monitoring Form. Stored privately; JPG/PNG/WebP up to 4 MB.')">
                <div class="grid gap-4 sm:grid-cols-3">
                    @foreach (['right' => __('Right view'), 'front' => __('Front view'), 'left' => __('Left view')] as $view => $label)
                        <div>
                            <flux:input type="file" wire:model="photo_{{ $view }}" :label="$label" accept="image/jpeg,image/png,image/webp" />
                            <div wire:loading wire:target="photo_{{ $view }}" class="mt-1 text-xs text-zinc-500">{{ __('Uploading…') }}</div>
                            @if ($this->{'photo_'.$view} && method_exists($this->{'photo_'.$view}, 'isPreviewable') && $this->{'photo_'.$view}->isPreviewable())
                                <img src="{{ $this->{'photo_'.$view}->temporaryUrl() }}" alt="{{ $label }}" class="mt-2 h-32 w-full rounded-lg object-cover">
                            @elseif ($assessment?->photoPaths()[$view])
                                <img src="{{ route('assessments.photo', [$assessment, $view]) }}" alt="{{ $label }}" class="mt-2 h-32 w-full rounded-lg object-cover">
                            @endif
                        </div>
                    @endforeach
                </div>
            </x-panel>

            <x-panel :heading="__('Certification')">
                <div class="grid gap-4 sm:grid-cols-2">
                    <flux:input wire:model="unit_representative_name" :label="__('Unit Representative')" />
                    <flux:input wire:model="unit_representative_designation" :label="__('Designation')" />
                    <flux:input wire:model="health_service_representative_name" :label="__('Health Service Representative')" />
                    <flux:input wire:model="health_service_representative_designation" :label="__('Designation')" />
                    <div class="sm:col-span-2">
                        <flux:text size="sm">{{ __('Encoder') }}: <strong>{{ $assessment?->encoder?->name ?? auth()->user()->name }}</strong>. {{ __('Certification ("Certified Correct") is done by an authorized Health Service officer after submission.') }}</flux:text>
                    </div>
                    <div class="sm:col-span-2">
                        <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" />
                    </div>
                    @if ($isCorrection)
                        <div class="sm:col-span-2">
                            <flux:textarea wire:model="correction_reason" :label="__('Reason for correction')" rows="2" required />
                        </div>
                    @endif
                </div>
            </x-panel>
        </div>

        <div class="space-y-6">
            <div class="xl:sticky xl:top-6 space-y-6">
                <x-panel :heading="__('Automatic results')">
                    @if ($preview)
                        <div class="text-center">
                            <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('BMI Result') }}</div>
                            <div class="text-5xl font-bold tabular-nums text-navy-900 dark:text-white">{{ number_format($preview->bmi, 1) }}</div>
                            <div class="mt-2 flex justify-center gap-2">
                                <span class="text-xs text-zinc-500">{{ __('PNP') }}:</span> <x-bmi-badge :label="$preview->pnpClassification?->label ?? __('Not configured')" :category="$preview->pnpClassification?->category" />
                                <span class="text-xs text-zinc-500">{{ __('WHO') }}:</span> <x-bmi-badge :label="$preview->whoClassification?->label ?? __('Not configured')" :category="$preview->whoClassification?->category" />
                            </div>
                        </div>
                        <dl class="mt-4 space-y-2 text-sm">
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Ideal body weight') }}</dt><dd class="font-medium">{{ $preview->idealBodyWeight->label() }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Weight to lose / maintain') }}</dt><dd class="font-medium">{{ $preview->weightActionText() }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Waist–hip ratio') }}</dt><dd class="font-medium">{{ $preview->waistHipRatio ? number_format($preview->waistHipRatio, 2) : '—' }}</dd></div>
                            <div class="flex justify-between"><dt class="text-zinc-500">{{ __('Requires intervention') }}</dt><dd class="font-medium">{{ $preview->requiresIntervention() ? __('Yes') : __('No') }}</dd></div>
                        </dl>
                        <flux:text size="xs" class="mt-3">{{ __('IBW method') }}: {{ $ibwMethod->label() }}</flux:text>
                    @else
                        <x-empty-state icon="calculator" :heading="__('Enter height and weight')" :text="__('Select the personnel and encode height (cm) and weight (kg) to compute the BMI.')" class="py-4" />
                    @endif
                </x-panel>

                <x-panel :heading="__('Intervention package')">
                    <flux:select wire:model="intervention_package_id">
                        <flux:select.option value="">{{ $preview?->suggestedInterventionPackageId() ? __('Use suggested package') : __('None') }}</flux:select.option>
                        @foreach ($this->packageOptions as $package)
                            <flux:select.option :value="$package->id">{{ $package->displayName() }} — {{ $package->name }}{{ $preview?->suggestedInterventionPackageId() === $package->id ? ' ('.__('suggested').')' : '' }}</flux:select.option>
                        @endforeach
                    </flux:select>
                    <flux:text size="xs" class="mt-2">{{ __('Suggestions come from the PNP classification settings and remain subject to Health Service approval.') }}</flux:text>
                </x-panel>

                @unless ($isCorrection)
                    <flux:checkbox wire:model="record_monthly" :label="__('Also record this weight as the monthly weigh-in for the assessment month (if none exists)')" />
                @endunless

                <div class="flex flex-col gap-2">
                    @if ($isCorrection)
                        <flux:button type="button" variant="primary" wire:click="submit">{{ __('Save correction') }}</flux:button>
                    @else
                        <flux:button type="submit" variant="primary" icon="eye">{{ __('Preview and submit') }}</flux:button>
                        <flux:button type="button" wire:click="saveDraft">{{ __('Save as draft') }}</flux:button>
                    @endif
                </div>
            </div>
        </div>
    </form>

    <flux:modal name="review-assessment" class="md:w-[34rem]">
        <div class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Review before submitting') }}</flux:heading>
                <flux:text class="mt-1">{{ $person?->rank_name }} · {{ $assessed_on ? \Carbon\CarbonImmutable::parse($assessed_on)->format('F j, Y') : '' }}</flux:text>
            </div>

            @if ($preview)
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        <tr><td class="py-1.5 text-zinc-500">{{ __('Height / Weight') }}</td><td class="py-1.5 text-end font-medium">{{ $height_cm }} cm / {{ $weight_kg }} kg</td></tr>
                        <tr><td class="py-1.5 text-zinc-500">{{ __('Waist / Hip / Wrist') }}</td><td class="py-1.5 text-end font-medium">{{ $waist_cm ?: '—' }} / {{ $hip_cm ?: '—' }} / {{ $wrist_cm ?: '—' }} cm</td></tr>
                        <tr><td class="py-1.5 text-zinc-500">{{ __('BMI') }}</td><td class="py-1.5 text-end text-lg font-bold">{{ number_format($preview->bmi, 1) }}</td></tr>
                        <tr><td class="py-1.5 text-zinc-500">{{ __('PNP / WHO') }}</td><td class="py-1.5 text-end font-medium">{{ $preview->pnpClassification?->label ?? '—' }} / {{ $preview->whoClassification?->label ?? '—' }}</td></tr>
                        <tr><td class="py-1.5 text-zinc-500">{{ __('IBW') }}</td><td class="py-1.5 text-end font-medium">{{ $preview->idealBodyWeight->label() }}</td></tr>
                        <tr><td class="py-1.5 text-zinc-500">{{ __('Weight to lose') }}</td><td class="py-1.5 text-end font-medium">{{ $preview->weightActionText() }}</td></tr>
                    </tbody>
                </table>
            @endif

            @error('assessed_on')
                <flux:callout variant="warning" icon="exclamation-triangle">
                    <flux:callout.text>{{ $message }}</flux:callout.text>
                </flux:callout>
                <flux:checkbox wire:model="confirm_replace" :label="__('I confirm: supersede the existing assessment for this date')" />
            @enderror

            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Back to editing') }}</flux:button></flux:modal.close>
                <flux:button variant="primary" wire:click="submit">{{ __('Submit assessment') }}</flux:button>
            </div>
        </div>
    </flux:modal>
</div>
