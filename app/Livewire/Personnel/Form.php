<?php

namespace App\Livewire\Personnel;

use App\Enums\PersonnelStatus;
use App\Enums\Role;
use App\Enums\Sex;
use App\Livewire\Concerns\WithLookups;
use App\Models\Personnel;
use App\Models\PersonnelAssignment;
use App\Models\User;
use Carbon\CarbonImmutable;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Personnel')]
class Form extends Component
{
    use WithLookups;

    public ?Personnel $personnel = null;

    public string $employee_number = '';

    public string $rank_id = '';

    public string $unit_id = '';

    public string $last_name = '';

    public string $first_name = '';

    public string $middle_name = '';

    public string $suffix = '';

    public string $qualifier = '';

    public string $sex = '';

    public string $date_of_birth = '';

    public string $position = '';

    public string $contact_number = '';

    public string $email = '';

    public string $status = 'active';

    public string $remarks = '';

    public string $assignment_effective_from = '';

    public string $assignment_remarks = '';

    public bool $create_account = false;

    public string $account_email = '';

    public string $account_password = '';

    public bool $account_active = true;

    public bool $confirmDuplicate = false;

    /**
     * @var list<array{id: int, name: string, unit: string|null}>
     */
    public array $possibleDuplicates = [];

    public function mount(?Personnel $personnel = null): void
    {
        if ($personnel?->exists) {
            $this->authorize('update', $personnel);
            $this->personnel = $personnel->load('user');
            $this->fill($personnel->only(['employee_number', 'last_name', 'first_name']));
            $this->rank_id = (string) $personnel->rank_id;
            $this->unit_id = (string) $personnel->unit_id;
            $this->middle_name = (string) $personnel->middle_name;
            $this->suffix = (string) $personnel->suffix;
            $this->qualifier = (string) $personnel->qualifier;
            $this->sex = $personnel->sex->value;
            $this->date_of_birth = (string) $personnel->date_of_birth?->format('Y-m-d');
            $this->position = (string) $personnel->position;
            $this->contact_number = (string) $personnel->contact_number;
            $this->email = (string) $personnel->email;
            $this->status = $personnel->status->value;
            $this->remarks = (string) $personnel->remarks;
            $this->account_active = (bool) $personnel->user?->is_active;
            $this->account_email = (string) ($personnel->user?->email ?? $personnel->email);
        } else {
            $this->authorize('create', Personnel::class);
            $this->personnel = null;
        }

        $this->assignment_effective_from = today()->format('Y-m-d');
    }

    /**
     * Whether unit, rank or position differs from the saved record (a transfer or promotion).
     */
    public function assignmentChanged(): bool
    {
        if (! $this->personnel) {
            return false;
        }

        return (string) $this->personnel->unit_id !== $this->unit_id
            || (string) $this->personnel->rank_id !== $this->rank_id
            || (string) $this->personnel->position !== $this->position;
    }

