<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Procurement;
use Illuminate\Auth\Access\HandlesAuthorization;

class ProcurementPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any procurements.
     */
    public function viewAny(User $user): bool
    {
        return true; // all users can list
    }

    /**
     * Determine whether the user can view a specific procurement.
     */
    public function view(User $user, Procurement $procurement): bool
    {
        return true; // everyone can view
    }

    /**
     * Determine whether the user can create procurements.
     */
    public function create(User $user): bool
    {
        // adjust if you have role logic, for now all users can create
        return true;
    }

    /**
     * Determine whether the user can update the procurement.
     */
    public function update(User $user, Procurement $procurement): bool
    {
        // Only assigned employees can edit
        $employee = $user->employee; // User hasOne Employee
        if (!$employee) {
            return false;
        }

        // Creator can always edit
        if ($procurement->created_by === $user->id) {
            return true;
        }

        // If employee is among assigned procurement_employees
        return $procurement->employees()->where('employees.id', $employee->id)->exists();
    }

    /**
     * Determine whether the user can delete the procurement.
     */
    public function delete(User $user, Procurement $procurement): bool
    {
        // same rule as update
        return $this->update($user, $procurement);
    }
}
