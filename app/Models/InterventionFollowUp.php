<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Carbon\CarbonInterface;
use Database\Factories\InterventionFollowUpFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $personnel_intervention_id
 * @property CarbonInterface $follow_up_on
 * @property float|null $weight_kg
 * @property string|null $notes
 * @property int|null $recorded_by
 * @property-read PersonnelIntervention $intervention
 */
#[Fillable(['personnel_intervention_id', 'follow_up_on', 'weight_kg', 'notes', 'recorded_by'])]
class InterventionFollowUp extends Model
{
    /** @use HasFactory<InterventionFollowUpFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'follow_up_on' => 'date',
            'weight_kg' => 'float',
        ];
    }

    /**
     * @return BelongsTo<PersonnelIntervention, $this>
     */
    public function intervention(): BelongsTo
    {
        return $this->belongsTo(PersonnelIntervention::class, 'personnel_intervention_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recorder(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    public function auditPersonnelId(): ?int
    {
        return $this->intervention?->personnel_id;
    }
}
