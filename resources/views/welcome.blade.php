<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => __('Welcome')])
    </head>
    <body class="min-h-screen bg-zinc-50 text-zinc-800 antialiased dark:bg-zinc-900 dark:text-zinc-100">
        <div class="mx-auto flex min-h-screen max-w-5xl flex-col px-6 py-8">
            <header class="flex items-center justify-between">
                <div class="flex items-center gap-3">
                    <span class="flex size-10 items-center justify-center rounded-lg bg-navy-800 text-white">
                        <x-app-logo-icon class="size-6" />
                    </span>
                    <div class="leading-tight">
                        <div class="text-xs font-medium uppercase tracking-wide text-zinc-500">{{ __('Philippine National Police') }}</div>
                        <div class="font-semibold">{{ __('Health Service') }}</div>
                    </div>
                </div>

                @auth
                    <flux:button :href="route('dashboard')" variant="primary" size="sm">{{ __('Go to dashboard') }}</flux:button>
                @else
                    <flux:button :href="route('login')" variant="primary" size="sm">{{ __('Sign in') }}</flux:button>
                @endauth
            </header>

            <main class="flex flex-1 flex-col justify-center py-16">
                <p class="text-sm font-medium text-health-600">{{ __('Personnel Health Information System') }}</p>
                <h1 class="mt-2 max-w-2xl text-3xl font-semibold tracking-tight text-navy-900 sm:text-4xl dark:text-white">
                    {{ __('PNP Personnel BMI Monitoring and Health Information System') }}
                </h1>
                <p class="mt-4 max-w-2xl text-zinc-600 dark:text-zinc-300">
                    {{ __('A centralized platform for recording BMI assessments, monthly weight monitoring, interventions and compliance reporting of PNP personnel.') }}
                </p>

                <div class="mt-10 grid gap-4 sm:grid-cols-3">
                    @foreach ([
                        ['clipboard-document-check', __('Individual BMI profiles'), __('Every personnel member has a continuously updated digital BMI profile.')],
                        ['scale', __('Monthly weight monitoring'), __('January–December weigh-ins with automatic BMI and compliance tracking.')],
                        ['chart-bar', __('Health Service analytics'), __('Unit compliance, classification trends and official printable forms.')],
                    ] as [$icon, $heading, $text])
                        <div class="rounded-xl border border-zinc-200 bg-white p-5 dark:border-zinc-700 dark:bg-zinc-800">
                            <flux:icon :name="$icon" class="size-6 text-navy-700 dark:text-navy-200" />
                            <div class="mt-3 font-medium">{{ $heading }}</div>
                            <p class="mt-1 text-sm text-zinc-500 dark:text-zinc-400">{{ $text }}</p>
                        </div>
                    @endforeach
                </div>
            </main>

            <footer class="text-xs text-zinc-500">
                {{ __('Authorized use only. Personal and health information is protected under the Data Privacy Act of 2012 (RA 10173).') }}
            </footer>
        </div>
        @fluxScripts
    </body>
</html>
