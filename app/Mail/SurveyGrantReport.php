<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyGrantReport extends Mailable
{
    use Queueable, SerializesModels;

    protected $survey;
    protected $proposalsUp;
    protected $proposalsDown;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($survey, $proposalsUp, $proposalsDown)
    {
        $this->survey = $survey;
        $this->proposalsUp = $proposalsUp;
        $this->proposalsDown = $proposalsDown;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $id = $this->survey->id;
        return $this->view('emails.survey_grant_report')
            ->subject("Survey $id is completed")
            ->with([
                'survey' => $this->survey,
                'proposalsUp' => $this->proposalsUp,
                'proposalsDown' => $this->proposalsDown
            ]);
    }
}
