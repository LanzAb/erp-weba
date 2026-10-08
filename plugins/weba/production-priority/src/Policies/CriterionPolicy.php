<?php

namespace Weba\ProductionPriority\Policies;

use Illuminate\Auth\Access\HandlesAuthorization;
use Weba\ProductionPriority\Models\Criterion;
use Webkul\Security\Models\User;

class CriterionPolicy
{
    use HandlesAuthorization;

    public function viewAny(User $user): bool
    {
        return $user->can('view_any_production_priority_criterion');
    }

    public function view(User $user, Criterion $criterion): bool
    {
        return $user->can('view_production_priority_criterion');
    }

    public function create(User $user): bool
    {
        return $user->can('create_production_priority_criterion');
    }

    public function update(User $user, Criterion $criterion): bool
    {
        return $user->can('update_production_priority_criterion');
    }

    public function delete(User $user, Criterion $criterion): bool
    {
        return $user->can('delete_production_priority_criterion');
    }

    public function deleteAny(User $user): bool
    {
        return $user->can('delete_any_production_priority_criterion');
    }

    public function reorder(User $user): bool
    {
        return $user->can('reorder_production_priority_criterion');
    }
}
