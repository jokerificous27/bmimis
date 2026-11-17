<div class="max-w-4xl">
    <x-page-header :title="__('Users Upload')" :subtitle="__('Create many login accounts at once. Export the personnel of a unit as an Excel file, complete it, then upload it. Nothing is saved until you confirm.')">
        <x-slot:actions>
            <flux:button size="sm" variant="ghost" :href="route('admin.users')" wire:navigate>{{ __('Back to User Management') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :heading="__('1. Export the unit\'s personnel (Excel)')" :subheading="__('The file lists the active personnel of the unit who have no login account yet, already in the upload format.')">
        <div class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1">
                <flux:select wire:model.live="unit_id" :label="__('Unit / Office')">
                    <flux:select.option value="">{{ __('Select unit') }}</flux:select.option>
                    @foreach ($allUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ $unit->code }} — {{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
            </div>
            <flux:button icon="arrow-down-tray" wire:click="export" :disabled="$unit_id === ''">{{ __('Export to Excel') }}</flux:button>
        </div>
        @if ($this->unit)
            <flux:text size="sm" class="mt-2">{{ trans_choice(':count personnel of :unit has no login account yet.|:count personnel of :unit have no login account yet.', $this->pendingCount, ['count' => $this->pendingCount, 'unit' => $this->unit->code]) }}</flux:text>
        @endif

        <div class="mt-4 text-sm">
            <div class="font-medium">{{ __('Columns (first row = headers):') }}</div>
            <div class="mt-1 flex flex-wrap gap-1">
                @foreach ($columns as $column)
                    <code class="rounded bg-zinc-100 px-1.5 py-0.5 text-xs dark:bg-zinc-800">{{ $column }}</code>
                @endforeach
            </div>
            <ul class="mt-2 list-disc space-y-1 ps-5 text-zinc-600 dark:text-zinc-400">
                <li>{{ __('Fill in the email of each personnel (it is what they sign in with). Emails already on the personnel record are filled in for you.') }}</li>
                <li>{{ __('Leave the password blank and the system generates a temporary password per account; you download the list once after the upload.') }}</li>
                <li>{{ __('Role is "personnel" unless you change it (unit_representative, health_service_encoder, health_service_admin). Delete the rows of personnel who should not get an account.') }}</li>
            </ul>
        </div>
    </x-panel>

    <x-panel class="mt-6" :heading="__('2. Upload and validate')">
        <form wire:submit="analyze" class="flex flex-col gap-3 sm:flex-row sm:items-end">
            <div class="flex-1"><flux:input type="file" wire:model="file" :label="__('Completed Excel file')" accept=".xlsx,.csv" /></div>
            <flux:button type="submit" variant="primary" icon="magnifying-glass">{{ __('Validate file') }}</flux:button>
        </form>
        <div wire:loading wire:target="file,analyze" class="mt-2 text-sm text-zinc-500">{{ __('Processing…') }}</div>
    </x-panel>

    @if ($result)
        <x-panel class="mt-6" :heading="__('3. Review and create accounts')" :subheading="$filename">
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

            @php($generated = collect($result['valid'])->whereNull('password')->count())
            @if ($generated)
                <flux:callout class="mt-4" icon="key" variant="secondary">
                    <flux:callout.text>{{ trans_choice(':count account has no password in the file. A temporary password will be generated and downloaded as an Excel file right after the accounts are created. Keep that file safe: it cannot be downloaded again.|:count accounts have no password in the file. Temporary passwords will be generated and downloaded as an Excel file right after the accounts are created. Keep that file safe: it cannot be downloaded again.', $generated, ['count' => $generated]) }}</flux:callout.text>
                </flux:callout>
            @endif

            <div class="mt-4 flex flex-wrap items-center justify-end gap-3">
                @if ($result['errors'])
                    <flux:text size="sm">{{ __('Rows with errors will be skipped. Fix them in the file and upload again.') }}</flux:text>
                @endif
                <flux:button variant="primary" wire:click="commit" :disabled="! count($result['valid'])" wire:confirm="{{ __('Create the accounts for the valid rows now?') }}">{{ __('Create :count accounts', ['count' => count($result['valid'])]) }}</flux:button>
            </div>
            <div wire:loading wire:target="commit" class="mt-2 text-end text-sm text-zinc-500">{{ __('Creating accounts…') }}</div>
        </x-panel>
    @endif
</div>
