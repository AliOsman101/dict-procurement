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
        $employee = $user->employee;
        if (!$employee) return false;

        // Check if PPMP document exists via direct query
        $hasPpmpDocument = \App\Models\ProcurementDocument::whereHas('procurement', function ($q) use ($procurement) {
                $q->where('parent_id', $procurement->id)->where('module', 'ppmp');
            })
            ->where('module', 'ppmp')
            ->exists();

        if ($hasPpmpDocument) {
            return false;
        }

        if ($procurement->created_by === $user->id) {
            return true;
        }

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
