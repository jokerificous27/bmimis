@php
    $user = auth()->user();
    $unreadNotifications = $user->unreadNotifications()->count();
    $isPersonnelOnly = $user->role === \App\Enums\Role::Personnel;
    $hasOwnProfile = $user->personnel()->exists();
@endphp
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="min-h-screen bg-zinc-50 dark:bg-zinc-800">
        <flux:sidebar sticky collapsible="mobile" class="border-e border-zinc-200 bg-white dark:border-zinc-700 dark:bg-zinc-900">
            <flux:sidebar.header>
                <x-app-logo :sidebar="true" href="{{ route('dashboard') }}" wire:navigate />
                <flux:sidebar.collapse class="lg:hidden" />
            </flux:sidebar.header>

            <flux:sidebar.nav>
                @if ($isPersonnelOnly)
                    <flux:sidebar.group :heading="__('My Health Record')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('my.dashboard')" :current="request()->routeIs('my.dashboard')" wire:navigate>{{ __('My Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('my.profile')" :current="request()->routeIs('my.profile')" wire:navigate>{{ __('My BMI Profile') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="scale" :href="route('my.monitoring')" :current="request()->routeIs('my.monitoring')" wire:navigate>{{ __('My Monthly Monitoring') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="qr-code" :href="route('my.qr')">{{ __('My Weigh-in QR') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('my.assessments')" :current="request()->routeIs('my.assessments*')" wire:navigate>{{ __('My Assessment History') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" :badge="$unreadNotifications ?: null" wire:navigate>{{ __('My Notifications') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="user-circle" :href="route('profile.edit')" :current="request()->routeIs('profile.edit', 'security.edit', 'appearance.edit')" wire:navigate>{{ __('My Account') }}</flux:sidebar.item>
                    </flux:sidebar.group>
                @else
                    <flux:sidebar.group :heading="__('General')" class="grid">
                        <flux:sidebar.item icon="home" :href="route('dashboard')" :current="request()->routeIs('dashboard', 'dashboard.health-service') || (request()->routeIs('dashboard.unit') && $user->hasUnitScope())" wire:navigate>{{ __('Dashboard') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="bell" :href="route('notifications.index')" :current="request()->routeIs('notifications.*')" :badge="$unreadNotifications ?: null" wire:navigate>{{ __('Notifications') }}</flux:sidebar.item>
                    </flux:sidebar.group>

                    <flux:sidebar.group :heading="__('Personnel')" class="grid">
                        <flux:sidebar.item icon="users" :href="route('personnel.index')" :current="request()->routeIs('personnel.*') && request('mode') !== 'profiles'" wire:navigate>{{ __('Personnel Directory') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="identification" :href="route('personnel.index', ['mode' => 'profiles'])" :current="request()->routeIs('personnel.index') && request('mode') === 'profiles'" wire:navigate>{{ __('BMI Profiles') }}</flux:sidebar.item>
                        <flux:sidebar.item icon="clipboard-document-list" :href="route('assessments.index')" :current="request()->routeIs('assessments.*')" wire:navigate>{{ __('BMI Assessments') }}</flux:sidebar.item>
                        @can('monitoring.encode')
                            <flux:sidebar.item icon="qr-code" :href="route('monitoring.station')" :current="request()->routeIs('monitoring.station')" wire:navigate>{{ __('Weigh-in Station') }}</flux:sidebar.item>
                        @endcan
                        @canany(['monitoring.encode', 'compliance.view'])
                            <flux:sidebar.item icon="scale" :href="route('monitoring.index')" :current="request()->routeIs('monitoring.*') && ! request()->routeIs('monitoring.station')" wire:navigate>{{ __('Monthly Weight Monitoring') }}</flux:sidebar.item>
                        @endcanany
                        <flux:sidebar.item icon="heart" :href="route('interventions.index')" :current="request()->routeIs('interventions.*')" wire:navigate>{{ __('Intervention Management') }}</flux:sidebar.item>
                    </flux:sidebar.group>

                    @canany(['compliance.view', 'analytics.view'])
                        <flux:sidebar.group :heading="__('Health Service')" class="grid">
                            @can('compliance.view')
                                <flux:sidebar.item icon="check-badge" :href="route('compliance.index')" :current="request()->routeIs('compliance.*')" wire:navigate>{{ __('Compliance Monitoring') }}</flux:sidebar.item>
                                <flux:sidebar.item icon="building-office-2" :href="route('dashboard.unit')" :current="request()->routeIs('dashboard.unit') && ! $user->hasUnitScope()" wire:navigate>{{ __('Unit Monitoring') }}</flux:sidebar.item>
                            @endcan
                            @can('analytics.view')
                                <flux:sidebar.item icon="chart-bar" :href="route('analytics.index')" :current="request()->routeIs('analytics.*')" wire:navigate>{{ __('BMI Analytics') }}</flux:sidebar.item>
                            @endcan
                        </flux:sidebar.group>
                    @endcanany

                    @can('reports.generate')
                        <flux:sidebar.group :heading="__('Reports')" class="grid">
                            @foreach (\App\Services\Reports\ReportCatalog::categories() as $categoryKey => $categoryLabel)
                                <flux:sidebar.item :icon="\App\Services\Reports\ReportCatalog::categoryIcon($categoryKey)" :href="route('reports.index', ['category' => $categoryKey])" :current="request()->routeIs('reports.*') && request('category', 'individual') === $categoryKey" wire:navigate>{{ $categoryLabel }}</flux:sidebar.item>
                            @endforeach
                        </flux:sidebar.group>
                    @endcan

                    @canany(['users.manage', 'units.manage', 'ranks.manage', 'standards.manage', 'packages.manage', 'audit.view', 'settings.manage'])
                        <flux:sidebar.group :heading="__('Administration')" class="grid">
                            @can('users.manage')
                                <flux:sidebar.item icon="user-group" :href="route('admin.users')" :current="request()->routeIs('admin.users')" wire:navigate>{{ __('User Management') }}</flux:sidebar.item>
                                <flux:sidebar.item icon="key" :href="route('admin.permissions')" :current="request()->routeIs('admin.permissions')" wire:navigate>{{ __('Roles & Permissions') }}</flux:sidebar.item>
                            @endcan
                            @can('units.manage')
                                <flux:sidebar.item icon="building-office" :href="route('admin.units')" :current="request()->routeIs('admin.units')" wire:navigate>{{ __('Unit Management') }}</flux:sidebar.item>
                            @endcan
                            @can('ranks.manage')
                                <flux:sidebar.item icon="star" :href="route('admin.ranks')" :current="request()->routeIs('admin.ranks')" wire:navigate>{{ __('Rank Management') }}</flux:sidebar.item>
                            @endcan
                            @can('standards.manage')
                                <flux:sidebar.item icon="adjustments-horizontal" :href="route('admin.standards')" :current="request()->routeIs('admin.standards')" wire:navigate>{{ __('BMI Standards') }}</flux:sidebar.item>
                            @endcan
                            @can('packages.manage')
                                <flux:sidebar.item icon="rectangle-stack" :href="route('admin.packages')" :current="request()->routeIs('admin.packages')" wire:navigate>{{ __('Intervention Packages') }}</flux:sidebar.item>
                            @endcan
                            @can('audit.view')
                                <flux:sidebar.item icon="shield-check" :href="route('admin.audit')" :current="request()->routeIs('admin.audit')" wire:navigate>{{ __('Audit Trail') }}</flux:sidebar.item>
                            @endcan
                            @can('settings.manage')
                                <flux:sidebar.item icon="cog-6-tooth" :href="route('admin.settings')" :current="request()->routeIs('admin.settings')" wire:navigate>{{ __('System Settings') }}</flux:sidebar.item>
                            @endcan
                        </flux:sidebar.group>
                    @endcanany

                    @if ($hasOwnProfile)
                        <flux:sidebar.group :heading="__('My Health Record')" class="grid">
                            <flux:sidebar.item icon="identification" :href="route('my.profile')" :current="request()->routeIs('my.profile')" wire:navigate>{{ __('My BMI Profile') }}</flux:sidebar.item>
                            <flux:sidebar.item icon="scale" :href="route('my.monitoring')" :current="request()->routeIs('my.monitoring')" wire:navigate>{{ __('My Monthly Monitoring') }}</flux:sidebar.item>
                        </flux:sidebar.group>
                    @endif
                @endif
            </flux:sidebar.nav>

            <flux:spacer />

            <div class="px-2 text-xs text-zinc-500 dark:text-zinc-400">
                <div class="font-medium text-zinc-600 dark:text-zinc-300">{{ $user->role->label() }}</div>
                @if ($user->unit)
                    <div class="truncate">{{ $user->unit->name }}</div>
                @endif
            </div>

            <x-desktop-user-menu class="hidden lg:block" :name="$user->name" />
        </flux:sidebar>

        <!-- Mobile User Menu -->
        <flux:header class="lg:hidden">
            <flux:sidebar.toggle class="lg:hidden" icon="bars-2" inset="left" />

            <flux:spacer />

            <flux:dropdown position="top" align="end">
                <flux:profile
                    :initials="$user->initials()"
                    icon-trailing="chevron-down"
                />

                <flux:menu>
                    <flux:menu.radio.group>
                        <div class="p-0 text-sm font-normal">
                            <div class="flex items-center gap-2 px-1 py-1.5 text-start text-sm">
                                <flux:avatar
                                    :name="$user->name"
                                    :initials="$user->initials()"
                                />

                                <div class="grid flex-1 text-start text-sm leading-tight">
                                    <flux:heading class="truncate">{{ $user->name }}</flux:heading>
                                    <flux:text class="truncate">{{ $user->email }}</flux:text>
                                </div>
                            </div>
                        </div>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <flux:menu.radio.group>
                        <flux:menu.item :href="route('profile.edit')" icon="cog" wire:navigate>
                            {{ __('Settings') }}
                        </flux:menu.item>
                    </flux:menu.radio.group>

                    <flux:menu.separator />

                    <form method="POST" action="{{ route('logout') }}" class="w-full">
                        @csrf
                        <flux:menu.item
                            as="button"
                            type="submit"
                            icon="arrow-right-start-on-rectangle"
                            class="w-full cursor-pointer"
                            data-test="logout-button"
                        >
                            {{ __('Log out') }}
                        </flux:menu.item>
                    </form>
                </flux:menu>
            </flux:dropdown>
        </flux:header>

        {{ $slot }}

        @persist('toast')
            <flux:toast.group>
                <flux:toast />
            </flux:toast.group>
        @endpersist

        @fluxScripts
    </body>
</html>
