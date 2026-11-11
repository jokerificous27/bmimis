<div>
    <x-page-header :title="__('Roles & Permissions')" :subtitle="__('Configure what each role may do. Data scope still applies: Unit Representatives only see their unit, and personnel only see their own record.')">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" wire:click="save">{{ __('Save permissions') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <flux:callout icon="information-circle" class="mb-6">
        <flux:callout.text>{{ __('The Super Administrator role always has full access and cannot be restricted. Hiding a menu item never replaces authorization: every permission is checked on the server.') }}</flux:callout.text>
    </flux:callout>

    <x-panel :padding="false">
        <div class="overflow-x-auto">
            <table class="w-full min-w-[46rem] text-sm">
                <thead class="bg-zinc-50 dark:bg-zinc-800">
                    <tr>
                        <th class="px-4 py-3 text-start font-medium">{{ __('Permission') }}</th>
                        @foreach ($roles as $role)
                            <th class="px-3 py-3 text-center font-medium">
                                <div>{{ $role->label() }}</div>
                                <button type="button" class="text-xs font-normal text-navy-700 underline dark:text-navy-200" wire:click="restoreDefaults('{{ $role->value }}')">{{ __('defaults') }}</button>
                            </th>
                        @endforeach
                    </tr>
                </thead>
                <tbody>
                    @foreach ($groups as $group => $permissions)
                        <tr class="bg-zinc-50/60 dark:bg-zinc-800/40"><td colspan="{{ count($roles) + 1 }}" class="px-4 py-2 text-xs font-semibold uppercase tracking-wide text-zinc-500">{{ $group }}</td></tr>
                        @foreach ($permissions as $permission)
                            <tr class="border-t border-zinc-100 dark:border-zinc-800">
                                <td class="px-4 py-2.5">
                                    <div>{{ $permission->label() }}</div>
                                    <div class="font-mono text-xs text-zinc-400">{{ $permission->value }}</div>
                                </td>
                                @foreach ($roles as $role)
                                    <td class="px-3 py-2.5 text-center">
                                        <input type="checkbox" class="size-4 rounded border-zinc-300 accent-navy-800" value="{{ $permission->value }}" wire:model="matrix.{{ $role->value }}" aria-label="{{ $role->label() }}: {{ $permission->label() }}">
                                    </td>
                                @endforeach
                            </tr>
                        @endforeach
                    @endforeach
                </tbody>
            </table>
        </div>
    </x-panel>
</div>
