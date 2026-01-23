<?php

namespace App\Http\Controllers;

use App\Models\RfqDistribution;
use Illuminate\Support\Facades\Mail;
use App\Mail\SupplierReceivedRfqMail;

class RfqReceiveController extends Controller
{
    public function receive($id)
    {
        $distribution = RfqDistribution::findOrFail($id);

        // Mark as received
        $distribution->update([
            'received_at' => now(),
        ]);

        // Find who distributed the RFQ
        $procurement = $distribution->procurement; 
        $creator = $procurement->creator; // user who created the RFQ distribution

        if ($creator && $creator->email) {
            try {
                Mail::to($creator->email)->send(
                    new SupplierReceivedRfqMail($distribution)
                );
            } catch (\Exception $e) {
                \Log::error("Failed to send SupplierReceivedRfqMail: " . $e->getMessage());
            }
        }

        return view('emails.rfq.received-success');
    }
}
