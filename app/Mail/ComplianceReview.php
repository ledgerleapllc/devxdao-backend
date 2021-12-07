<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ComplianceReview extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $proposal, $public_link, $approve_link, $deny_link)
    {
        $this->subject = $subject;
        $this->proposal = $proposal;
        $this->public_link = $public_link;
        $this->approve_link = $approve_link;
        $this->deny_link = $deny_link;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        if (str_contains( url('/'), 'localhost')) {
            // test for local
            $linkPdf = 'https://backend.devxdao.com/storage/pdf/proposal/proposal_1.pdf';
        } else {
            $linkPdf = asset($this->proposal->pdf);
        }
        return $this->view('emails.compliance_review')
            ->attach($linkPdf)
            ->subject($this->subject)
            ->with([
                'proposal' => $this->proposal,
                'public_link' => $this->public_link,
                'approve_link' => $this->approve_link,
                'deny_link' => $this->deny_link,
            ]);
    }
}
