<?php

namespace App\Models;

use App\Enums\AssessmentStatus;
use App\Enums\InterventionStatus;
use App\Enums\Permission;
use App\Enums\PersonnelStatus;
use App\Enums\Sex;
use App\Models\Concerns\RecordsActivity;
use App\Models\Concerns\TracksAuthors;
use Carbon\CarbonInterface;
use Database\Factories\PersonnelFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int|null $user_id
 * @property string $employee_number
 * @property int|null $rank_id
 * @property int|null $unit_id
 * @property string $last_name
 * @property string $first_name
 * @property string|null $middle_name
 * @property string|null $suffix
 * @property string|null $qualifier
 * @property Sex $sex
 * @property CarbonInterface|null $date_of_birth
 * @property string|null $position
 * @property string|null $contact_number
 * @property string|null $email
 * @property PersonnelStatus $status
 * @property string|null $remarks
 * @property-read string $full_name
 * @property-read string $formal_name
 * @property-read string $rank_name
 * @property-read string|null $middle_initial
 * @property-read int|null $age
 * @property-read Rank|null $rank
 * @property-read Unit|null $unit
 * @property-read User|null $user
 * @property-read BmiAssessment|null $latestAssessment
 */
#[Table('personnel')]
#[Fillable([
    'employee_number', 'rank_id', 'unit_id', 'last_name', 'first_name', 'middle_name', 'suffix', 'qualifier',
    'sex', 'date_of_birth', 'position', 'contact_number', 'email', 'status', 'remarks',
])]
class Personnel extends Model
{
    /** @use HasFactory<PersonnelFactory> */
    use HasFactory, RecordsActivity, SoftDeletes, TracksAuthors;

    /**
     * Prefix of the value encoded in a personnel weigh-in QR code.
     */
    public const QR_PREFIX = 'PNPBMI:';

    /**
     * The QR token is an identifier only; it is kept out of audit values.
     *
     * @var list<string>
     */
    protected array $auditExclude = ['qr_token'];

