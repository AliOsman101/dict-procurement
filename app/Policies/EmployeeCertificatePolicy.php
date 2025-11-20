<?php

namespace App\Policies;

use App\Models\EmployeeCertificate;
use App\Models\User;
use Illuminate\Auth\Access\HandlesAuthorization;

class EmployeeCertificatePolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any models.
     */
    public function viewAny(User $user): bool
    {
        return ($user->hasRole('admin') &&  $user->employee !== null)  || $user->employee->employee_no !== null;
        // return false;

    }

    /**
     * Determine whether the user can view the model.
     */
    public function view(User $user, EmployeeCertificate $employeeCertificate): bool
    {
        return ($user->hasRole('admin') &&  $user->employee !== null)  || ($user->employee && $user->employee->id === $employeeCertificate->employee_id);
        // return false;
    }

    /**
     * Determine whether the user can create models.
     */
    public function create(User $user): bool
    {
        return $user->employee !== null;
    }

    /**
     * Determine whether the user can update the model.
     */
    public function update(User $user, EmployeeCertificate $employeeCertificate): bool
    {
        return $user->employee && $user->employee->id === $employeeCertificate->employee_id;
    }

    /**
     * Determine whether the user can delete the model.
     */
    public function delete(User $user, EmployeeCertificate $employeeCertificate): bool
    {
        return $user->employee && $user->employee->id === $employeeCertificate->employee_id;
    }

    /**
     * Determine whether the user can restore the model.
     */
    public function restore(User $user, EmployeeCertificate $employeeCertificate): bool
    {
        return $user->employee && $user->employee->id === $employeeCertificate->employee_id;
    }

    /**
     * Determine whether the user can permanently delete the model.
     */
    public function forceDelete(User $user, EmployeeCertificate $employeeCertificate): bool
    {
        return $user->employee && $user->employee->id === $employeeCertificate->employee_id;
    }
}