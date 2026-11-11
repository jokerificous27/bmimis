<div>
    @php($reportsSubtitle = __('Generate printable A4 reports and Excel exports from live records. To export a PDF, open the print preview and choose Save as PDF.'))
    <x-page-header :title="$categories[$category]" :subtitle="$reportsSubtitle" />

    @if (session('report_error'))
        <flux:callout variant="warning" icon="exclamation-triangle" class="mb-6" :heading="session('report_error')" />
    @endif

    <nav class="mb-6 flex gap-1 overflow-x-auto border-b border-zinc-200 dark:border-zinc-700">
        @foreach ($categories as $key => $label)
            <button type="button" wire:click="$set('category', '{{ $key }}')" @class([
                'whitespace-nowrap border-b-2 px-3 py-2.5 text-sm font-medium',
                'border-navy-800 text-navy-800 dark:border-navy-200 dark:text-navy-200' => $category === $key,
                'border-transparent text-zinc-500 hover:text-zinc-800 dark:hover:text-zinc-200' => $category !== $key,
            ])>{{ $label }}</button>
        @endforeach
    </nav>

    <div class="grid gap-6 lg:grid-cols-5">
        <div class="space-y-3 lg:col-span-2">
            @foreach ($reports as $key => $item)
                <button type="button" wire:click="$set('report', '{{ $key }}')" @class([
                    'block w-full rounded-xl border p-4 text-start transition',
                    'border-navy-800 bg-navy-50 ring-1 ring-navy-800 dark:bg-navy-900/30' => $report === $key,
                    'border-zinc-200 bg-white hover:border-navy-200 dark:border-zinc-700 dark:bg-zinc-900' => $report !== $key,
                ])>
                    <div class="flex items-center gap-2">
                        <flux:icon.document-text class="size-5 text-navy-700 dark:text-navy-200" />
                        <span class="font-medium">{{ __($item['title']) }}</span>
                    </div>
                    <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ __($item['description']) }}</p>
                    <div class="mt-2 text-xs text-zinc-400">A4 {{ $item['orientation'] }}</div>
                </button>
            @endforeach
        </div>

        <x-panel class="lg:col-span-3" :heading="__($definition['title'])" :subheading="__('Report options')">
            <div class="space-y-4">
                @if (in_array('personnel_id', $definition['filters'], true))
                    @if ($this->selectedPersonnel)
                        <div class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                            <span class="font-medium">{{ $this->selectedPersonnel->rank_name }} · {{ $this->selectedPersonnel->employee_number }}</span>
                            <flux:button size="xs" variant="ghost" wire:click="$set('personnelId', null)">{{ __('Change') }}</flux:button>
                        </div>
                    @else
                        <div class="relative">
                            <flux:input wire:model.live.debounce.250ms="personnelSearch" :label="__('Personnel')" icon="magnifying-glass" :placeholder="__('Search name or employee no.')" autocomplete="off" />
                            @if ($this->personnelResults->isNotEmpty())
                                <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    @foreach ($this->personnelResults as $result)
                                        <button type="button" wire:click="selectPersonnel({{ $result->id }})" class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">{{ $result->rank?->abbreviation }} {{ $result->formal_name }} <span class="text-zinc-500">· {{ $result->employee_number }}</span></button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                @endif

                <div class="grid gap-4 sm:grid-cols-2">
                    @if (in_array('year', $definition['filters'], true))
                        <flux:select wire:model.live="year" :label="__('Year')">
                            @foreach ($this->yearOptions() as $option)
                                <flux:select.option :value="$option">{{ $option }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('month', $definition['filters'], true))
                        <flux:select wire:model.live="month" :label="__('Month')">
                            @foreach ($this->monthOptions() as $number => $name)
                                <flux:select.option :value="$number">{{ $name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('unit_id', $definition['filters'], true))
                        <flux:select wire:model.live="unitId" :label="__('Unit / Office')">
                            <flux:select.option value="">{{ __('All authorized units') }}</flux:select.option>
                            @foreach ($this->unitOptions as $unit)
                                <flux:select.option :value="$unit->id">{{ $unit->code }} — {{ $unit->name }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                    @if (in_array('category', $definition['filters'], true))
                        <flux:select wire:model.live="classification" :label="__('BMI classification')">
                            <flux:select.option value="">{{ __('All') }}</flux:select.option>
                            @foreach ($classifications as $value => $label)
                                <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                            @endforeach
                        </flux:select>
                    @endif
                </div>

                @if ($missingRequirement)
                    <flux:callout icon="information-circle" :heading="__('Select a personnel to generate this report.')" />
                @endif

                <div class="flex flex-wrap gap-2 border-t border-zinc-100 pt-4 dark:border-zinc-800">
                    @if ($printUrl)
                        <flux:button variant="primary" icon="printer" :href="$printUrl" target="_blank">{{ __('Print preview') }}</flux:button>
                    @else
                        <flux:button variant="primary" icon="printer" disabled>{{ __('Print preview') }}</flux:button>
                    @endif
                    @if ($exportUrl)
                        <flux:button icon="arrow-down-tray" :href="$exportUrl">{{ __('Export Excel') }}</flux:button>
                    @endif
                </div>
                <flux:text size="xs">{{ __('Report generation and exports are recorded in the audit trail. Only records you are authorized to view are included.') }}</flux:text>
            </div>
        </x-panel>
    </div>
</div>
