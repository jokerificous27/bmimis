<div>
    <x-page-header :title="__('BMI Standards')" :subtitle="__('Configure WHO and PNP BMI classification thresholds with effective dates. The lower bound is inclusive and the upper bound exclusive.')" />

    <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6" :heading="__('Official PNP thresholds are not built in')">
        <flux:callout.text>{{ __('Encode the PNP Acceptable BMI values exactly as stated in the applicable PNP Health Service policy, including its reference (memorandum / circular number). A standard marked "provisional" shows a warning on the dashboard.') }}</flux:callout.text>
    </flux:callout>

    <div class="grid gap-6 xl:grid-cols-4">
        <div class="space-y-6">
            @foreach (['pnp' => __('PNP Acceptable BMI'), 'who' => __('WHO Standard')] as $type => $label)
                <x-panel :heading="$label" :padding="false">
                    <x-slot:actions>
                        <flux:button size="xs" icon="plus" wire:click="newVersion('{{ $type }}')">{{ __('New version') }}</flux:button>
                    </x-slot:actions>
                    <div class="divide-y divide-zinc-100 dark:divide-zinc-800">
                        @foreach ($standards[$type] ?? [] as $standard)
                            <button type="button" wire:click="select({{ $standard->id }})" @class(['block w-full px-4 py-3 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-800', 'bg-navy-50 dark:bg-navy-900/30' => $standardId === $standard->id])>
                                <div class="font-medium">{{ $standard->name }}</div>
                                <div class="mt-0.5 flex flex-wrap items-center gap-1 text-xs text-zinc-500">
                                    {{ __('From :date', ['date' => $standard->effective_from->format('M j, Y')]) }}
                                    @if ($current[$type] === $standard->id)<flux:badge size="sm" color="green">{{ __('In force') }}</flux:badge>@endif
                                    @if (! $standard->is_active)<flux:badge size="sm" color="zinc">{{ __('Inactive') }}</flux:badge>@endif
                                    @if ($standard->is_provisional)<flux:badge size="sm" color="amber">{{ __('Provisional') }}</flux:badge>@endif
                                </div>
                            </button>
                        @endforeach
                    </div>
                </x-panel>
            @endforeach
        </div>

        @if ($selected)
            <form wire:submit="save" class="space-y-6 xl:col-span-3">
                <x-panel :heading="$selected->type->label()">
                    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                        <div class="sm:col-span-2"><flux:input wire:model="name" :label="__('Name')" required /></div>
                        <div class="sm:col-span-2"><flux:input wire:model="reference" :label="__('Policy reference')" :placeholder="__('e.g. PNP HS Memorandum Circular No.')" /></div>
                        <flux:input type="date" wire:model="effective_from" :label="__('Effective from')" required />
                        <flux:input type="date" wire:model="effective_to" :label="__('Effective until')" />
                        <div class="flex items-end"><flux:switch wire:model="is_active" :label="__('Active')" /></div>
                        <div class="flex items-end"><flux:switch wire:model="is_provisional" :label="__('Provisional')" /></div>
                        <div class="sm:col-span-2 lg:col-span-4"><flux:textarea wire:model="description" :label="__('Description')" rows="2" /></div>
                    </div>
                </x-panel>

                <x-panel :heading="__('Classification bands')" :subheading="__('Leave a BMI bound empty for open-ended bands. Age and sex limits are optional, for age- or sex-specific official thresholds.')" :padding="false">
                    <x-slot:actions>
                        <flux:button size="sm" icon="plus" wire:click="addBand">{{ __('Add band') }}</flux:button>
                    </x-slot:actions>
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[64rem] text-sm">
                            <thead class="bg-zinc-50 text-xs text-zinc-500 dark:bg-zinc-800">
                                <tr>
                                    <th class="px-2 py-2 text-start">{{ __('Label') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('Category') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('BMI from (≥)') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('BMI to (<)') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('Age') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('Sex') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('Intervention') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('Default package') }}</th>
                                    <th class="px-2 py-2 text-start">{{ __('Color') }}</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($bands as $index => $band)
                                    <tr class="border-t border-zinc-100 align-top dark:border-zinc-800" wire:key="band-{{ $index }}-{{ $band['id'] ?? 'new' }}">
                                        <td class="px-2 py-2"><flux:input size="sm" wire:model="bands.{{ $index }}.label" /><flux:error name="bands.{{ $index }}.label" /></td>
                                        <td class="px-2 py-2">
                                            <flux:select size="sm" wire:model="bands.{{ $index }}.category">
                                                @foreach ($categories as $value => $label)
                                                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </td>
                                        <td class="w-24 px-2 py-2"><flux:input size="sm" type="number" step="0.01" wire:model="bands.{{ $index }}.min_bmi" /><flux:error name="bands.{{ $index }}.min_bmi" /></td>
                                        <td class="w-24 px-2 py-2"><flux:input size="sm" type="number" step="0.01" wire:model="bands.{{ $index }}.max_bmi" /><flux:error name="bands.{{ $index }}.max_bmi" /></td>
                                        <td class="w-36 px-2 py-2">
                                            <div class="flex gap-1">
                                                <flux:input size="sm" type="number" wire:model="bands.{{ $index }}.min_age" :placeholder="__('min')" />
                                                <flux:input size="sm" type="number" wire:model="bands.{{ $index }}.max_age" :placeholder="__('max')" />
                                            </div>
                                            <flux:error name="bands.{{ $index }}.max_age" />
                                        </td>
                                        <td class="w-28 px-2 py-2">
                                            <flux:select size="sm" wire:model="bands.{{ $index }}.sex">
                                                <flux:select.option value="">{{ __('Any') }}</flux:select.option>
                                                @foreach ($sexes as $value => $label)
                                                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </td>
                                        <td class="px-2 py-2"><flux:checkbox wire:model="bands.{{ $index }}.requires_intervention" :label="__('Required')" /></td>
                                        <td class="w-44 px-2 py-2">
                                            <flux:select size="sm" wire:model="bands.{{ $index }}.intervention_package_id">
                                                <flux:select.option value="">{{ __('None') }}</flux:select.option>
                                                @foreach ($packages as $package)
                                                    <flux:select.option :value="$package->id">{{ $package->displayName() }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </td>
                                        <td class="w-28 px-2 py-2">
                                            <flux:select size="sm" wire:model="bands.{{ $index }}.color">
                                                @foreach ($colors as $color)
                                                    <flux:select.option :value="$color">{{ ucfirst($color) }}</flux:select.option>
                                                @endforeach
                                            </flux:select>
                                        </td>
                                        <td class="px-2 py-2"><flux:button size="xs" variant="ghost" icon="trash" wire:click="removeBand({{ $index }})" /></td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="p-4"><flux:error name="bands" /></div>
                </x-panel>

                <div class="flex justify-end"><flux:button type="submit" variant="primary">{{ __('Save standard') }}</flux:button></div>
            </form>
        @endif
    </div>
</div>
