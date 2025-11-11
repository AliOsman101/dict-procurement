<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class WinningSupplierMail extends Mailable
{
    use Queueable, SerializesModels;

    public $supplierName;
    public $procurementTitle;
    public $evaluationDetails; // <-- added

    public function __construct($supplierName, $procurementTitle, $evaluationDetails)
    {
        $this->supplierName = $supplierName;
        $this->procurementTitle = $procurementTitle;
        $this->evaluationDetails = $evaluationDetails; // <-- set
    }

    public function build()
    {
        return $this->subject('Congratulations! You Won the Quotation')
            ->view('emails.winning-supplier');
    }
}
