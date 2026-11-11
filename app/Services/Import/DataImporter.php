<?php

namespace App\Services\Import;

use App\Enums\PersonnelStatus;
use App\Enums\Sex;
use App\Models\ActivityLog;
use App\Models\MonthlyWeightMonitoring;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\Rank;
use App\Models\Unit;
use App\Models\User;
use App\Services\MonthlyMonitoringService;
use App\Support\Settings;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * Validates and imports personnel masterlists and historical monthly weights from spreadsheet rows.
 * Validation runs first so the user can review an error report before anything is committed.
 */
class DataImporter
{
    public const PERSONNEL_COLUMNS = ['employee_number', 'rank', 'last_name', 'first_name', 'middle_name', 'suffix', 'sex', 'date_of_birth', 'unit', 'position', 'contact_number', 'email'];

    public const MONTHLY_COLUMNS = ['employee_number', 'year', 'month', 'weight_kg', 'measured_on', 'remarks'];

    public function __construct(
        private Settings $settings,
        private MonthlyMonitoringService $monitoring,
    ) {}

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{valid: list<array<string, mixed>>, errors: array<int, list<string>>, total: int}
     */
    public function validate(string $type, array $rows, User $user): array
    {
        return $type === 'monthly' ? $this->validateMonthly($rows, $user) : $this->validatePersonnel($rows);
    }

    /**
     * @param  list<array<string, mixed>>  $valid
     */
    public function commit(string $type, array $valid, User $user, string $filename): int
    {
        $count = DB::transaction(fn () => $type === 'monthly' ? $this->commitMonthly($valid, $user) : $this->commitPersonnel($valid));

        ActivityLog::record('import.'.$type, newValues: ['file' => $filename, 'imported' => $count], description: $type === 'monthly' ? "Imported {$count} monthly weight records" : "Imported {$count} personnel records");

        return $count;
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{valid: list<array<string, mixed>>, errors: array<int, list<string>>, total: int}
     */
    private function validatePersonnel(array $rows): array
    {
        $ranks = Rank::query()->pluck('id', 'abbreviation')->mapWithKeys(fn ($id, $abbr) => [mb_strtolower($abbr) => $id]);
        $units = Unit::query()->pluck('id', 'code')->mapWithKeys(fn ($id, $code) => [mb_strtolower($code) => $id]);
        $existing = Personnel::withTrashed()->pluck('employee_number')->map(fn ($n) => mb_strtolower($n))->flip();
        $seen = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $row = array_map('trim', $row);
            $sex = match (mb_strtolower($row['sex'] ?? '')) {
                'm', 'male' => Sex::Male->value,
                'f', 'female' => Sex::Female->value,
                default => null,
            };
            $data = [
                'employee_number' => $row['employee_number'] ?? '',
                'rank_id' => $ranks[mb_strtolower($row['rank'] ?? '')] ?? null,
                'unit_id' => $units[mb_strtolower($row['unit'] ?? '')] ?? null,
                'last_name' => $row['last_name'] ?? '',
                'first_name' => $row['first_name'] ?? '',
                'middle_name' => ($row['middle_name'] ?? '') ?: null,
                'suffix' => ($row['suffix'] ?? '') ?: null,
                'sex' => $sex,
                'date_of_birth' => $this->parseDate($row['date_of_birth'] ?? ''),
                'position' => ($row['position'] ?? '') ?: null,
                'contact_number' => ($row['contact_number'] ?? '') ?: null,
                'email' => ($row['email'] ?? '') ?: null,
            ];

            $validator = Validator::make($data, [
                'employee_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/'],
                'rank_id' => ['required'],
                'unit_id' => ['required'],
                'last_name' => ['required', 'string', 'max:100'],
                'first_name' => ['required', 'string', 'max:100'],
                'sex' => ['required', Rule::enum(Sex::class)],
                'date_of_birth' => ['required', 'date', 'before:-17 years', 'after:1940-01-01'],
                'email' => ['nullable', 'email'],
            ], [
                'rank_id.required' => "Unknown rank \"{$row['rank']}\".",
                'unit_id.required' => "Unknown unit code \"{$row['unit']}\".",
                'sex.required' => 'Sex must be M, F, Male or Female.',
                'date_of_birth.required' => 'Date of birth is missing or not a valid date (use YYYY-MM-DD).',
            ]);

            $messages = $validator->errors()->all();
            $key = mb_strtolower($data['employee_number']);

            if ($key !== '' && $existing->has($key)) {
                $messages[] = 'Employee number already exists in the system.';
            }

            if ($key !== '' && isset($seen[$key])) {
                $messages[] = "Duplicate employee number (also on row {$seen[$key]}).";
            }

            $seen[$key] = $line;

            if ($messages) {
                $errors[$line] = $messages;
            } else {
                $valid[] = $data + ['_row' => $line];
            }
        }

