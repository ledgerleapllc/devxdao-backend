<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class AdminReport extends Mailable
{
    use Queueable, SerializesModels;

    protected $votes;
    protected $grants;
    protected $milestoneReviews;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($votes, $grants, $milestoneReviews)
    {
        $this->votes = $votes;
        $this->grants = $grants;
        $this->milestoneReviews = $milestoneReviews;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {

        return $this->view('emails.admin_report')
            ->subject('Daily MVPR Admin Task Report')
            ->with([
                'votes' => $this->votes,
                'grants' => $this->grants,
                'milestoneReviews' => $this->milestoneReviews,
            ]);
    }
}
