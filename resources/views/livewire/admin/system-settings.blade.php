<div class="max-w-5xl">
    <x-page-header :title="__('System Settings')" :subtitle="__('Defaults are system suggestions, not official PNP policy. Align them with the applicable Health Service issuances.')">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" wire:click="save">{{ __('Save settings') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        <x-panel :heading="__('Letterhead (printed forms)')">
            <div class="grid gap-4 sm:grid-cols-3">
                <flux:input wire:model="values.organization_name" :label="__('Organization')" required />
                <flux:input wire:model="values.facility_name" :label="__('Facility')" />
                <flux:input wire:model="values.facility_address" :label="__('Address')" />
            </div>
        </x-panel>

        <x-panel :heading="__('BMI computation')" :subheading="__('BMI = weight (kg) ÷ height (m)². The IBW on the reference form (52.8–71.4 kg at 169 cm) equals BMI 18.5–25.0 × height².')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:select wire:model="values.bmi_precision" :label="__('BMI decimal places')">
                    <flux:select.option value="1">1 (e.g. 23.7)</flux:select.option>
                    <flux:select.option value="2">2 (e.g. 23.70)</flux:select.option>
                </flux:select>
                <div class="sm:col-span-3">
                    <flux:select wire:model.live="values.ibw_method" :label="__('Ideal body weight method')">
                        @foreach ($ibwMethods as $value => $label)
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endforeach
                    </flux:select>
                </div>
                <flux:input wire:model="values.ibw_bmi_lower" type="number" step="0.1" :label="__('IBW lower BMI')" :description="__('BMI range method')" />
                <flux:input wire:model="values.ibw_bmi_upper" type="number" step="0.1" :label="__('IBW upper BMI')" :description="__('BMI range method')" />
                <flux:input wire:model="values.ibw_tolerance_percent" type="number" step="0.5" :label="__('Formula tolerance (%)')" :description="__('Hamwi / Devine methods')" />
            </div>
        </x-panel>

        <x-panel :heading="__('Measurement validation ranges')" :subheading="__('Entries outside these ranges are rejected, catching unit mistakes such as metres or pounds.')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                @foreach (['height' => ['cm', __('Height')], 'weight' => ['kg', __('Weight')], 'waist' => ['cm', __('Waist')], 'hip' => ['cm', __('Hip')], 'wrist' => ['cm', __('Wrist')]] as $measure => [$unit, $label])
                    <div class="grid grid-cols-2 gap-2">
                        <flux:input wire:model="values.{{ $measure }}_min_{{ $unit }}" type="number" step="0.1" :label="$label.' min ('.$unit.')'" />
                        <flux:input wire:model="values.{{ $measure }}_max_{{ $unit }}" type="number" step="0.1" :label="__('max')" />
                    </div>
                @endforeach
            </div>
        </x-panel>

        <x-panel :heading="__('Monitoring schedule & compliance rules')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <flux:input wire:model="values.monitoring_deadline_day" type="number" :label="__('Monthly deadline (day of month)')" :description="__('Weights after this day count as late/missing')" />
                <flux:input wire:model="values.reminder_days_before" type="number" :label="__('Reminder (days before deadline)')" />
                <flux:input wire:model="values.compliance_target_percent" type="number" :label="__('Compliance target (%)')" />
                <flux:input wire:model="values.assessment_validity_months" type="number" :label="__('Assessment validity (months)')" :description="__('Older assessments are flagged as outdated')" />
                <flux:input wire:model="values.significant_weight_change_kg" type="number" step="0.1" :label="__('Significant monthly change (kg)')" />
                <flux:input wire:model="values.significant_bmi_change" type="number" step="0.1" :label="__('Significant BMI change (points)')" :description="__('Larger changes from the last assessment ask administrators whether to update waist, hip and wrist')" />
                <flux:input wire:model="values.trend_consecutive_months"type="number" :label="__('Consecutive months for a trend')" />
                <flux:input wire:model="values.follow_up_default_days" type="number" :label="__('Default follow-up interval (days)')" />
            </div>
        </x-panel>

        <x-panel :heading="__('Weigh-in Station')" :subheading="__('Personnel are identified from their QR code in the browser. Optionally, the scale photo can be read automatically by an AI service.')">
            <flux:radio.group wire:model="values.weight_reader" :label="__('Read the weight from the scale photo')">
                <flux:radio value="device" :label="__('On the phone (recommended)')" :description="__('Reads the red LED digits of the scale on the device itself. Free, works without internet, no photo leaves your server. Best with clear, straight-on photos.')" />
                <flux:radio value="ai" :label="__('AI (Anthropic API)')" :description="__('Sends each photo to the Anthropic API (Claude). Reads difficult photos better, costs about ₱0.50 per photo, requires ANTHROPIC_API_KEY and Data Privacy clearance because photos show the personnel.')" />
                <flux:radio value="off" :label="__('Off')" :description="__('Staff type the weight shown on the scale.')" />
            </flux:radio.group>
            <div class="mt-2">
                @if (filled(config('services.anthropic.key')))
                    <flux:badge size="sm" color="green">{{ __('API key configured') }}</flux:badge>
                @else
                    <flux:badge size="sm" color="zinc">{{ __('No API key — the AI option behaves like Off') }}</flux:badge>
                @endif
            </div>
            <div class="mt-4 max-w-sm">
                <flux:select wire:model="values.scale_decimals" :label="__('Decimals shown on the scale display')" :description="__('Readings with a different number of decimals are not trusted (they are usually a stray mark read as a digit).')">
                    <flux:select.option value="2">{{ __('Two (e.g. 59.80)') }}</flux:select.option>
                    <flux:select.option value="1">{{ __('One (e.g. 59.8)') }}</flux:select.option>
                    <flux:select.option value="auto">{{ __('One or two') }}</flux:select.option>
                </flux:select>
            </div>
            <div class="mt-4">
                <flux:switch wire:model="values.station_auto_save" :label="__('Save automatically when the reading is clear')" :description="__('No confirmation tap when the QR code is recognised, the reading is certain and within the significant-change limit of the last weigh-in. Everything else waits for staff to check.')" />
            </div>
        </x-panel>

        <x-panel :heading="__('Signatories & notifications')">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="values.default_hs_representative_name" :label="__('Default Health Service Representative')" />
                <flux:input wire:model="values.default_hs_representative_designation" :label="__('Designation')" />
                <div class="sm:col-span-2">
                    <flux:switch wire:model="values.notifications_email_enabled" :label="__('Also send notifications by email')" :description="__('Requires a configured mail server (MAIL_* settings). In-app notifications are always sent.')" />
                </div>
            </div>
        </x-panel>

        <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save settings') }}</flux:button></div>
    </form>
</div>
