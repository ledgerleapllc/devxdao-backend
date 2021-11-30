<?php

namespace App\Console\Commands;

use App\FinalGrant;
use App\GrantTracking;
use App\Http\Helper;
use App\Milestone;
use App\MilestoneReview;
use App\OnBoarding;
use App\Proposal;
use App\Shuftipro;
use App\SurveyRank;
use App\Vote;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class MigrateProposalGrantTracking extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proposal:grant-tracking';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Migrate proposal grant tracking timeline';

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
        Proposal::chunk(50, function ($proposals) {
            foreach ($proposals as $proposal) {
                try {
                    $this->saveGrantTracking($proposal->id, "Proposal $proposal->id submitted", 'proposal_submitted', $proposal->created_at);
                    if ($proposal->status == 'payment' || $proposal->status == 'approved' || $proposal->status == 'completed') {
                        $this->saveGrantTracking($proposal->id, "Approved by admin", 'approved_by_admin', $proposal->created_at);
                    }
                    if ($proposal->status == 'approved' || $proposal->status == 'completed') {
                        $this->saveGrantTracking($proposal->id, "Entered discussion phase", 'discussion_phase', $proposal->approved_at);
                    }
                    $survey_rank = SurveyRank::where('proposal_id', $proposal->id)->where('is_winner', 1)->first();
                    if ($survey_rank) {
                        $this->saveGrantTracking($proposal->id, "Passed survey in spot $survey_rank->rank", 'passed_survey_spot', $survey_rank->created_at);
                    }
                    $informal_vote = Vote::where('proposal_id', $proposal->id)->where('type', 'informal')->orderBy('created_at', 'asc')->first();
                    if ($informal_vote) {
                        $this->saveGrantTracking($proposal->id, "Informal vote started", 'informal_vote_started',  $informal_vote->created_at);
                        $onboarding = OnBoarding::where('vote_id', $informal_vote->id)->first();
                        if ($onboarding) {
                            $this->saveGrantTracking($proposal->id, "Informal vote passed", 'informal_vote_passed', $onboarding->created_at);
                        }
                    }
                    $onboarding = OnBoarding::where('proposal_id', $proposal->id)->where('status', 'completed')->first();
                    if ($onboarding) {
                        $this->saveGrantTracking($proposal->id, "ETA compliance complete", 'eta_compliance_complete', $onboarding->updated_at);
                    }
                    $shuftipro = Shuftipro::where('user_id', $proposal->user_id)->where('status', 'approved')->first();
                    if ($shuftipro) {
                        $this->saveGrantTracking($proposal->id, "KYC checks complete", 'kyc_checks_complete', $shuftipro->created_at);
                    }
                    $formal_vote = Vote::where('proposal_id', $proposal->id)->where('type', 'formal')->orderBy('created_at', 'asc')->first();
                    if ($formal_vote) {
                        $this->saveGrantTracking($proposal->id, "Entered Formal vote", 'entered_formal_vote', $formal_vote->created_at);
                        if ($formal_vote->result == 'success') {
                            $this->saveGrantTracking($proposal->id, "Passed Formal vote", 'passed_formal_vote', $formal_vote->updated_at);
                        }
                    }
                    $finalGrant = FinalGrant::where('proposal_id', $proposal->id)->whereIn('status', ['active', 'completed'])->first();
                    if ($finalGrant) {
                        $this->saveGrantTracking($proposal->id, 'Grant activated by ETA', 'grant_activated', $finalGrant->created_at);
                    }
                    $milestones = Milestone::where('proposal_id', $proposal->id)->orderBy('id', 'asc')->get();

                    foreach ($milestones as $milestone) {
                        $milestonePosition = Helper::getPositionMilestone($milestone);
                        if ($milestone->submitted_time) {
                            $this->saveGrantTracking($proposal->id, "Milestone $milestonePosition submitted", 'milestone_' . $milestonePosition . '_submitted', $milestone->submitted_time);
                        }
                        $milestone_review = MilestoneReview::where('milestone_id', $milestone->id)->where('status', 'approved')->first();
                        if ($milestone_review) {
                            $this->saveGrantTracking($proposal->id, "Milestone $milestonePosition approved by CRDAO", 'milestone_' . $milestonePosition . '_approved_crdao', $milestone_review->reviewed_at);
                            $this->saveGrantTracking($proposal->id, "Milestone $milestonePosition approved by Proj. Mngmt.", 'milestone_' . $milestonePosition . '_approved_proj', $milestone_review->reviewed_at);
                        }
                        $milestone_informal_vote =  Vote::where('proposal_id', $proposal->id)->where('type', 'informal')->where('milestone_id', $milestone->id)->orderBy('created_at', 'asc')->first();
                        if ($milestone_informal_vote) {
                            $this->saveGrantTracking($milestone->proposal_id, "Milestone $milestonePosition started informal vote", 'milestone_' . $milestonePosition . '_started_informal_vote', $milestone_informal_vote->created_at);
                            if ($milestone_informal_vote->result == 'success') {
                                $this->saveGrantTracking($milestone->proposal_id, "Milestone $milestonePosition passed informal vote", "milestone_" . $milestonePosition . "_passed_informal_vote", $milestone_informal_vote->updated_at);
                            }
                        }

                        $milestone_formal_vote =  Vote::where('proposal_id', $proposal->id)->where('type', 'formal')->where('milestone_id', $milestone->id)->orderBy('created_at', 'asc')->first();
                        if ($milestone_formal_vote) {
                            $this->saveGrantTracking($milestone->proposal_id, "Milestone $milestonePosition started formal vote", 'milestone_' . $milestonePosition . '_started_formal_vote', $milestone_formal_vote->created_at);
                            if ($milestone_formal_vote->result == 'success') {
                                $this->saveGrantTracking($milestone->proposal_id, "Milestone $milestonePosition passed formal vote", "milestone_" . $milestonePosition . "_passed_formal_vote", $milestone_formal_vote->updated_at);
                            }
                        }
                    }
                    if ($proposal->status == 'completed') {
                        $this->saveGrantTracking($proposal->id, 'Grant 100% complete', 'grant_completed', $proposal->updated_at);
                    }
                } catch (Exception $e) {
                    Log::error($e->getMessage());
                }
            }
        });
    }
    public function saveGrantTracking($proposal_id, $event, $key, $created_at)
    {
        $grantTracking = GrantTracking::where('proposal_id', $proposal_id)->where('key', $key)->first();
        if ($grantTracking) {
            $grantTracking->created_at = $created_at;
            $grantTracking->save();
            return;
        }
        $grantTracking = new GrantTracking();
        $grantTracking->proposal_id = $proposal_id;
        $grantTracking->event = $event;
        $grantTracking->key = $key;
        $grantTracking->save();
        return;
    }
}