    /**
     * @return array<string, mixed>
     */
    protected function rules(): array
    {
        $hasAccount = (bool) $this->personnel?->user;

        return [
            'employee_number' => ['required', 'string', 'max:30', 'regex:/^[A-Za-z0-9\-\/]+$/', Rule::unique('personnel', 'employee_number')->ignore($this->personnel?->id)],
            'rank_id' => ['required', 'exists:ranks,id'],
            'unit_id' => ['required', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'last_name' => ['required', 'string', 'max:100'],
            'first_name' => ['required', 'string', 'max:100'],
            'middle_name' => ['nullable', 'string', 'max:100'],
            'suffix' => ['nullable', 'string', 'max:10'],
            'qualifier' => ['nullable', 'string', 'max:20'],
            'sex' => ['required', Rule::enum(Sex::class)],
            'date_of_birth' => ['required', 'date', 'before:-17 years', 'after:1940-01-01'],
            'position' => ['nullable', 'string', 'max:255'],
            'contact_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9+\-\s()]+$/'],
            'email' => ['nullable', 'email', 'max:255'],
            'status' => ['required', Rule::enum(PersonnelStatus::class)],
            'remarks' => ['nullable', 'string', 'max:2000'],
            'assignment_effective_from' => [$this->assignmentChanged() ? 'required' : 'nullable', 'date', 'before_or_equal:today'],
            'assignment_remarks' => ['nullable', 'string', 'max:500'],
            'account_email' => [$this->create_account && ! $hasAccount ? 'required' : 'nullable', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->personnel?->user_id)],
            'account_password' => [$this->create_account && ! $hasAccount ? 'required' : 'nullable', Password::defaults()],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'date_of_birth.before' => __('Personnel must be at least 17 years old.'),
            'employee_number.regex' => __('Use letters, numbers, dashes or slashes only.'),
        ];
    }

    public function save(): void
    {
        $this->personnel ? $this->authorize('update', $this->personnel) : $this->authorize('create', Personnel::class);

        $validated = $this->validate();

        if (! $this->personnel && ! $this->confirmDuplicate && $this->detectDuplicates()) {
            return;
        }

        $attributes = collect($validated)->only([
            'employee_number', 'rank_id', 'unit_id', 'last_name', 'first_name', 'middle_name', 'suffix', 'qualifier',
            'sex', 'date_of_birth', 'position', 'contact_number', 'email', 'status', 'remarks',
        ])->map(fn ($value) => $value === '' ? null : $value)->all();

        $personnel = DB::transaction(function () use ($attributes): Personnel {
            $isNew = ! $this->personnel;
            $assignmentChanged = $this->assignmentChanged();
            $personnel = $this->personnel ?? new Personnel;

            $personnel->fill($attributes)->save();

            if ($isNew || $assignmentChanged) {
                $this->recordAssignment($personnel, $isNew);
            }

            $this->syncAccount($personnel);

            return $personnel;
        });

        Flux::toast(variant: 'success', text: __('Personnel record saved.'));

        $this->redirectRoute('personnel.show', $personnel, navigate: true);
    }

    private function detectDuplicates(): bool
    {
        $this->possibleDuplicates = Personnel::query()
            ->withTrashed()
            ->whereRaw('LOWER(last_name) = ?', [mb_strtolower(trim($this->last_name))])
            ->whereRaw('LOWER(first_name) = ?', [mb_strtolower(trim($this->first_name))])
            ->whereDate('date_of_birth', $this->date_of_birth)
            ->with('unit:id,code')
            ->get()
            ->map(fn (Personnel $p) => ['id' => $p->id, 'name' => $p->formal_name.' ('.$p->employee_number.')', 'unit' => $p->unit?->code])
            ->all();

        return $this->possibleDuplicates !== [];
    }

    private function recordAssignment(Personnel $personnel, bool $isNew): void
    {
        $effective = CarbonImmutable::parse($this->assignment_effective_from ?: today());

        if (! $isNew) {
            $personnel->assignments()->whereNull('effective_to')->update(['effective_to' => $effective->subDay()]);
        }

        PersonnelAssignment::create([
            'personnel_id' => $personnel->id,
            'unit_id' => $personnel->unit_id,
            'rank_id' => $personnel->rank_id,
            'position' => $personnel->position,
            'effective_from' => $effective,
            'remarks' => $this->assignment_remarks ?: ($isNew ? __('Initial record') : __('Reassignment / rank update')),
            'created_by' => Auth::id(),
        ]);
    }

    private function syncAccount(Personnel $personnel): void
    {
        $user = $personnel->user;

        if ($user) {
            if ($user->is_active !== $this->account_active && $user->id !== Auth::id()) {
                $user->forceFill(['is_active' => $this->account_active])->save();
            }

            return;
        }

        if (! $this->create_account) {
            return;
        }

        $user = new User([
            'name' => $personnel->rank_name,
            'email' => strtolower($this->account_email),
            'password' => $this->account_password,
        ]);
        $user->forceFill(['role' => Role::Personnel, 'is_active' => true, 'email_verified_at' => now()])->save();

        $personnel->forceFill(['user_id' => $user->id])->save();
    }

    public function render(): View
    {
        return view('livewire.personnel.form', [
            'sexes' => Sex::options(),
            'statuses' => PersonnelStatus::options(),
        ])->title($this->personnel ? __('Edit personnel') : __('Add personnel'));
    }
}
