<?php

namespace App\Helpers;

use App\Models\Procurement;

class ProcurementStatusHelper
{
    /**
     * Update parent procurement status based on all child modules
     * 
     * Rules:
     * - If ANY child module is Rejected -> Parent = Rejected
     * - If ANY child module is Pending/Locked -> Parent = Pending
     * - If ALL modules Approved + PPMP uploaded -> Parent = Completed
     * - Otherwise -> Parent = Pending
     */
    public static function updateParentStatus(Procurement $parent): void
    {
        if ($parent->module !== null) {
            return; // Only update parent procurements
        }

        $requiredModules = [
            'purchase_request',
            'request_for_quotation',
            'abstract_of_quotation',
            'bac_resolution_recommending_award',
            'purchase_order'
        ];

        // Check if PPMP is uploaded
        $ppmpChild = $parent->children()->where('module', 'ppmp')->first();
        $ppmpUploaded = $ppmpChild && $ppmpChild->documents()->where('module', 'ppmp')->exists();

        // Check each module status
        $hasRejected = false;
        $hasPending = false;
        $allApproved = true;

        foreach ($requiredModules as $module) {
            $moduleChild = $parent->children()->where('module', $module)->first();

            if (!$moduleChild) {
                $allApproved = false;
                $hasPending = true;
                continue;
            }

            // Check if module has any rejected approval
            $hasRejectedApproval = $moduleChild->approvals()
                ->where('status', 'Rejected')
                ->exists();

            if ($hasRejectedApproval) {
                $hasRejected = true;
                break;
            }

            // Check if module status is Pending or Locked
            if (in_array($moduleChild->status, ['Pending', 'Locked'])) {
                $hasPending = true;
                $allApproved = false;
            }

            // Check if all approvals are approved
            $allApprovalsApproved = $moduleChild->approvals()
                ->where('status', '!=', 'Approved')
                ->doesntExist();

            if (!$allApprovalsApproved || $moduleChild->status !== 'Approved') {
                $allApproved = false;
            }
        }

        // Determine parent status
        if ($hasRejected) {
            $parent->update(['status' => 'Rejected']);
        } elseif ($allApproved && $ppmpUploaded) {
            $parent->update(['status' => 'Completed']);
        } else {
            $parent->update(['status' => 'Pending']);
        }
    }

    /**
     * Check if procurement should appear in Rejected Procurements list
     */
    public static function isRejectedAndNotRevised(Procurement $parent): bool
    {
        if ($parent->status !== 'Rejected') {
            return false;
        }

        $requiredModules = [
            'purchase_request',
            'request_for_quotation',
            'abstract_of_quotation',
            'bac_resolution_recommending_award',
            'purchase_order'
        ];

        // Check if any child module has been revised (Pending with Pending approvals)
        foreach ($requiredModules as $module) {
            $moduleChild = $parent->children()->where('module', $module)->first();

            if ($moduleChild && $moduleChild->status === 'Pending') {
                $hasPendingApprovals = $moduleChild->approvals()
                    ->where('status', 'Pending')
                    ->exists();

                if ($hasPendingApprovals) {
                    // This module was revised, so parent shouldn't be in rejected list
                    return false;
                }
            }
        }

        // Check if any child module is currently rejected
        $hasRejectedModule = false;
        foreach ($requiredModules as $module) {
            $moduleChild = $parent->children()->where('module', $module)->first();

            if ($moduleChild) {
                $hasRejectedApproval = $moduleChild->approvals()
                    ->where('status', 'Rejected')
                    ->exists();

                if ($hasRejectedApproval || $moduleChild->status === 'Rejected') {
                    $hasRejectedModule = true;
                    break;
                }
            }
        }

        return $hasRejectedModule;
    }
}