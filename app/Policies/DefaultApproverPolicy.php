<?php

namespace App\Policies;

use App\Models\User;
use App\Models\DefaultApprover;
use Illuminate\Auth\Access\HandlesAuthorization;

class DefaultApproverPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any default approvers.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function viewAny(User $user)
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the default approver.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\DefaultApprover  $defaultApprover
     * @return mixed
     */
    public function view(User $user, DefaultApprover $defaultApprover)
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can create default approvers.
     *
     * @param  \App\Models\User  $user
     * @return mixed
     */
    public function create(User $user)
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the default approver.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\DefaultApprover  $defaultApprover
     * @return mixed
     */
    public function update(User $user, DefaultApprover $defaultApprover)
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the default approver.
     *
     * @param  \App\Models\User  $user
     * @param  \App\Models\DefaultApprover  $defaultApprover
     * @return mixed
     */
    public function delete(User $user, DefaultApprover $defaultApprover)
    {
        return $user->hasRole('admin');
    }

    // Add other policy methods if needed
}
