<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Mail\SurveyGrantReport;
use App\Mail\SurveyRfpReport;
use App\Proposal;
use App\Survey;
use App\SurveyDownVoteRank;
use App\SurveyDownVoteResult;
use App\SurveyRank;
use App\SurveyResult;
use App\SurveyRfpBid;
use App\SurveyRfpRank;
use App\SurveyRfpResult;
use App\User;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

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
                $this->sendEmailSurveyRfp($survey->id);
            } else {
                $this->processUpvote($survey);
                $this->processDownvote($survey);
                $this->sendEmailSurveyGrant($survey->id);
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

    public function sendEmailSurveyGrant($id)
    {
        $survey = Survey::where('id', $id)->with(['surveyRanks' => function ($q) {
            $q->orderBy('rank', 'desc');
        }])->with(['surveyRanks.proposal'])
            ->with(['surveyDownvoteRanks' => function ($q) {
                $q->orderBy('rank', 'desc');
            }])->with(['surveyDownvoteRanks.proposal'])
            ->first();
        // Record
        $proposals = Proposal::where('proposal.status', 'approved')
            ->doesntHave('votes')
            ->get();
        $results = SurveyResult::where('survey_id', $id)->get();
        foreach ($proposals as $proposal) {
            for ($i = 1; $i <= $survey->number_response; $i++) {
                $key = $i . '_place';
                $proposal->$key = null;
            }
            $total_vote = count($results->where("proposal_id", $proposal->id));
            $proposal->total_vote = $total_vote;
            if ($total_vote) {
                for ($i = 1; $i <= $survey->number_response; $i++) {
                    $key = $i . '_place';
                    $proposal->$key = count($results->where("proposal_id", $proposal->id)->where('place_choice', $i));
                }
            }
        }
        $proposalsUp = $proposals->sortBy('id')->values();

        $proposals = Proposal::where('proposal.status', 'approved')
            ->doesntHave('votes')
            ->get();
        $results = SurveyDownVoteResult::where('survey_id', $id)->get();
        foreach ($proposals as $proposal) {
            for ($i = 1; $i <= $survey->number_response; $i++) {
                $key = $i . '_place';
                $proposal->$key = null;
            }
            $total_vote = count($results->where("proposal_id", $proposal->id));
            $proposal->total_vote = $total_vote;
            if ($total_vote) {
                for ($i = 1; $i <= $survey->number_response; $i++) {
                    $key = $i . '_place';
                    $proposal->$key = count($results->where("proposal_id", $proposal->id)->where('place_choice', $i));
                }
            }
        }
        $proposalsDown = $proposals->sortBy('id')->values();
        // return view('emails.survey_grant_report', compact('survey', 'proposalsUp', 'proposalsDown'));
        $listVAs = User::where('is_member', 1)
            ->where('banned', 0)
            ->where('can_access', 1)
            ->get();
        foreach ($listVAs as $user) {
            try {
                Mail::to($user->email)->send(new SurveyGrantReport($survey, $proposalsUp, $proposalsDown));
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }

    public function sendEmailSurveyRfp($id)
    {
        $survey = Survey::where('id', $id)
            ->with(['surveyRfpRanks' => function ($q) {
                $q->orderBy('rank', 'desc');
            }])->with(['surveyRfpRanks.surveyRfpBid', 'surveyRfpBids'])
            ->first();
        if (!$survey) {
            return;
        }
        $bids = SurveyRfpBid::where('survey_rfp_bid.survey_id', $id)
            ->get();
        $results = SurveyRfpResult::where('survey_id', $id)->get();
        foreach ($bids as $bid) {
            for ($i = 1; $i <= $survey->number_response; $i++) {
                $key = $i . '_place';
                $bid->$key = null;
            }
            $total_vote = count($results->where("bid", $bid->bid));
            $bid->total_vote = $total_vote;
            if ($total_vote) {
                for ($i = 1; $i <= $survey->number_response; $i++) {
                    $key = $i . '_place';
                    $bid->$key = count($results->where("bid", $bid->bid)->where('place_choice', $i));
                }
            }
        }
        $bidResults = $bids->sortBy('id')->values();
        $listVAs = User::where('is_member', 1)
            ->where('banned', 0)
            ->where('can_access', 1)
            ->get();
        // return view('emails.survey_rfp_report', compact('survey', 'bidResults'));
        foreach ($listVAs as $user) {
            try {
                Mail::to($user->email)->send(new SurveyRfpReport($survey, $bidResults));
            } catch (Exception $e) {
                Log::error($e->getMessage());
            }
        }
    }
}
