<?php

namespace App\Policies;

use App\Enums\AssessmentStatus;
use App\Enums\Permission;
use App\Models\BmiAssessment;
use App\Models\User;

class BmiAssessmentPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->hasPermission(Permission::ViewAllRecords) || $user->hasPermission(Permission::EncodeAssessments) || $user->hasUnitScope();
    }

    /**
     * Drafts are visible only to Health Service encoders; everyone else sees official results only.
     */
    public function view(User $user, BmiAssessment $assessment): bool
    {
        if (! $user->canAccessPersonnel($assessment->personnel)) {
            return false;
        }

        return $assessment->status !== AssessmentStatus::Draft || $user->hasPermission(Permission::EncodeAssessments);
    }

    public function create(User $user): bool
    {
        return $user->hasPermission(Permission::EncodeAssessments);
    }

    /**
     * Drafts may be edited by encoders; submitted or certified results need correction rights.
     */
    public function update(User $user, BmiAssessment $assessment): bool
    {
        if (! $user->canAccessPersonnel($assessment->personnel)) {
            return false;
        }

        return match ($assessment->status) {
            AssessmentStatus::Draft => $user->hasPermission(Permission::EncodeAssessments),
            AssessmentStatus::Submitted, AssessmentStatus::Certified => $user->hasPermission(Permission::CorrectRecords),
            AssessmentStatus::Superseded => false,
        };
    }

    /**
     * Segregation of duties: the encoder of an assessment cannot certify it (except the Super Administrator).
     */
    public function certify(User $user, BmiAssessment $assessment): bool
    {
        return $assessment->status === AssessmentStatus::Submitted
            && $user->hasPermission(Permission::CertifyAssessments)
            && $user->canAccessPersonnel($assessment->personnel)
            && ($assessment->encoded_by !== $user->id || $user->isSuperAdmin());
    }

    public function print(User $user, BmiAssessment $assessment): bool
    {
        return $this->view($user, $assessment)
            && ($assessment->personnel->user_id === $user->id || $user->hasPermission(Permission::GenerateReports));
    }
}
