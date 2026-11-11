<div class="overflow-x-auto px-4">
    <flux:table>
        <flux:table.columns>
            <flux:table.column>{{ __('Month') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Weight (kg)') }}</flux:table.column>
            <flux:table.column align="end">{{ __('Change') }}</flux:table.column>
            <flux:table.column align="end">{{ __('BMI') }}</flux:table.column>
            <flux:table.column>{{ __('PNP') }}</flux:table.column>
            <flux:table.column>{{ __('Measured on') }}</flux:table.column>
            <flux:table.column>{{ __('Recorded by') }}</flux:table.column>
            <flux:table.column>{{ __('Status') }}</flux:table.column>
            @if ($editable)
                <flux:table.column></flux:table.column>
            @endif
        </flux:table.columns>
        <flux:table.rows>
            @foreach ($sheet as $row)
                <flux:table.row :key="'month-'.$row['month']">
                    <flux:table.cell class="font-medium">{{ $row['name'] }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $row['record'] ? number_format($row['record']->weight_kg, 2) : '—' }}</flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums"><x-weight-trend :difference="$row['difference']" :trend="$row['trend']" class="justify-end" /></flux:table.cell>
                    <flux:table.cell align="end" class="tabular-nums">{{ $row['record']?->bmi !== null ? number_format($row['record']->bmi, 1) : '—' }}</flux:table.cell>
                    <flux:table.cell><x-bmi-badge :label="$row['record']?->pnp_classification" :category="$row['record']?->pnp_category" /></flux:table.cell>
                    <flux:table.cell>
                        {{ $row['record']?->measured_on?->format('M j, Y') ?? '—' }}
                        @if ($row['record']?->photo_path)
                            <a href="{{ route('monitoring.photo', $row['record']) }}" target="_blank" class="ms-1 inline-flex align-middle text-navy-700 dark:text-navy-200" title="{{ __('Weigh-in photo') }}"><flux:icon.camera variant="micro" /></a>
                        @endif
                    </flux:table.cell>
                    <flux:table.cell>{{ $row['record']?->recorder?->name ?? '—' }}</flux:table.cell>
                    <flux:table.cell>
                        <x-month-status :status="$row['status']" />
                        @if ($row['record']?->measurement_review)
                            <flux:badge size="sm" :color="$row['record']->measurement_review->color()" class="ms-1">{{ __($row['record']->measurement_review->label()) }}</flux:badge>
                        @endif
                    </flux:table.cell>
                    @if ($editable)
                        <flux:table.cell align="end">
                            @if ($row['record'])
                                @can('update', $row['record'])
                                    <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="openCorrection({{ $row['record']->id }})">{{ __('Correct') }}</flux:button>
                                @endcan
                            @elseif ($row['status'] !== 'upcoming' && $this->canEncode)
                                <flux:button size="xs" variant="primary" icon="plus" wire:click="openEntry({{ $row['month'] }})">{{ __('Record') }}</flux:button>
                            @endif
                        </flux:table.cell>
                    @endif
                </flux:table.row>
            @endforeach
        </flux:table.rows>
    </flux:table>
</div>
