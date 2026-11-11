<?php

namespace App\Models;

use App\Models\Concerns\RecordsActivity;
use Carbon\CarbonInterface;
use Database\Factories\PersonnelAssignmentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Historical record of a personnel member's unit, rank and position.
 *
 * @property int $id
 * @property int $personnel_id
 * @property int|null $unit_id
 * @property int|null $rank_id
 * @property string|null $position
 * @property CarbonInterface $effective_from
 * @property CarbonInterface|null $effective_to
 * @property string|null $remarks
 * @property int|null $created_by
 */
#[Fillable(['personnel_id', 'unit_id', 'rank_id', 'position', 'effective_from', 'effective_to', 'remarks', 'created_by'])]
class PersonnelAssignment extends Model
{
    /** @use HasFactory<PersonnelAssignmentFactory> */
    use HasFactory, RecordsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'effective_from' => 'date',
            'effective_to' => 'date',
        ];
    }

    /**
     * @return BelongsTo<Personnel, $this>
     */
    public function personnel(): BelongsTo
    {
        return $this->belongsTo(Personnel::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * @return BelongsTo<Rank, $this>
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }
}
