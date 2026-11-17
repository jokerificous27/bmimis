{{-- Live BMI result at the Weigh-in Station: WHO and PNP, and the correction applied to an overweight or obese result. --}}
@php
    /** @var \App\Services\Bmi\BmiEvaluation|null $preview */
    $base = $preview?->pnpClassification;
    $above = $base?->category === \App\Enums\ClassificationCategory::Above;
@endphp

<div class="rounded-xl border border-zinc-200 bg-white p-4 dark:border-zinc-700 dark:bg-zinc-900" wire:key="bmi-result">
    @if ($preview)
        <div class="flex items-center justify-between gap-3">
            <div>
                <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('BMI result') }}</div>
                <div class="text-4xl font-bold tabular-nums text-navy-900 dark:text-white">{{ number_format($preview->bmi, 1) }}</div>
            </div>
            <dl class="space-y-1.5 text-sm">
                <div class="flex items-center justify-end gap-2"><dt class="text-xs text-zinc-500">{{ __('WHO') }}</dt><dd><x-bmi-badge :label="$preview->whoClassification?->label ?? __('Not configured')" :category="$preview->whoClassification?->category" /></dd></div>
                <div class="flex items-center justify-end gap-2"><dt class="text-xs text-zinc-500">{{ __('PNP') }}</dt><dd><x-bmi-badge :label="$base?->label ?? __('Not configured')" :category="$base?->category" /></dd></div>
            </dl>
        </div>

        @if ($above)
            <div class="mt-3 border-t border-zinc-200 pt-3 dark:border-zinc-700">
                @if ($askAbdomen)
                    <flux:radio.group wire:model.live="bulging_abdomen" variant="segmented" :label="__('Bulging abdomen?')"
                        :description="__('The result is above the acceptable BMI for the age. Look at the personnel from the side: without a bulging abdomen it may be corrected by large body frame or waist circumference.')">
                        <flux:radio value="no">{{ __('No') }}</flux:radio>
                        <flux:radio value="yes">{{ __('Yes, bulging') }}</flux:radio>
                    </flux:radio.group>
                @endif

                @if ($preview->pnpAcceptance)
                    <div class="mt-3 flex items-start gap-2 rounded-lg bg-green-50 p-3 text-sm text-green-900 dark:bg-green-950/40 dark:text-green-200">
                        <flux:icon.check-circle class="size-5 shrink-0 text-green-600" />
                        <div>
                            <div class="font-semibold">{{ __('Corrected to: :label', ['label' => __($preview->pnpAcceptance->label())]) }}</div>
                            <div class="text-xs">
                                @if ($preview->pnpAcceptance === \App\Enums\PnpAcceptance::LargeFrame)
                                    {{ __('Height and wrist ratio :ratio (:frame), no bulging abdomen.', ['ratio' => number_format((float) $preview->heightWristRatio, 1), 'frame' => __($preview->frameSize?->label() ?? '')]) }}
                                @else
                                    {{ __('Waist within the cut-off, no bulging abdomen.') }}
                                @endif
                            </div>
                        </div>
                    </div>
                @elseif (! $askAbdomen || $bulging_abdomen !== '')
                    <flux:text size="sm" class="mt-3">
                        {{ __('Final PNP classification: :label.', ['label' => $base->label]) }}
                        @if ($bulging_abdomen === 'yes')
                            {{ __('No correction with a bulging abdomen.') }}
                        @elseif ($bulging_abdomen === 'no')
                            {{ __('Not a large body frame within the limit for the age, and the waist is not within the cut-off.') }}
                        @endif
                    </flux:text>
                @endif
            </div>
        @endif
    @else
        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('BMI result') }}</div>
        <flux:text size="sm" class="mt-1">{{ __('Shown for WHO and PNP as soon as the weight and the height are entered.') }}</flux:text>
    @endif
</div>
