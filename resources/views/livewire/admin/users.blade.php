<div>
    <x-page-header :title="__('User Management')" :subtitle="__('Login accounts, roles and unit assignments. Permissions per role are configured in Roles & Permissions.')">
        <x-slot:actions>
            <flux:button size="sm" variant="primary" icon="plus" wire:click="create">{{ __('Add user') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <x-panel :padding="false">
        <div class="grid gap-3 border-b border-zinc-100 p-4 sm:grid-cols-3 dark:border-zinc-800">
            <div class="sm:col-span-2"><flux:input wire:model.live.debounce.300ms="search" icon="magnifying-glass" :placeholder="__('Search name or email')" clearable /></div>
            <flux:select wire:model.live="roleFilter">
                <flux:select.option value="">{{ __('All roles') }}</flux:select.option>
                @foreach ($roles as $value => $label)
                    <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                @endforeach
            </flux:select>
        </div>
        <div class="overflow-x-auto px-4">
            <flux:table :paginate="$users">
                <flux:table.columns>
                    <flux:table.column>{{ __('Name') }}</flux:table.column>
                    <flux:table.column>{{ __('Email') }}</flux:table.column>
                    <flux:table.column>{{ __('Role') }}</flux:table.column>
                    <flux:table.column>{{ __('Unit') }}</flux:table.column>
                    <flux:table.column>{{ __('Personnel') }}</flux:table.column>
                    <flux:table.column>{{ __('Last login') }}</flux:table.column>
                    <flux:table.column>{{ __('Status') }}</flux:table.column>
                    <flux:table.column></flux:table.column>
                </flux:table.columns>
                <flux:table.rows>
                    @foreach ($users as $account)
                        <flux:table.row :key="$account->id">
                            <flux:table.cell><div class="font-medium">{{ $account->name }}</div><div class="text-xs text-zinc-500">{{ $account->designation }}</div></flux:table.cell>
                            <flux:table.cell>{{ $account->email }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$account->role->color()">{{ $account->role->label() }}</flux:badge></flux:table.cell>
                            <flux:table.cell>{{ $account->unit?->code ?? '—' }}</flux:table.cell>
                            <flux:table.cell>
                                @if ($account->personnel)
                                    <a href="{{ route('personnel.show', $account->personnel) }}" wire:navigate class="text-navy-800 hover:underline dark:text-navy-200">{{ $account->personnel->employee_number }}</a>
                                @else
                                    —
                                @endif
                            </flux:table.cell>
                            <flux:table.cell>{{ $account->last_login_at?->diffForHumans() ?? __('Never') }}</flux:table.cell>
                            <flux:table.cell><flux:badge size="sm" :color="$account->is_active ? 'green' : 'zinc'">{{ $account->is_active ? __('Active') : __('Inactive') }}</flux:badge></flux:table.cell>
                            <flux:table.cell align="end">
                                <flux:button size="xs" variant="ghost" icon="pencil-square" wire:click="edit({{ $account->id }})" />
                                @if ($account->id !== auth()->id())
                                    <flux:button size="xs" variant="ghost" wire:click="toggleActive({{ $account->id }})" wire:confirm="{{ $account->is_active ? __('Deactivate this account? The user will be signed out.') : __('Activate this account?') }}">{{ $account->is_active ? __('Deactivate') : __('Activate') }}</flux:button>
                                @endif
                            </flux:table.cell>
                        </flux:table.row>
                    @endforeach
                </flux:table.rows>
            </flux:table>
        </div>
    </x-panel>
    <flux:error name="role" class="mt-2" />

    <flux:modal name="user-form" class="md:w-[34rem]">
        <form wire:submit="save" class="space-y-5">
            <flux:heading size="lg">{{ $editingId ? __('Edit user') : __('Add user') }}</flux:heading>
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:input wire:model="name" :label="__('Full name')" required />
                <flux:input wire:model="designation" :label="__('Designation')" :placeholder="__('e.g. Admin PCO, RHSU 2')" />
                <div class="sm:col-span-2"><flux:input type="email" wire:model="email" :label="__('Email')" required /></div>
                <flux:select wire:model.live="role" :label="__('Role')" required>
                    @foreach ($roles as $value => $label)
                        @if ($value !== 'super_admin' || auth()->user()->isSuperAdmin())
                            <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                        @endif
                    @endforeach
                </flux:select>
                <flux:select wire:model="unit_id" :label="__('Assigned unit')" :description="__('Required for Unit Representatives')">
                    <flux:select.option value="">{{ __('None') }}</flux:select.option>
                    @foreach ($allUnits as $unit)
                        <flux:select.option :value="$unit->id">{{ $unit->code }} — {{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <div class="sm:col-span-2"><flux:input type="password" wire:model="password" :label="$editingId ? __('New password (leave blank to keep)') : __('Password')" viewable /></div>
                <div class="sm:col-span-2">
                    @if ($this->linkedPersonnel)
                        <div class="flex items-center justify-between rounded-lg bg-zinc-50 p-3 text-sm dark:bg-zinc-800">
                            <span>{{ __('Linked personnel') }}: <strong>{{ $this->linkedPersonnel->rank_name }}</strong> ({{ $this->linkedPersonnel->employee_number }})</span>
                            <flux:button size="xs" variant="ghost" wire:click="$set('personnelId', null)">{{ __('Unlink') }}</flux:button>
                        </div>
                    @else
                        <div class="relative">
                            <flux:input wire:model.live.debounce.250ms="personnelSearch" :label="__('Link to personnel profile (optional)')" icon="magnifying-glass" autocomplete="off" />
                            @if ($this->personnelResults->isNotEmpty())
                                <div class="absolute z-20 mt-1 w-full overflow-hidden rounded-lg border border-zinc-200 bg-white shadow-lg dark:border-zinc-700 dark:bg-zinc-800">
                                    @foreach ($this->personnelResults as $result)
                                        <button type="button" wire:click="linkPersonnel({{ $result->id }})" class="block w-full px-3 py-2 text-start text-sm hover:bg-zinc-50 dark:hover:bg-zinc-700">{{ $result->rank?->abbreviation }} {{ $result->formal_name }} · {{ $result->employee_number }}</button>
                                    @endforeach
                                </div>
                            @endif
                        </div>
                    @endif
                    <flux:error name="personnelId" />
                </div>
                <div class="sm:col-span-2"><flux:switch wire:model="is_active" :label="__('Account active')" /></div>
            </div>
            <flux:error name="role" />
            <div class="flex justify-end gap-2">
                <flux:modal.close><flux:button variant="ghost">{{ __('Cancel') }}</flux:button></flux:modal.close>
                <flux:button type="submit" variant="primary">{{ __('Save') }}</flux:button>
            </div>
        </form>
    </flux:modal>
</div>
