<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class HelpRequest extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */

    protected $emailAddress = "";
    protected $text = "";

    public function __construct($emailAddress, $text)
    {
        $this->emailAddress = $emailAddress;
        $this->text = $text;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.help')->with([
            'emailAddress' => $this->emailAddress,
            'text' => $this->text,
        ]);
    }
}
