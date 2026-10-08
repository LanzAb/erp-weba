<?php

namespace Weba\ProductionPriority\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Weba\ProductionPriority\Models\Evaluation;
use Webkul\Security\Models\User;

/**
 * Evaluations are an audit trail: they can be created (run), viewed and
 * applied (update), but never deleted.
 */
class EvaluationPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_production_priority_evaluation');
    }

    public function view(User $user, Evaluation $evaluation): bool
    {
        return $user->can('view_production_priority_evaluation');
    }

    public function create(User $user): bool
    {
        return $user->can('create_production_priority_evaluation');
    }

    public function update(User $user, Evaluation $evaluation): bool
    {
        return $user->can('update_production_priority_evaluation');
    }

    public function delete(User $user, Evaluation $evaluation): bool
    {
        return false;
    }

    public function deleteAny(User $user): bool
    {
        return false;
    }
}
