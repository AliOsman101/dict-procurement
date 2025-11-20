<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class LosingSupplierMail extends Mailable
{
    use Queueable, SerializesModels;

    public $supplierName;
    public $procurementTitle;
    public $allEvaluationDetails; // contains all bidders

    public function __construct($supplierName, $procurementTitle, $allEvaluationDetails)
    {
        $this->supplierName = $supplierName;
        $this->procurementTitle = $procurementTitle;
        $this->allEvaluationDetails = $allEvaluationDetails;
    }

    public function build()
    {
        return $this->subject('Quotation Result Notification – Transparent Evaluation')
                    ->view('emails.transparent-supplier');
    }
}
