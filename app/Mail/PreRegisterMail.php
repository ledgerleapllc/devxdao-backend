<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class PreRegisterMail extends Mailable
{
    use Queueable, SerializesModels;

    protected $first_name = "";
    protected $last_name = "";
    protected $email = "";
    protected $interest = "";
    protected $qualifications = "";
    protected $technology = "";

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($first_name, $last_name, $email, $interest, $qualifications, $technology)
    {
        $this->first_name = $first_name;
        $this->last_name = $last_name;
        $this->email = $email;
        $this->interest = $interest;
        $this->qualifications = $qualifications;
        $this->technology = $technology;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.pre_register')->with([
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'email' => $this->email,
            'interest' => $this->interest,
            'qualifications' => $this->qualifications,
            'technology' => $this->technology
        ]);
    }
}
