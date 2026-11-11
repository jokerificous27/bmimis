<?php

namespace App\Providers;

use App\Enums\Permission;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\Bmi\BmiClassifier;
use App\Services\WeighIn\ClaudeScaleReader;
use App\Services\WeighIn\ScaleReader;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Events\Failed;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\Events\Logout;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(Settings::class);
        $this->app->scoped(BmiClassifier::class);
        $this->app->bind(ScaleReader::class, ClaudeScaleReader::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        $this->configureDefaults();
        $this->configurePermissions();
        $this->configureAuthenticationAudit();
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }

    /**
     * Register one gate per permission so views and routes can use `@can('reports.generate')`.
     */
    protected function configurePermissions(): void
    {
        foreach (Permission::cases() as $permission) {
            Gate::define($permission->value, fn (User $user): bool => $user->hasPermission($permission));
        }
    }

    /**
     * Record login, logout and failed login attempts in the audit trail.
     */
    protected function configureAuthenticationAudit(): void
    {
        Event::listen(function (Login $event): void {
            if ($event->user instanceof User) {
                $event->user->forceFill(['last_login_at' => now()])->saveQuietly();
                ActivityLog::record('auth.login', $event->user, userId: $event->user->id, description: 'Signed in');
            }
        });

        Event::listen(function (Logout $event): void {
            if ($event->user instanceof User) {
                ActivityLog::record('auth.logout', $event->user, userId: $event->user->id, description: 'Signed out');
            }
        });

        Event::listen(function (Failed $event): void {
            ActivityLog::record(
                'auth.failed',
                $event->user instanceof User ? $event->user : null,
                userId: $event->user?->getAuthIdentifier(),
                newValues: ['email' => $event->credentials['email'] ?? null],
                description: 'Failed sign-in attempt',
            );
        });
    }
}