    /**
     * @var list<string>
     */
    protected $hidden = ['qr_token'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sex' => Sex::class,
            'status' => PersonnelStatus::class,
            'date_of_birth' => 'date',
        ];
    }

    /**
     * The value printed in the personnel's weigh-in QR code. A random token, never the name or employee number.
     */
    public function qrPayload(): string
    {
        if (blank($this->qr_token)) {
            $this->regenerateQrToken();
        }

        return self::QR_PREFIX.$this->qr_token;
    }

    /**
     * Issue a new QR token, invalidating the previous card (e.g. when a card is lost).
     */
    public function regenerateQrToken(): void
    {
        $this->forceFill(['qr_token' => self::newQrToken()]);
        $this->auditRemarks = 'Weigh-in QR code issued';
        $this->save();
    }

    /**
     * 13 random characters from 0-9 and A-Z (about 67 bits). Upper-case letters and digits let the QR use
     * its compact alphanumeric mode, so "PNPBMI:" + token fits the smallest QR version (21 × 21 modules),
     * which stays readable when the code is small in a full-body photo or shown on a phone screen.
     */
    private static function newQrToken(): string
    {
        $alphabet = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

        do {
            $token = '';

            for ($i = 0; $i < 13; $i++) {
                $token .= $alphabet[random_int(0, 35)];
            }
        } while (self::withTrashed()->where('qr_token', $token)->exists());

        return $token;
    }

    /**
     * Find personnel from a scanned QR value, limited to records the user may see.
     */
    public static function findByQrPayload(string $payload, User $user): ?self
    {
        $payload = trim($payload);

        if (! str_starts_with($payload, self::QR_PREFIX)) {
            return null;
        }

        $token = substr($payload, strlen(self::QR_PREFIX));

        if ($token === '' || strlen($token) > 64) {
            return null;
        }

        return self::query()->visibleTo($user)->where('qr_token', $token)->first();
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Rank, $this>
     */
    public function rank(): BelongsTo
    {
        return $this->belongsTo(Rank::class);
    }

    /**
     * @return BelongsTo<Unit, $this>
     */
    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class)->withTrashed();
    }

    /**
     * @return HasMany<PersonnelAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(PersonnelAssignment::class)->orderByDesc('effective_from')->orderByDesc('id');
    }

    /**
     * @return HasMany<BmiAssessment, $this>
     */
    public function assessments(): HasMany
    {
        return $this->hasMany(BmiAssessment::class);
    }

    /**
     * Latest submitted or certified assessment (drafts and superseded results are excluded).
     *
     * @return HasOne<BmiAssessment, $this>
     */
    public function latestAssessment(): HasOne
    {
        return $this->hasOne(BmiAssessment::class)->ofMany(
            ['assessed_on' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->whereIn('status', AssessmentStatus::officialValues()),
        );
    }

    /**
     * @return HasMany<MonthlyWeightMonitoring, $this>
     */
    public function monthlyWeights(): HasMany
    {
        return $this->hasMany(MonthlyWeightMonitoring::class);
    }

    /**
     * @return HasMany<PersonnelIntervention, $this>
     */
    public function interventions(): HasMany
    {
        return $this->hasMany(PersonnelIntervention::class)->latest('assigned_on');
    }

    /**
     * @return HasOne<PersonnelIntervention, $this>
     */
    public function activeIntervention(): HasOne
    {
        return $this->hasOne(PersonnelIntervention::class)->ofMany(
            ['assigned_on' => 'max', 'id' => 'max'],
            fn (Builder $query) => $query->where('status', InterventionStatus::Ongoing->value),
        );
    }

    /**
     * Restrict the query to personnel the user is authorized to see.
     *
     * @param  Builder<self>  $query
     */
    public function scopeVisibleTo(Builder $query, User $user): void
    {
        if (! $user->is_active) {
            $query->whereRaw('1 = 0');

            return;
        }

        if ($user->hasPermission(Permission::ViewAllRecords)) {
            return;
        }

        $query->where(function (Builder $query) use ($user): void {
            $query->where('user_id', $user->id);

            if ($user->hasUnitScope()) {
                $query->orWhere('unit_id', $user->unit_id);
            }
        });
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('status', PersonnelStatus::Active->value);
    }

    /**
     * @param  Builder<self>  $query
     */
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);

        if ($term === '') {
            return;
        }

        foreach (preg_split('/\s+/', $term) ?: [] as $word) {
            $query->where(function (Builder $query) use ($word): void {
                $like = '%'.$word.'%';

                $query->where('last_name', 'like', $like)
                    ->orWhere('first_name', 'like', $like)
                    ->orWhere('middle_name', 'like', $like)
                    ->orWhere('employee_number', 'like', $like);
            });
        }
    }

    protected function middleInitial(): Attribute
    {
        return Attribute::get(fn (): ?string => filled($this->middle_name) ? Str::upper(Str::substr($this->middle_name, 0, 1)) : null);
    }

    /**
     * Name as written on the monitoring form, e.g. "Juan D Dela Cruz Jr".
     */
    protected function fullName(): Attribute
    {
        return Attribute::get(fn (): string => collect([$this->first_name, $this->middle_initial, $this->last_name, $this->suffix])
            ->filter()
            ->implode(' '));
    }

    /**
     * Name in directory order, e.g. "DELA CRUZ, Juan D. Jr".
     */
    protected function formalName(): Attribute
    {
        return Attribute::get(fn (): string => Str::upper($this->last_name).', '.collect([
            $this->first_name,
            $this->middle_initial ? $this->middle_initial.'.' : null,
            $this->suffix,
        ])->filter()->implode(' '));
    }

    /**
     * Rank and name, e.g. "PCpl Juan D Dela Cruz".
     */
    protected function rankName(): Attribute
    {
        return Attribute::get(fn (): string => trim(($this->rank?->abbreviation ?? '').' '.$this->full_name));
    }

    protected function age(): Attribute
    {
        return Attribute::get(fn (): ?int => $this->ageOn(now()));
    }

    public function ageOn(CarbonInterface $date): ?int
    {
        return $this->date_of_birth ? (int) $this->date_of_birth->diffInYears($date) : null;
    }

    /**
     * Profile fields that are missing, used by data quality monitoring.
     *
     * @return list<string>
     */
    public function missingProfileFields(): array
    {
        return collect([
            'Date of birth' => $this->date_of_birth,
            'Rank' => $this->rank_id,
            'Unit / Office' => $this->unit_id,
            'Position' => $this->position,
            'Contact number' => $this->contact_number,
        ])->filter(fn (mixed $value): bool => blank($value))->keys()->values()->all();
    }

    public function auditPersonnelId(): ?int
    {
        return $this->id;
    }
}
