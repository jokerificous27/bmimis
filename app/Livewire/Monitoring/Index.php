<?php

namespace App\Livewire\Monitoring;

use App\Enums\Permission;
use App\Livewire\Concerns\ValidatesMeasurements;
use App\Livewire\Concerns\WithLookups;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Services\ComplianceService;
use App\Services\MonthlyMonitoringService;
use App\Services\PersonnelQuery;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Monthly encoding sheet: record the weigh-ins of many personnel for one month at once.
 */
#[Title('Monthly Weight Monitoring')]
class Index extends Component
{
    use ValidatesMeasurements, WithLookups, WithPagination;

    #[Url]
    public int $year = 0;

    #[Url]
    public int $month = 0;

    #[Url(except: '')]
    public string $unitId = '';

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: 'all')]
    public string $show = 'all';

    /**
     * Weights typed into the sheet, keyed by personnel id.
     *
     * @var array<int|string, string>
     */
    public array $weights = [];

    public string $measuredOn = '';

    public function mount(): void
    {
        $this->year = $this->year ?: (int) today()->format('Y');
        $this->month = $this->month ?: (int) today()->format('n');
        $this->resetMeasuredOn();
    }

    public function updated(string $property): void
    {
        if (in_array($property, ['year', 'month', 'unitId', 'search', 'show'], true)) {
            $this->resetPage();
            $this->weights = [];
            $this->resetMeasuredOn();
        }
    }

    public function canEncode(): bool
    {
        return Auth::user()->hasPermission(Permission::EncodeMonitoring);
    }

    public function saveBatch(MonthlyMonitoringService $monitoring): void
    {
        abort_unless($this->canEncode(), 403);

        $period = CarbonImmutable::create($this->year, $this->month, 1);

        if ($period->isAfter(today())) {
            throw ValidationException::withMessages(['measuredOn' => __('Weights cannot be recorded for a future month.')]);
        }

        $entries = collect($this->weights)->map(fn ($value) => trim((string) $value))->filter(fn ($value) => $value !== '');

        if ($entries->isEmpty()) {
            Flux::toast(variant: 'warning', text: __('Type at least one weight before saving.'));

            return;
        }

        $this->validate([
            'measuredOn' => ['required', 'date', 'after_or_equal:'.$period->format('Y-m-d'), 'before_or_equal:'.$period->endOfMonth()->min(today())->format('Y-m-d')],
        ], ['measuredOn.*' => __('The measurement date must be within the selected month and not in the future.')]);

        Validator::make(
            ['weights' => $entries->all()],
            ['weights.*' => $this->measurementRule('weight', decimals: 2)],
            ['weights.*.min' => __('Below the plausible range.'), 'weights.*.max' => __('Above the plausible range (enter kilograms).'), 'weights.*.decimal' => __('Max. 2 decimals.'), 'weights.*.numeric' => __('Enter a number.')],
        )->validate();

        $people = Personnel::query()->visibleTo(Auth::user())->active()->whereIn('id', $entries->keys())->get()->keyBy('id');
        $saved = 0;
        $errors = [];

        foreach ($entries as $personnelId => $weight) {
            $person = $people->get((int) $personnelId);

            if (! $person || ! Auth::user()->can('encodeMonitoring', $person)) {
                $errors["weights.{$personnelId}"] = __('Not authorized.');

                continue;
            }

            try {
                $monitoring->record($person, $this->year, $this->month, (float) $weight, CarbonImmutable::parse($this->measuredOn));
                $saved++;
                unset($this->weights[$personnelId]);
            } catch (ValidationException $exception) {
                $errors["weights.{$personnelId}"] = collect($exception->errors())->flatten()->first();
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        Flux::toast(variant: 'success', text: trans_choice(':count weight recorded.|:count weights recorded.', $saved, ['count' => $saved]));
    }

    private function resetMeasuredOn(): void
    {
        $period = CarbonImmutable::create($this->year ?: (int) today()->format('Y'), $this->month ?: (int) today()->format('n'), 1);
        $this->measuredOn = ($period->isSameMonth(today()) ? today() : $period->endOfMonth()->min(today()))->format('Y-m-d');
    }

    public function render(ComplianceService $compliance, MonthlyMonitoringService $monitoring): View
    {
        $user = Auth::user();
        $previous = CarbonImmutable::create($this->year, $this->month, 1)->subMonth();
        $base = PersonnelQuery::for($user, ['unit_id' => $this->unitId]);

        $personnel = (clone $base)->active()->search($this->search)
            ->when($this->show === 'missing', fn (Builder $query) => $query->whereDoesntHave('monthlyWeights', fn ($q) => $q->where('year', $this->year)->where('month', $this->month)))
            ->when($this->show === 'recorded', fn (Builder $query) => $query->whereHas('monthlyWeights', fn ($q) => $q->where('year', $this->year)->where('month', $this->month)))
            ->with(['rank:id,abbreviation', 'unit:id,code', 'latestAssessment',
                'monthlyWeights' => fn ($query) => $query->where(fn ($query) => $query
                    ->where(fn ($q) => $q->where('year', $this->year)->where('month', $this->month))
                    ->orWhere(fn ($q) => $q->where('year', $previous->year)->where('month', $previous->month))),
            ])
            ->orderBy('unit_id')->orderBy('last_name')->orderBy('first_name')
            ->paginate(25);

        return view('livewire.monitoring.index', [
            'personnel' => $personnel,
            'summary' => $compliance->summary($base, $this->year, $this->month),
            'deadline' => $monitoring->deadlineFor($this->year, $this->month),
            'periodStatus' => $monitoring->periodStatus($this->year, $this->month),
            'previous' => $previous,
            'isFuture' => CarbonImmutable::create($this->year, $this->month, 1)->isAfter(today()),
            'monthName' => MonthlyWeightMonitoring::MONTHS[$this->month],
        ]);
    }
}
