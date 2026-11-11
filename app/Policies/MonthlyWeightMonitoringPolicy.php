<?php

namespace App\Policies;

use App\Enums\Permission;
use App\Models\MonthlyWeightMonitoring;
use App\Models\User;

class MonthlyWeightMonitoringPolicy
{
    public function view(User $user, MonthlyWeightMonitoring $record): bool
    {
        return $user->canAccessPersonnel($record->personnel);
    }

    /**
     * Correcting an existing entry requires correction rights; every correction is audited.
     */
    public function update(User $user, MonthlyWeightMonitoring $record): bool
    {
        return $user->hasPermission(Permission::CorrectRecords) && $user->canAccessPersonnel($record->personnel);
    }

    /**
     * Waist, hip and wrist are measured again by the Health Service staff who encode assessments
     * (encoders included); updating them creates a new assessment.
     */
    public function reviewMeasurements(User $user, MonthlyWeightMonitoring $record): bool
    {
        return $user->hasPermission(Permission::EncodeAssessments)
            && $user->canAccessPersonnel($record->personnel);
    }
}
