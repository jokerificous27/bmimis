<?php

namespace App\Models;

use Carbon\CarbonImmutable;
use Database\Factories\ActivityLogFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;
use Illuminate\Support\Str;
use LogicException;

/**
 * Append-only audit trail entry. Entries cannot be updated or deleted through Eloquent.
 *
 * @property int $id
 * @property int|null $user_id
 * @property string $action
 * @property string|null $description
 * @property string|null $subject_type
 * @property int|null $subject_id
 * @property int|null $personnel_id
 * @property array<string, mixed>|null $old_values
 * @property array<string, mixed>|null $new_values
 * @property string|null $remarks
 * @property string|null $ip_address
 * @property string|null $user_agent
 * @property CarbonImmutable $created_at
 */
#[Fillable(['user_id', 'action', 'description', 'subject_type', 'subject_id', 'personnel_id', 'old_values', 'new_values', 'remarks', 'ip_address', 'user_agent', 'created_at'])]
class ActivityLog extends Model
{
    /** @use HasFactory<ActivityLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    public static function booted(): void
    {
        static::updating(fn () => throw new LogicException('Audit trail entries are immutable.'));
        static::deleting(fn () => throw new LogicException('Audit trail entries cannot be deleted.'));
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'old_values' => 'array',
            'new_values' => 'array',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Record an audit trail entry for the current request.
     *
     * @param  array<string, mixed>  $oldValues
     * @param  array<string, mixed>  $newValues
     */
    public static function record(
        string $action,
        ?Model $subject = null,
        ?int $personnelId = null,
        array $oldValues = [],
        array $newValues = [],
        ?string $remarks = null,
        ?string $description = null,
        ?int $userId = null,
    ): self {
        return self::create([
            'user_id' => $userId ?? Auth::id(),
            'action' => $action,
            'description' => $description ?? Str::of($action)->replace(['.', '_'], ' ')->ucfirst()->toString(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'personnel_id' => $personnelId,
            'old_values' => $oldValues ?: null,
            'new_values' => $newValues ?: null,
            'remarks' => $remarks,
            'ip_address' => app()->runningInConsole() ? null : Request::ip(),
            'user_agent' => app()->runningInConsole() ? 'console' : Str::limit((string) Request::userAgent(), 490, ''),
            'created_at' => now(),
        ]);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Personnel, $this>
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class)->withTrashed();
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
