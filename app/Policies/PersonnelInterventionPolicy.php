<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\PersonnelIntervention;
use App\Models\User;

class PersonnelInterventionPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ManageInterventions) || $user->hasPermission(Permission::ViewAllRecords) || $user->hasUnitScope();
    }

    public function view(User $user, PersonnelIntervention $intervention): bool
    {
        return $user->canAccessPersonnel($intervention->personnel);
    }

    public function update(User $user, PersonnelIntervention $intervention): bool
    {
        return $user->hasPermission(Permission::ManageInterventions) && $user->canAccessPersonnel($intervention->personnel);
    }
}
