<?php

namespace App\Models;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Concerns\RecordsActivity;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Fortify\Contracts\PasskeyUser;
use Laravel\Fortify\PasskeyAuthenticatable;
use Laravel\Fortify\TwoFactorAuthenticatable;

/**
 * @property int $id
 * @property string $name
 * @property string|null $designation
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property Role $role
 * @property int|null $unit_id
 * @property bool $is_active
 * @property Carbon|null $last_login_at
 * @property string|null $two_factor_secret
 * @property string|null $two_factor_recovery_codes
 * @property Carbon|null $two_factor_confirmed_at
 * @property string|null $remember_token
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read Unit|null $unit
 * @property-read Personnel|null $personnel
 */
#[Fillable(['name', 'designation', 'email', 'password'])]
#[Hidden(['password', 'two_factor_secret', 'two_factor_recovery_codes', 'remember_token'])]
class User extends Authenticatable implements PasskeyUser
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable, PasskeyAuthenticatable, RecordsActivity, TwoFactorAuthenticatable;

    /**
     * @var list<string>
     */
    protected array $auditExclude = ['last_login_at'];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => Role::class,
            'is_active' => 'boolean',
            'last_login_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class);
    }

    /**
     * The personnel profile linked to this login account.
     *
     * @return HasOne<Personnel, $this>
     */
    public function personnel(): HasOne
    {
        return $this->hasOne(Personnel::class);
    }

    public function hasRole(Role ...$roles): bool
    {
        return in_array($this->role, $roles, true);
    }

    public function isSuperAdmin(): bool
    {
        return $this->role === Role::SuperAdmin;
    }

    /**
     * Whether the user works in the Health Service administrative interface.
     */
    public function isStaff(): bool
    {
        return $this->role->isStaff();
    }

    public function hasPermission(Permission $permission): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        return in_array($permission->value, RolePermission::valuesFor($this->role), true);
    }

    /**
     * Whether the user may see the given personnel member's health records.
     */
    public function canAccessPersonnel(Personnel $personnel): bool
    {
        if (! $this->is_active) {
            return false;
        }

        if ($personnel->user_id !== null && $personnel->user_id === $this->id) {
            return true;
        }

        if ($this->hasPermission(Permission::ViewAllRecords)) {
            return true;
        }

        return $this->hasUnitScope() && $personnel->unit_id === $this->unit_id;
    }

    /**
     * Whether access is limited to personnel of the user's assigned unit.
     */
    public function hasUnitScope(): bool
    {
        return $this->role !== Role::Personnel
            && $this->unit_id !== null
            && ! $this->hasPermission(Permission::ViewAllRecords);
    }

    /**
     * Get the user's initials
     */
    public function initials(): string
    {
        $initials = Str::initials($this->name, true);

        return Str::length($initials) > 1
            ? Str::substr($initials, 0, 1).Str::substr($initials, -1)
            : $initials;
    }

    public function auditPersonnelId(): ?int
    {
        return null;
    }
}
