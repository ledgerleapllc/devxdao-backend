<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Survey;
use App\SurveyDownVoteRank;
use App\SurveyDownVoteResult;
use App\SurveyRank;
use App\SurveyResult;
use App\SurveyRfpBid;
use App\SurveyRfpRank;
use App\SurveyRfpResult;
use Illuminate\Console\Command;

class CheckSurvey extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'survey:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Survey check';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $surveys = Survey::where('status', 'active')->where('end_time', '<=', now())->get();

        foreach ($surveys as $survey) {
            if ($survey->type == 'rfp') {
                $this->processRfpSurvey($survey);
            } else {
                $this->processUpvote($survey);
                $this->processDownvote($survey);
            }
            $survey->status = 'completed';
            $survey->save();
        }
    }
    public function processUpvote($survey)
    {
        $reults = SurveyResult::where('survey_id', $survey->id)->get();
        $surey_grouped = $reults->groupBy('proposal_id');
        $responses = collect();
        foreach ($surey_grouped as $key => $value) {
            $total_point = $value->sum('point');
            $responses->push([
                'proposal_id' => $key,
                'total_point' => $total_point
            ]);
        }
        $sorted = $responses->sortByDesc('total_point');
        $rank = 1;
        foreach ($sorted as $response) {
            if ($rank == 1) {
                $survey->proposal_win =  $response['proposal_id'];
                $survey->save();
            }
            $survey_rank = new SurveyRank();
            $survey_rank->survey_id = $survey->id;
            $survey_rank->proposal_id =  $response['proposal_id'];
            $survey_rank->total_point =   $response['total_point'];
            $survey_rank->rank =  $rank;
            $survey_rank->is_winner = $rank <= $survey->number_response ? 1 : 0;
            $survey_rank->save();
            if ($rank <= $survey->number_response) {
                Helper::createGrantTracking($response['proposal_id'], "Passed survey in spot $rank", 'passed_survey_spot');
            }
            $rank += 1;
        }
    }

    public function processDownvote($survey)
    {
        $reults = SurveyDownVoteResult::where('survey_id', $survey->id)->get();
        $surey_grouped = $reults->groupBy('proposal_id');
        $responses = collect();
        foreach ($surey_grouped as $key => $value) {
            $total_point = $value->sum('point');
            $responses->push([
                'proposal_id' => $key,
                'total_point' => $total_point
            ]);
        }
        $sorted = $responses->sortByDesc('total_point');
        $rank = 1;
        foreach ($sorted as $response) {
            if ($rank == 1) {
                $survey->proposal_win =  $response['proposal_id'];
                $survey->save();
            }
            $survey_rank = new SurveyDownVoteRank();
            $survey_rank->survey_id = $survey->id;
            $survey_rank->proposal_id =  $response['proposal_id'];
            $survey_rank->total_point =   $response['total_point'];
            $survey_rank->rank =  $rank;
            $survey_rank->is_winner = $rank <= $survey->number_response ? 1 : 0;
            $survey_rank->save();
            $rank += 1;
        }
    }

    public function processRfpSurvey($survey)
    {
        $reults = SurveyRfpResult::where('survey_id', $survey->id)->get();
        $surey_grouped = $reults->groupBy('bid');
        $responses = collect();
        foreach ($surey_grouped as $key => $value) {
            $total_point = $value->sum('point');
            $responses->push([
                'bid' => $key,
                'total_point' => $total_point
            ]);
        }
        $sorted = $responses->sortByDesc('total_point');
        $rank = 1;
        foreach ($sorted as $response) {
            $survey_rank = new SurveyRfpRank();
            if ($rank == 1) {
                $survey_rank->is_winner = 1;
            }
            $bid = SurveyRfpBid::where('survey_id', $survey->id)->where('bid', $response['bid'])->first();
			$survey_rank->bid_id = $bid->id;
            $survey_rank->survey_id = $survey->id;
            $survey_rank->bid =  $response['bid'];
            $survey_rank->total_point =   $response['total_point'];
            $survey_rank->rank =  $rank;
            $survey_rank->save();
            $rank += 1;
        }
    }
}