        return ['valid' => $valid, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * @param  list<array<string, string>>  $rows
     * @return array{valid: list<array<string, mixed>>, errors: array<int, list<string>>, total: int}
     */
    private function validateMonthly(array $rows, User $user): array
    {
        $personnel = Personnel::query()->visibleTo($user)->get(['id', 'employee_number'])->keyBy(fn ($p) => mb_strtolower($p->employee_number));
        $existing = MonthlyWeightMonitoring::query()->whereIn('personnel_id', $personnel->pluck('id'))->get(['personnel_id', 'year', 'month'])
            ->map(fn ($m) => $m->personnel_id.'-'.$m->year.'-'.$m->month)->flip();
        $months = collect(MonthlyWeightMonitoring::MONTHS)->mapWithKeys(fn ($name, $number) => [mb_strtolower($name) => $number, mb_strtolower(substr($name, 0, 3)) => $number]);
        $seen = [];
        $valid = [];
        $errors = [];

        foreach ($rows as $index => $row) {
            $line = $index + 2;
            $row = array_map('trim', $row);
            $person = $personnel->get(mb_strtolower($row['employee_number'] ?? ''));
            $month = is_numeric($row['month'] ?? null) ? (int) $row['month'] : ($months[mb_strtolower($row['month'] ?? '')] ?? null);

            $data = [
                'personnel_id' => $person?->id,
                'employee_number' => $row['employee_number'] ?? '',
                'year' => (int) ($row['year'] ?? 0),
                'month' => $month,
                'weight_kg' => $row['weight_kg'] ?? '',
                'measured_on' => $this->parseDate($row['measured_on'] ?? ''),
                'remarks' => ($row['remarks'] ?? '') ?: null,
            ];

            $validator = Validator::make($data, [
                'personnel_id' => ['required'],
                'year' => ['required', 'integer', 'between:2000,'.today()->year],
                'month' => ['required', 'integer', 'between:1,12'],
                'weight_kg' => ['required', 'numeric', 'decimal:0,2', 'min:'.$this->settings->get('weight_min_kg'), 'max:'.$this->settings->get('weight_max_kg')],
                'measured_on' => ['nullable', 'date', 'before_or_equal:today'],
            ], [
                'personnel_id.required' => "Employee number \"{$data['employee_number']}\" not found or not within your access.",
                'month.required' => 'Month must be 1–12 or a month name.',
                'weight_kg.min' => 'Weight is below the plausible range (kg).',
                'weight_kg.max' => 'Weight is above the plausible range (kg, not pounds).',
            ]);

            $messages = $validator->errors()->all();

            if ($person && $month && $data['year']) {
                $key = $person->id.'-'.$data['year'].'-'.$month;

                if (CarbonImmutable::create($data['year'], $month, 1)->isAfter(today())) {
                    $messages[] = 'Month is in the future.';
                }

                if ($existing->has($key)) {
                    $messages[] = 'A weight for this personnel and month already exists (corrections must be made in the monitoring sheet).';
                }

                if (isset($seen[$key])) {
                    $messages[] = "Duplicate month for this personnel (also on row {$seen[$key]}).";
                }

                $seen[$key] = $line;
            }

            if ($messages) {
                $errors[$line] = $messages;
            } else {
                $valid[] = $data + ['_row' => $line];
            }
        }

        return ['valid' => $valid, 'errors' => $errors, 'total' => count($rows)];
    }

    /**
     * @param  list<array<string, mixed>>  $valid
     */
    private function commitPersonnel(array $valid): int
    {
        foreach ($valid as $data) {
            $personnel = Personnel::create(collect($data)->except('_row')->all() + ['status' => PersonnelStatus::Active]);

            PersonnelAssignment::create([
                'personnel_id' => $personnel->id,
                'unit_id' => $personnel->unit_id,
                'rank_id' => $personnel->rank_id,
                'position' => $personnel->position,
                'effective_from' => today(),
                'remarks' => 'Imported',
                'created_by' => Auth::id(),
            ]);
        }

        return count($valid);
    }

    /**
     * @param  list<array<string, mixed>>  $valid
     */
    private function commitMonthly(array $valid, User $user): int
    {
        $people = Personnel::query()->visibleTo($user)->whereIn('id', array_column($valid, 'personnel_id'))->get()->keyBy('id');

        foreach ($valid as $data) {
            $this->monitoring->record(
                personnel: $people[$data['personnel_id']],
                year: $data['year'],
                month: $data['month'],
                weightKg: (float) $data['weight_kg'],
                measuredOn: $data['measured_on'] ? CarbonImmutable::parse($data['measured_on']) : null,
                remarks: $data['remarks'] ?? 'Imported',
                notify: false,
                captureMethod: 'import',
            );
        }

        return count($valid);
    }

    /**
     * Accepts ISO dates, common formats and Excel serial date numbers.
     */
    private function parseDate(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        if (is_numeric($value) && (float) $value > 10000 && (float) $value < 80000) {
            return CarbonImmutable::create(1899, 12, 30)->addDays((int) $value)->format('Y-m-d');
        }

        try {
            return CarbonImmutable::parse($value)->format('Y-m-d');
        } catch (Throwable) {
            return null;
        }
    }
}
