<?php

namespace App\Policies;

use App\Models\User;
use App\Models\RfqResponse;
use Illuminate\Auth\Access\HandlesAuthorization;

class RfqResponsePolicy
{


    use HandlesAuthorization;

    /**
     * Determine whether the user can view any RFQ responses.
     */
    public function viewAny(User $user): bool
    {
        return true; // All authenticated users can list RFQ responses
    }

    /**
     * Determine whether the user can view a specific RFQ response.
     */
    public function view(User $user, RfqResponse $rfqResponse): bool
    {
        return true; // All authenticated users can view RFQ responses
    }

    /**
     * Determine whether the user can create RFQ responses.
     */
    public function create(User $user, ?Procurement $procurement = null): bool
    {
        // Admins can always create
        if ($user->hasRole('admin')) {
            return true;
        }

        // Must have an employee record
        $employee = $user->employee;
        if (!$employee || !$procurement) {
            return false;
        }

        // Employee must be assigned to THIS procurement
        return $procurement->employees()->where('employees.id', $employee->id)->exists();
    }

    /**
     * Determine whether the user can update a specific RFQ response.
     */
    public function update(User $user, RfqResponse $rfqResponse): bool
    {
        // Admins can always update
        if ($user->hasRole('admin')) {
            return true;
        }

        // Employees assigned to the procurement can update
        $employee = $user->employee;
        if (!$employee) {
            return false;
        }

        return $rfqResponse->procurement->employees()->where('employees.id', $employee->id)->exists();
    }

    /**
     * Determine whether the user can delete a specific RFQ response.
     */
    public function delete(User $user, RfqResponse $rfqResponse): bool
    {
        // Same rules as update
        return $this->update($user, $rfqResponse);
    }
}