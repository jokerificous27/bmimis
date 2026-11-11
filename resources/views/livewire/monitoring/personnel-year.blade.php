<div>
    <x-page-header :title="__('Monthly Weight Monitoring')" :subtitle="$personnel->rank_name.' · '.($personnel->unit?->name ?? __('No unit'))">
        <x-slot:actions>
            <flux:select wire:model.live="year" size="sm" class="w-28">
                @foreach ($this->yearOptions() as $option)
                    <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                @endforeach
            </flux:select>
            @can('printReports', $personnel)
                <flux:button size="sm" icon="printer" :href="route('print.monitoring-form', ['personnel' => $personnel, 'year' => $year])" target="_blank">{{ __('Print monitoring form') }}</flux:button>
            @endcan
            @if (auth()->user()->isStaff() || auth()->user()->hasUnitScope())
                <flux:button size="sm" variant="ghost" :href="route('personnel.show', $personnel)" wire:navigate>{{ __('Open profile') }}</flux:button>
            @endif
        </x-slot:actions>
    </x-page-header>

    <div class="mb-6 grid gap-4 sm:grid-cols-3">
        <x-stat-card :label="__('Months recorded')" :value="$recorded.' / '.$due" icon="check-badge" :tone="$recorded >= $due ? 'green' : 'amber'" :hint="__('Months due so far in :year', ['year' => $year])" />
        <x-stat-card :label="__('Monthly compliance')" :value="($due ? round($recorded / $due * 100) : 0).'%'" icon="chart-bar" />
        <x-stat-card :label="__('Height used for BMI')" :value="$applicable ? number_format($applicable->height_cm, 1).' cm' : '—'" icon="identification" tone="zinc" :hint="$applicable ? __('From assessment dated :date', ['date' => $applicable->assessed_on->format('M j, Y')]) : __('No assessment yet — BMI will not be computed')" />
    </div>

    @if ($this->pendingReviewId)
        <div class="mb-6">
            <livewire:monitoring.measurement-review :record-id="$this->pendingReviewId" :key="'measurement-review-'.$this->pendingReviewId" />
        </div>
    @endif

    <div class="grid gap-6 xl:grid-cols-3">
        <x-panel :heading="__('January – December :year', ['year' => $year])" :padding="false" class="xl:col-span-2">
            @include('livewire.monitoring.partials.year-table', ['sheet' => $this->sheet, 'editable' => true])
        </x-panel>
        <x-panel :heading="__('Weight trend')">
            <x-chart :config="$this->chart" height="18rem" />
        </x-panel>
    </div>

    <flux:modal name="monthly-entry" class="md:w-[28rem]">
        <form wire:submit="saveEntry" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Record monthly weight') }}</flux:heading>
                <flux:text class="mt-1">{{ $entryMonth ? \App\Models\MonthlyWeightMonitoring::MONTHS[$entryMonth].' '.$year : '' }} · {{ $personnel->rank_name }}</flux:text>
            </div>
            <flux:input wire:model="entryWeight" :label="__('Weight (kg)')" type="number" step="0.01" inputmode="decimal" required autofocus />
            <flux:input wire:model="entryMeasuredOn" :label="__('Date measured')" type="date" required />
            <flux:textarea wire:model="entryRemarks" :label="__('Remarks')" rows="2" />
            <flux:error name="month" />
            <flux:error name="weight_kg" />
            <flux:text size="sm">{{ __('BMI is computed automatically using the height from the applicable BMI assessment.') }}</flux:text>
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save weight') }}</flux:button>
            </div>
        </form>
    </flux:modal>

    <flux:modal name="monthly-correction" class="md:w-[28rem]">
        <form wire:submit="saveCorrection" class="space-y-5">
            <div>
                <flux:heading size="lg">{{ __('Correct monthly entry') }}</flux:heading>
                <flux:text class="mt-1">{{ __('Corrections never overwrite history silently: the previous value and your reason are kept in the audit trail.') }}</flux:text>
            </div>
            <flux:input wire:model="correctWeight" :label="__('Corrected weight (kg)')" type="number" step="0.01" required />
            <flux:input wire:model="correctMeasuredOn" :label="__('Date measured')" type="date" />
            <flux:textarea wire:model="correctReason" :label="__('Reason for correction')" rows="2" required />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save correction') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
