<?php

namespace App\Policies;

use App\Models\User;
use App\Models\NewUser;
use Illuminate\Auth\Access\HandlesAuthorization;

class NewUserPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any instances.
     */
    public function viewAny(User $user)
    {
        // Only admins can view all new users
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, NewUser $newUser)
    {
        // Can view if admin or if the new user is the current user
        return $user->hasRole('admin') || $user->id === $newUser->user_id;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user)
    {
        // Only admins can create new users
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, NewUser $newUser)
    {
        // Can update if admin or if the new user is the current user
        return $user->hasRole('admin') || $user->id === $newUser->user_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, NewUser $newUser)
    {
        // Only admins can delete new users
        return $user->hasRole('admin');
    }
}
