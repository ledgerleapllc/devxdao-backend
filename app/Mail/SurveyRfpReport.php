<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class SurveyRfpReport extends Mailable
{
    use Queueable, SerializesModels;

    protected $survey;
    protected $bidResults;
    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($survey, $bidResults)
    {
        $this->survey = $survey;
        $this->bidResults = $bidResults;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        $id = $this->survey->id;
        return $this->view('emails.survey_rfp_report')
            ->subject("Survey $id is completed")
            ->with([
                'survey' => $this->survey,
                'bidResults' => $this->bidResults,
            ]);
    }
}
