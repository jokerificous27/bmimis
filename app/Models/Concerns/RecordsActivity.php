<?php

namespace App\Models\Concerns;

use App\Models\ActivityLog;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;

/**
 * Writes an audit trail entry whenever the model is created, updated or deleted.
 *
 * Set {@see $auditRemarks} before saving to attach a reason (e.g. a correction justification).
 *
 * @mixin Model
 */
trait RecordsActivity
{
    /**
     * Remarks attached to the next audit entry written for this model instance.
     */
    public ?string $auditRemarks = null;

    public static function bootRecordsActivity(): void
    {
        static::created(function (Model $model): void {
            $model->writeActivity('created', [], $model->auditableValues($model->getAttributes()));
        });

        static::updated(function (Model $model): void {
            $changes = $model->auditableValues($model->getChanges());

            if ($changes === []) {
                return;
            }

            $original = Arr::only($model->getOriginal(), array_keys($changes));

            $model->writeActivity('updated', $model->auditableValues($original), $changes);
        });

        static::deleted(function (Model $model): void {
            $model->writeActivity('deleted', $model->auditableValues($model->getAttributes()), []);
        });
    }

    /**
     * Name used as the prefix for audit actions, e.g. "personnel.updated".
     */
    public function auditName(): string
    {
        return Str::snake(class_basename($this));
    }

    /**
     * The personnel record this model relates to, used to filter the audit trail by person.
     */
    public function auditPersonnelId(): ?int
    {
        return $this->getAttribute('personnel_id');
    }

    /**
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>
     */
    protected function auditableValues(array $values): array
    {
        $excluded = array_merge(
            ['created_at', 'updated_at', 'created_by', 'updated_by', 'password', 'remember_token', 'two_factor_secret', 'two_factor_recovery_codes'],
            property_exists($this, 'auditExclude') ? $this->auditExclude : [],
        );

        return collect(Arr::except($values, $excluded))
            ->map(fn (mixed $value): mixed => $value instanceof \BackedEnum ? $value->value : ($value instanceof \DateTimeInterface ? $value->format('Y-m-d H:i:s') : $value))
            ->all();
    }

    /**
     * @param  array<string, mixed>  $old
     * @param  array<string, mixed>  $new
     */
    protected function writeActivity(string $event, array $old, array $new): void
    {
        ActivityLog::record(
            action: $this->auditName().'.'.$event,
            subject: $this,
            personnelId: $this->auditPersonnelId(),
            oldValues: $old,
            newValues: $new,
            remarks: $this->auditRemarks,
        );

        $this->auditRemarks = null;
    }
}
