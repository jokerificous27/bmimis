<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Enums\Role;
use App\Models\Personnel;
use App\Models\User;

class PersonnelPolicy
{
    /**
     * Directory access: staff with record access, or unit representatives (limited to their unit by query scope).
     */
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewAllRecords)
            || ($user->hasRole(Role::UnitRepresentative, Role::HealthServiceEncoder, Role::HealthServiceAdmin) && $user->unit_id !== null);
    }

    public function view(User $user, Personnel $personnel): bool
    {
        return $user->canAccessPersonnel($personnel);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::ManagePersonnel);
    }

    public function update(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission(Permission::ManagePersonnel) && $user->canAccessPersonnel($personnel);
    }

    public function delete(User $user, Personnel $personnel): bool
    {
        return $this->update($user, $personnel);
    }

    public function encodeAssessment(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission(Permission::EncodeAssessments) && $user->canAccessPersonnel($personnel);
    }

    public function encodeMonitoring(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission(Permission::EncodeMonitoring) && $user->canAccessPersonnel($personnel);
    }

    public function manageInterventions(User $user, Personnel $personnel): bool
    {
        return $user->hasPermission(Permission::ManageInterventions) && $user->canAccessPersonnel($personnel);
    }

    /**
     * Print or download the personnel's official forms and reports.
     */
    public function printReports(User $user, Personnel $personnel): bool
    {
        return $user->canAccessPersonnel($personnel)
            && ($personnel->user_id === $user->id || $user->hasPermission(Permission::GenerateReports));
    }
}
