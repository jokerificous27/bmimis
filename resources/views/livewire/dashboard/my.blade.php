<div>
    <x-page-header :title="__('Welcome, :name', ['name' => $personnel->rank_name])" :subtitle="__(':unit · :id', ['unit' => $personnel->unit?->name ?? __('No unit'), 'id' => $personnel->employee_number])">
        <x-slot:actions>
            <flux:button size="sm" icon="identification" :href="route('my.profile')" wire:navigate>{{ __('My BMI profile') }}</flux:button>
            <flux:button size="sm" icon="printer" :href="route('print.monitoring-form', ['personnel' => $personnel, 'year' => $year])" target="_blank">{{ __('Print my monitoring form') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    @if ($thisMonth['status'] !== 'recorded')
        <flux:callout :variant="$thisMonth['status'] === 'missing' ? 'danger' : 'warning'" icon="calendar-days" class="mb-6" :heading="__('Your :month weigh-in is :status', ['month' => today()->format('F'), 'status' => strtolower($thisMonth['status'] === 'missing' ? __('overdue') : __('due'))])">
            <flux:callout.text>{{ __('Please report to your Health Service representative on or before :date.', ['date' => $deadline->format('F j, Y')]) }}</flux:callout.text>
        </flux:callout>
    @endif

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <x-stat-card :label="__('Current BMI')" :value="$latest ? number_format($latest->bmi, 1) : '—'" icon="scale" :tone="$latest?->pnp_category?->color() === 'green' ? 'green' : ($latest ? 'amber' : 'zinc')" :hint="$latest ? __('Assessed :date', ['date' => $latest->assessed_on->format('M j, Y')]) : __('No assessment yet')" />
        <x-stat-card :label="__('PNP classification')" :value="$latest?->pnp_classification ?? '—'" icon="check-badge" :tone="$latest?->pnp_category?->value === 'normal' ? 'green' : 'amber'" :hint="__('WHO: :class', ['class' => $latest?->who_classification ?? '—'])" />
        <x-stat-card :label="__('Ideal body weight')" :value="$latest ? $latest->ibwRangeText() : '—'" icon="adjustments-horizontal" :hint="$latest ? $latest->weightActionText() : null" />
        <x-stat-card :label="__('Monthly compliance :year', ['year' => $year])" :value="$recordedCount.' / '.$dueCount" icon="calendar" :tone="$recordedCount >= $dueCount ? 'green' : 'amber'" :hint="__('Months recorded so far')" />
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <x-panel :heading="__('My weight progress — :year', ['year' => $year])" class="lg:col-span-2">
            <x-chart :config="$chart" height="17rem" />
            <div class="mt-3 text-end"><flux:link :href="route('my.monitoring')" wire:navigate class="text-sm">{{ __('View monthly monitoring') }}</flux:link></div>
        </x-panel>

        <div class="space-y-6">
            <x-panel :heading="__('My weigh-in QR code')" :subheading="__('Show this at your monthly weigh-in, next to the scale display.')">
                <div class="flex flex-col items-center gap-3">
                    <canvas wire:ignore x-data x-init="window.renderWeighInQr($el, @js($qrPayload), 220)" class="rounded-lg bg-white p-2" aria-label="{{ __('My weigh-in QR code') }}"></canvas>
                    <flux:button size="sm" variant="primary" icon="qr-code" :href="route('my.qr')">{{ __('Show full screen for weigh-in') }}</flux:button>
                    <flux:button size="sm" variant="ghost" icon="printer" :href="route('print.qr-cards', ['personnel_id' => $personnel->id])" target="_blank">{{ __('Print my QR card') }}</flux:button>
                </div>
            </x-panel>

            <x-panel :heading="__('Intervention')">
                @if ($personnel->activeIntervention)
                    <div class="font-medium">{{ $personnel->activeIntervention->package->displayName() }} — {{ $personnel->activeIntervention->package->name }}</div>
                    <flux:text size="sm" class="mt-1">{{ __('Next follow-up: :date', ['date' => $personnel->activeIntervention->next_follow_up_on?->format('F j, Y') ?? '—']) }}</flux:text>
                    @if ($personnel->activeIntervention->package->recommended_activity)
                        <flux:text size="sm" class="mt-2"><strong>{{ __('Recommended activity') }}:</strong> {{ $personnel->activeIntervention->package->recommended_activity }}</flux:text>
                    @endif
                    @if ($personnel->activeIntervention->package->nutrition_guidance)
                        <flux:text size="sm" class="mt-1"><strong>{{ __('Nutrition guidance') }}:</strong> {{ $personnel->activeIntervention->package->nutrition_guidance }}</flux:text>
                    @endif
                @else
                    <flux:text>{{ __('No intervention package is assigned to you.') }}</flux:text>
                @endif
                <flux:text size="xs" class="mt-3">{{ __('Recommendations are informational and subject to Health Service approval.') }}</flux:text>
            </x-panel>

            <x-panel :heading="__('Recent notifications')" :padding="false">
                <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                    @forelse ($notifications as $notification)
                        <div class="px-4 py-3 text-sm">
                            <div @class(['font-medium', 'text-navy-800 dark:text-navy-200' => ! $notification->read_at])>{{ $notification->data['title'] ?? '' }}</div>
                            <div class="text-xs text-zinc-500">{{ $notification->created_at->diffForHumans() }}</div>
                        </div>
                    @empty
                        <x-empty-state icon="bell" :heading="__('No notifications')" class="py-6" />
                    @endforelse
                </div>
                <div class="border-t border-zinc-100 p-3 text-end dark:border-zinc-800"><flux:link :href="route('notifications.index')" wire:navigate class="text-sm">{{ __('All notifications') }}</flux:link></div>
            </x-panel>
        </div>
    </div>
</div>
