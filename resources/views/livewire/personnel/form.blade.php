<div class="max-w-4xl">
    <x-page-header :title="$personnel ? __('Edit personnel') : __('Add personnel')" :subtitle="$personnel ? $personnel->rank_name.' · '.$personnel->employee_number : __('Register a new PNP personnel member. The employee number must be unique.')">
        <x-slot:actions>
            <flux:button size="sm" variant="ghost" :href="$personnel ? route('personnel.show', $personnel) : route('personnel.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
        </x-slot:actions>
    </x-page-header>

    <form wire:submit="save" class="space-y-6">
        @if ($possibleDuplicates)
            <flux:callout variant="warning" icon="exclamation-triangle" :heading="__('Possible duplicate personnel')">
                <flux:callout.text>
                    {{ __('A record with the same name and date of birth already exists:') }}
                    <ul class="mt-1 list-disc ps-5">
                        @foreach ($possibleDuplicates as $duplicate)
                            <li><a class="underline" href="{{ route('personnel.show', $duplicate['id']) }}" target="_blank">{{ $duplicate['name'] }}</a> {{ $duplicate['unit'] ? '— '.$duplicate['unit'] : '' }}</li>
                        @endforeach
                    </ul>
                </flux:callout.text>
                <x-slot:actions>
                    <flux:checkbox wire:model="confirmDuplicate" :label="__('This is a different person — save anyway')" />
                </x-slot:actions>
            </flux:callout>
        @endif

        <x-panel :heading="__('Personal information')">
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                <flux:input wire:model="employee_number" :label="__('Personnel ID / Employee No.')" required />
                <flux:select wire:model.live="rank_id" :label="__('Rank')" required>
                    <flux:select.option value="">{{ __('Select rank') }}</flux:select.option>
                    @foreach ($this->rankOptions as $rank)
                        <flux:select.option :value="$rank->id">{{ $rank->abbreviation }} — {{ $rank->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:select wire:model="status" :label="__('Status')" required>
                    @foreach ($statuses as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>

                <flux:input wire:model="last_name" :label="__('Last name')" required />
                <flux:input wire:model="first_name" :label="__('First name')" required />
                <flux:input wire:model="middle_name" :label="__('Middle name')" />
                <flux:input wire:model="suffix" :label="__('Suffix')" placeholder="Jr, III" />
                <flux:input wire:model="qualifier" :label="__('Q (qualifier, if applicable)')" />
                <flux:select wire:model="sex" :label="__('Sex')" required>
                    <flux:select.option value="">{{ __('Select') }}</flux:select.option>
                    @foreach ($sexes as $value => $label)
                        <flux:select.option :value="$value">{{ $label }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input type="date" wire:model="date_of_birth" :label="__('Date of birth')" required :max="now()->subYears(17)->format('Y-m-d')" />
            </div>
        </x-panel>

        <x-panel :heading="__('Assignment and contact')">
            <div class="grid gap-4 sm:grid-cols-2">
                <flux:select wire:model.live="unit_id" :label="__('Unit / Office')" required>
                    <flux:select.option value="">{{ __('Select unit') }}</flux:select.option>
                    @foreach ($this->unitOptions as $unit)
                        <flux:select.option :value="$unit->id">{{ $unit->code }} — {{ $unit->name }}</flux:select.option>
                    @endforeach
                </flux:select>
                <flux:input wire:model.live.blur="position" :label="__('Position / Designation')" />
                <flux:input wire:model="contact_number" :label="__('Contact number')" />
                <flux:input type="email" wire:model="email" :label="__('Email (contact)')" />
                <div class="sm:col-span-2">
                    <flux:textarea wire:model="remarks" :label="__('Remarks')" rows="2" />
                </div>
            </div>

            @if ($this->assignmentChanged())
                <flux:callout class="mt-4" icon="information-circle" :heading="__('Transfer / rank change detected')">
                    <flux:callout.text>{{ __('A new assignment history entry will be created. Historical BMI records keep their original unit and rank.') }}</flux:callout.text>
                </flux:callout>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <flux:input type="date" wire:model="assignment_effective_from" :label="__('Effective date')" required />
                    <flux:input wire:model="assignment_remarks" :label="__('Order / remarks')" placeholder="{{ __('e.g. Special Order No.') }}" />
                </div>
            @endif
        </x-panel>

        <x-panel :heading="__('Login account')" :subheading="__('Personnel accounts can only view their own BMI records.')">
            @if ($personnel?->user)
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <div class="font-medium">{{ $personnel->user->email }}</div>
                        <flux:text size="sm">{{ __('Last login') }}: {{ $personnel->user->last_login_at?->diffForHumans() ?? __('Never') }}</flux:text>
                    </div>
                    <flux:switch wire:model="account_active" :label="__('Account active')" />
                </div>
            @else
                <flux:switch wire:model.live="create_account" :label="__('Create a login account for this personnel')" />
                @if ($create_account)
                    <div class="mt-4 grid gap-4 sm:grid-cols-2">
                        <flux:input type="email" wire:model="account_email" :label="__('Login email')" required />
                        <flux:input type="password" wire:model="account_password" :label="__('Temporary password')" required viewable />
                    </div>
                    <flux:text size="sm" class="mt-2">{{ __('Ask the personnel to change the password and enable two-factor authentication after first sign-in.') }}</flux:text>
                @endif
            @endif
        </x-panel>

        <div class="flex justify-end gap-2">
            <flux:button variant="ghost" :href="$personnel ? route('personnel.show', $personnel) : route('personnel.index')" wire:navigate>{{ __('Cancel') }}</flux:button>
            <flux:button type="submit" variant="primary">{{ __('Save personnel') }}</flux:button>
        </div>
    </form>
</div>
