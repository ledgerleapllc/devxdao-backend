<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class DailyReputation extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($subject, $name, $date, $path_csv)
    {
        $this->subject = $subject;
        $this->name = $name;
        $this->date = $date;
        $this->path_csv = $path_csv;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.daily_reputation')
            ->attach($this->path_csv)
            ->subject($this->subject)
            ->with([
                'name' => $this->name,
                'date' => $this->date,
                'path_csv' => $this->path_csv,
            ]);
    }
}
