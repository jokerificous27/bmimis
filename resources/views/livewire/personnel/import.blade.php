<div class="max-w-4xl">
    <x-page-header :title="__('Import Data')" :subtitle="__('Bulk import of personnel and historical monthly weights from Excel (.xlsx) or CSV. Rows are validated first; nothing is saved until you confirm.')">
        <x-slot:actions>
            <flux:button size="sm" variant="ghost" :href="route('personnel.index')" wire:navigate>{{ __('Back to directory') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :heading="__('1. Choose what to import')">
        <flux:radio.group wire:model.live="type" variant="segmented">
            <flux:radio value="personnel" :label="__('Personnel masterlist')" />
            <flux:radio value="monthly" :label="__('Historical monthly weights')" />
        </flux:radio.group>
        <div class="mt-4 text-sm">
            <div class="font-medium">{{ __('Required columns (first row = headers):') }}</div>
            <div class="mt-1 flex flex-wrap gap-1">
                @foreach ($columns as $column)
                    <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-800">{{ $column }}</code>
                @endforeach
            </div>
            <flux:text size="sm" class="mt-2">
                @if ($type === 'monthly')
                    {{ __('Weight in kilograms. Month may be 1–12 or a month name. BMI is computed from each personnel\'s applicable assessment height. Existing months are never overwritten.') }}
                @else
                    {{ __('Rank uses the abbreviation (e.g. PCpl) and unit uses the unit code (e.g. ITMS). Sex is M or F. Dates as YYYY-MM-DD. Existing employee numbers are rejected to prevent duplicates.') }}
                @endif
            </flux:text>
            <flux:button size="sm" icon="arrow-down-tray" class="mt-3" wire:click="downloadTemplate">{{ __('Download template') }}</flux:button>
        </div>
    </x-panel>

    <x-panel class="mt-6" :heading="__('2. Upload and validate')">
        <form wire:submit="analyze" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><flux:input type="file" wire:model="file" :label="__('Spreadsheet file')" accept=".xlsx,.csv" /></div>
            <flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('Validate file') }}</flux:button>
        </form>
        <div wire:loading wire:target="file,analyze" class="mt-2 text-sm text-zinc-500">{{ __('Processing…') }}</div>
    </x-panel>

    @if ($result)
        <x-panel class="mt-6" :heading="__('3. Review and import')" :subheading="$filename">
            <div class="grid gap-4 sm:grid-cols-3">
                <x-stat-card :label="__('Rows in file')" :value="$result['total']" icon="document-text" tone="zinc" />
                <x-stat-card :label="__('Valid rows')" :value="count($result['valid'])" icon="check-badge" tone="green" />
                <x-stat-card :label="__('Rows with errors')" :value="count($result['errors'])" icon="exclamation-triangle" :tone="count($result['errors']) ? 'red' : 'green'" />
            </div>

            @if ($result['errors'])
                <div class="mt-4 max-h-80 overflow-y-auto rounded-lg border border-red-200 dark:border-red-900">
                    <table class="w-full text-sm">
                        <thead class="bg-red-50 dark:bg-red-950/40"><tr><th class="px-3 py-2 text-start">{{ __('Row') }}</th><th class="px-3 py-2 text-start">{{ __('Problems') }}</th></tr></thead>
                        <tbody class="divide-y divide-red-100 dark:divide-red-900">
                            @foreach ($result['errors'] as $line => $messages)
                                <tr><td class="px-3 py-2 align-top font-mono">{{ $line }}</td><td class="px-3 py-2"><ul class="list-disc ps-4">@foreach ($messages as $message)<li>{{ $message }}</li>@endforeach</ul></td></tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                @if ($result['errors'])
                    <flux:text size="sm">{{ __('Rows with errors will be skipped. Fix them in the file and import again.') }}</flux:text>
                @endif
                <flux:button variant="primary" wire:click="commit" :disabled="! count($result['valid'])" wire:confirm="{{ __('Import the valid rows now?') }}">{{ __('Import :count valid rows', ['count' => count($result['valid'])]) }}</flux:button>
            </div>
        </x-panel>
    @endif
</div>
