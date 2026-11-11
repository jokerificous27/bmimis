<?php

namespace App\Livewire\Admin;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\ActivityLog;
use App\Models\RolePermission;
use Flux\Flux;
use Illuminate\Contracts\View\View;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Configurable permission matrix. The Super Administrator always has every permission.
 */
#[Title('Roles & Permissions')]
class Permissions extends Component
{
    /**
     * @var array<string, list<string>>
     */
    public array $matrix = [];

    public function mount(): void
    {
        foreach ($this->editableRoles() as $role) {
            $this->matrix[$role->value] = RolePermission::valuesFor($role);
        }
    }

    /**
     * @return list<Role>
     */
    private function editableRoles(): array
    {
        return array_values(array_filter(Role::cases(), fn (Role $role) => $role !== Role::SuperAdmin));
    }

    public function save(): void
    {
        $valid = array_map(fn (Permission $permission) => $permission->value, Permission::cases());

        foreach ($this->editableRoles() as $role) {
            $selected = array_values(array_intersect($this->matrix[$role->value] ?? [], $valid));
            $before = RolePermission::valuesFor($role);

            if (array_diff($before, $selected) || array_diff($selected, $before)) {
                RolePermission::sync($role, $selected);
                ActivityLog::record('permissions.updated', description: "Permissions updated for {$role->label()}", oldValues: ['permissions' => $before], newValues: ['permissions' => $selected]);
            }
        }

        Flux::toast(variant: 'success', text: __('Permissions saved.'));
    }

    public function restoreDefaults(Role $role): void
    {
        abort_if($role === Role::SuperAdmin, 403);

        $this->matrix[$role->value] = array_map(fn (Permission $permission) => $permission->value, $role->defaultPermissions());
    }

    public function render(): View
    {
        return view('livewire.admin.permissions', [
            'roles' => $this->editableRoles(),
            'groups' => collect(Permission::cases())->groupBy(fn (Permission $permission) => $permission->group()),
        ]);
    }
}
