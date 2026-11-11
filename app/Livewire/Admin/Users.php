<?php

namespace App\Livewire\Admin;

use App\Enums\Role;
use App\Livewire\Concerns\WithLookups;
use App\Models\Personnel;
use App\Models\Unit;
use App\Models\User;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('User Management')]
class Users extends Component
{
    use WithLookups, WithPagination;

    #[Url(except: '')]
    public string $search = '';

    #[Url(except: '')]
    public string $roleFilter = '';

    public ?int $editingId = null;

    public string $name = '';

    public string $designation = '';

    public string $email = '';

    public string $role = 'personnel';

    public string $unit_id = '';

    public bool $is_active = true;

    public string $password = '';

    public ?int $personnelId = null;

    public string $personnelSearch = '';

    public function updating(string $property): void
    {
        if (in_array($property, ['search', 'roleFilter'], true)) {
            $this->resetPage();
        }
    }

    /**
     * @return Collection<int, Personnel>
     */
    #[Computed]
    public function personnelResults(): Collection
    {
        if (mb_strlen(trim($this->personnelSearch)) < 2) {
            return collect();
        }

        return Personnel::query()->search($this->personnelSearch)
            ->where(fn (Builder $query) => $query->whereNull('user_id')->when($this->editingId, fn ($q) => $q->orWhere('user_id', $this->editingId)))
            ->with('rank:id,abbreviation')->limit(8)->get();
    }

    #[Computed]
    public function linkedPersonnel(): ?Personnel
    {
        return $this->personnelId ? Personnel::with('rank')->find($this->personnelId) : null;
    }

    public function create(): void
    {
        $this->resetForm();
        Flux::modal('user-form')->show();
    }

    public function edit(int $id): void
    {
        $user = User::with('personnel')->findOrFail($id);
        $this->resetForm();

        $this->editingId = $user->id;
        $this->name = $user->name;
        $this->designation = (string) $user->designation;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->unit_id = (string) $user->unit_id;
        $this->is_active = $user->is_active;
        $this->personnelId = $user->personnel?->id;

        Flux::modal('user-form')->show();
    }

    public function linkPersonnel(int $id): void
    {
        $this->personnelId = $id;
        $this->personnelSearch = '';
    }

    public function save(): void
    {
        $actor = Auth::user();
        $assignableRoles = collect(Role::cases())->reject(fn (Role $role) => $role === Role::SuperAdmin && ! $actor->isSuperAdmin())->map->value->all();

        $validated = $this->validate([
            'name' => ['required', 'string', 'max:255'],
            'designation' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($this->editingId)],
            'role' => ['required', Rule::in($assignableRoles)],
            'unit_id' => [$this->role === Role::UnitRepresentative->value ? 'required' : 'nullable', Rule::exists('units', 'id')->whereNull('deleted_at')],
            'is_active' => ['boolean'],
            'password' => [$this->editingId ? 'nullable' : 'required', Password::defaults()],
            'personnelId' => ['nullable', 'integer', 'exists:personnel,id'],
        ], ['unit_id.required' => __('Unit Representatives must be assigned to a unit.')]);

        $user = $this->editingId ? User::findOrFail($this->editingId) : new User;
        $this->guardAgainstLockout($user, Role::from($this->role), $this->is_active);

        DB::transaction(function () use ($user, $validated): void {
            $user->fill(['name' => $validated['name'], 'designation' => $validated['designation'] ?: null, 'email' => strtolower($validated['email'])]);

            if ($validated['password']) {
                $user->password = $validated['password'];
            }

            $user->forceFill([
                'role' => Role::from($validated['role']),
                'unit_id' => $validated['unit_id'] ?: null,
                'is_active' => $validated['is_active'],
                'email_verified_at' => $user->email_verified_at ?? now(),
            ])->save();

            Personnel::query()->where('user_id', $user->id)->when($this->personnelId, fn ($q) => $q->whereKeyNot($this->personnelId))->update(['user_id' => null]);

            if ($this->personnelId) {
                $personnel = Personnel::findOrFail($this->personnelId);

                if ($personnel->user_id && $personnel->user_id !== $user->id) {
                    throw ValidationException::withMessages(['personnelId' => __('That personnel is already linked to another account.')]);
                }

                $personnel->forceFill(['user_id' => $user->id])->save();
            }
        });

        Flux::modal('user-form')->close();
        Flux::toast(variant: 'success', text: __('User account saved.'));
    }

    public function toggleActive(int $id): void
    {
        $user = User::findOrFail($id);
        $this->guardAgainstLockout($user, $user->role, ! $user->is_active);

        $user->forceFill(['is_active' => ! $user->is_active])->save();

        Flux::toast(text: $user->is_active ? __('Account activated.') : __('Account deactivated.'));
    }

    private function guardAgainstLockout(User $user, Role $newRole, bool $active): void
    {
        if (! $user->exists) {
            return;
        }

        if ($user->id === Auth::id() && (! $active || $newRole !== $user->role)) {
            throw ValidationException::withMessages(['role' => __('You cannot deactivate your own account or change your own role.')]);
        }

        if ($user->isSuperAdmin() && (! Auth::user()->isSuperAdmin())) {
            throw ValidationException::withMessages(['role' => __('Only a Super Administrator can modify a Super Administrator account.')]);
        }

        $isLastSuperAdmin = $user->isSuperAdmin()
            && User::query()->where('role', Role::SuperAdmin->value)->where('is_active', true)->whereKeyNot($user->id)->doesntExist();

        if ($isLastSuperAdmin && (! $active || $newRole !== Role::SuperAdmin)) {
            throw ValidationException::withMessages(['role' => __('At least one active Super Administrator is required.')]);
        }
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->reset('editingId', 'name', 'designation', 'email', 'unit_id', 'password', 'personnelId', 'personnelSearch');
        $this->role = Role::Personnel->value;
        $this->is_active = true;
    }

    public function render(): View
    {
        return view('livewire.admin.users', [
            'users' => User::query()
                ->when($this->search !== '', fn (Builder $query) => $query->where(fn ($q) => $q->where('name', 'like', "%{$this->search}%")->orWhere('email', 'like', "%{$this->search}%")))
                ->when($this->roleFilter !== '', fn (Builder $query) => $query->where('role', $this->roleFilter))
                ->with(['unit:id,code', 'personnel:id,user_id,employee_number'])
                ->orderBy('name')
                ->paginate(20),
            'roles' => Role::options(),
            'allUnits' => Unit::query()->orderBy('name')->get(['id', 'code', 'name']),
        ]);
    }
}
