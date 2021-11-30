<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

use App\User;
use App\Vote;
use App\VoteResult;
use App\Setting;
use App\Proposal;
use App\Reputation;
use App\OnBoarding;
use App\FinalGrant;
use App\Milestone;
use App\Invoice;
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerAdmin;

use App\Http\Helper;

use Carbon\Carbon;

use App\Mail\AdminAlert;
use App\Mail\UserAlert;

class CheckMilestoneVote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'milestonevote:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Milestone Vote';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function checkInformal($settings, $mins, $minMembers, $vote, $force = false) {
        $start = Carbon::createFromFormat("Y-m-d H:i:s", $vote->created_at, "UTC");
        $start->addMinutes($mins);
        $today = Carbon::now('UTC');

        if ($start->lt($today) || $force) {
            $proposal = Proposal::find((int) $vote->proposal_id);
            if (!$proposal) return;
            $milestone = Milestone::find($vote->milestone_id);
            if (!$milestone) return;
            $op = User::find($proposal->user_id);
            if (!$op) return;

            $emailerData = Helper::getEmailerData();

            if ($vote->result_count < $minMembers) {
                // Can't Proceed
                $vote->status = 'completed';
                $vote->result = 'no-quorum';
                $vote->save();
                Helper::createMilestoneLog($vote->milestone_id, null, null, 'System', 'Vote failed to get quorum');
                // Emailer
                Helper::triggerAdminEmail('Vote Complete with No Quorum', $emailerData, $proposal, $vote);
                Helper::triggerUserEmail($op, 'Vote Recieved No Quorum', $emailerData, $proposal, $vote);
            } else {
                // Needs to Complete
                $result = Helper::getVoteResult($proposal, $vote, $settings);

                $vote->status = 'completed';
                $vote->result = $result;
                $vote->save();

                // Emailer
                if ($result == "success") {
                    Helper::createMilestoneLog($vote->milestone_id, null, null, 'System', 'Vote passed');
                    $milestonePosition = Helper::getPositionMilestone($milestone);
					Helper::createGrantTracking($milestone->proposal_id, "Milestone $milestonePosition passed informal vote", "milestone_" . $milestonePosition ."_passed_informal_vote");
                    Helper::triggerUserEmail($op, 'Milestone Vote Passed Informal', $emailerData, $proposal, $vote);
                } else if ($result == "fail") {
                    Helper::createMilestoneLog($vote->milestone_id, null, null, 'System', 'Vote failed');
                    Helper::triggerUserEmail($op, 'Milestone Vote Failed', $emailerData, $proposal, $vote);
                }
            }
        }
    }

    public function checkFormal($settings, $mins, $minMembers, $vote, $force = false) {
        $quorumRate = (float) $settings['quorum_rate_milestone'];
        $start = Carbon::createFromFormat("Y-m-d H:i:s", $vote->created_at, "UTC");
        $start->addMinutes($mins);
        $today = Carbon::now('UTC');
        $voteInfomal = Vote::where('proposal_id', $vote->proposal_id)->where('type', 'informal')
            ->where('content_type', 'milestone')->first(); 
        $totalMembers =  $voteInfomal->result_count ?? 0;
        $minMembers = $totalMembers  * $quorumRate / 100;
        $minMembers = ceil($minMembers);
        if ($start->lt($today) || $force) {
            $proposal = Proposal::find((int) $vote->proposal_id);
            $op = User::find($proposal->user_id);

            if (!$proposal || !$op) return;
            $milestone = Milestone::find($vote->milestone_id);
            if (!$milestone) return;
            $emailerData = Helper::getEmailerData();
            
            if ($vote->result_count < $minMembers) {
                // Can't Proceed
                $vote->status = 'completed';
                $vote->result = 'no-quorum';
                $vote->save();
                Helper::createMilestoneLog($vote->milestone_id, null, null, 'System', 'Vote failed to get quorum');
                // Emailer
                Helper::triggerAdminEmail('Vote Complete with No Quorum', $emailerData, $proposal, $vote);
                Helper::triggerUserEmail($op, 'Vote Recieved No Quorum', $emailerData, $proposal, $vote);
            } else {
                // Needs to Complete
                $result = Helper::getVoteResult($proposal, $vote, $settings);
                
                $vote->status = 'completed';
                $vote->result = $result;
                $vote->save();

                if ($result == "success") {
                    Helper::triggerUserEmail($op, 'Milestone Vote Passed Formal', $emailerData, $proposal, $vote);
                    $milestonePosition = Helper::getPositionMilestone($milestone);
					Helper::createGrantTracking($proposal->id, "Milestone $milestonePosition passed formal vote",  "milestone_" . $milestonePosition ."_passed_formal_vote");
                    Helper::runWinnerFlow($proposal, $vote, $settings);

                    $finalGrant = FinalGrant::where('proposal_id', $proposal->id)
                                            ->where('status', 'active')
                                            ->first();
                    Helper::createMilestoneLog($vote->milestone_id, null, null, 'System', 'Vote passed');
                    if ($finalGrant) {
                        $finalGrant->milestones_complete = (int) $finalGrant->milestones_complete + 1;
                        if (
                            (int) $finalGrant->milestones_complete == (int) $finalGrant->milestones_total
                        ) {
                            Helper::triggerUserEmail($op, 'All Milestones Complete', $emailerData, $proposal, $vote);

                            $finalGrant->status = "completed";
                            Helper::completeProposal($proposal);
                            // if ($op->hasRole('member'))
                            //     Helper::completeProposal($proposal);
                            // else
                            //     Helper::sendMembershipHellosign($op, $proposal, $settings);
                        }
                        $finalGrant->save();
                        $invoice = new Invoice();
                        $invoice->code = "$proposal->id-$milestonePosition";
                        $invoice->proposal_id = $proposal->id;
                        $invoice->milestone_id = $milestone->id;
                        $invoice->payee_id = $op->id;
                        $invoice->payee_email = $op->email;
                        $invoice->sent_at = now();
                        $invoice->save();
                    }
                } else {
                    Helper::createMilestoneLog($vote->milestone_id, null, null, 'System', 'Vote failed');
                    Helper::triggerUserEmail($op, 'Milestone Vote Failed', $emailerData, $proposal, $vote);
                    Helper::runLoserFlow($proposal, $vote, $settings);
                }
            }
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Force Mode
        $force = isset($_SERVER['APP_URL']) && $_SERVER['APP_URL'] == "http://localhost" ? true : false;

        // Get Settings
        $settings = Helper::getSettings();

        // Calculate Period
        if (
            !$settings || 
            !$settings['time_milestone'] || 
            !$settings['time_unit_milestone'] ||
            !$settings['pass_rate_milestone'] ||
            !$settings['quorum_rate_milestone']
        )
            return;

        $mins = 0;
        if ($settings['time_unit_milestone'] == 'min')
            $mins = (int) $settings['time_milestone'];
        else if ($settings['time_unit_milestone'] == 'hour')
            $mins = (int) $settings['time_milestone'] * 60;
        else if ($settings['time_unit_milestone'] == 'day')
            $mins = (int) $settings['time_milestone'] * 60 * 24;

        // Calculate Min Members
        $totalMembers = Helper::getTotalMembers();
        $quorumRate = (float) $settings['quorum_rate_milestone'];
        
        $minMembers = $totalMembers * $quorumRate / 100;
        $minMembers = ceil($minMembers);

        // Vote Records
        $informals = Vote::has('proposal')
                        ->where('content_type', 'milestone')
                        ->where('type', 'informal')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->limit(15)
                        ->get();

        $formals = Vote::has('proposal')
                        ->where('content_type', 'milestone')
                        ->where('type', 'formal')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->limit(15)
                        ->get();

        if ($informals) {
            foreach ($informals as $informal) {
                $this->checkInformal($settings, $mins, $minMembers, $informal, $force);
            }
        }

        if ($formals) {
            foreach ($formals as $formal) {
                $this->checkFormal($settings, $mins, $minMembers, $formal, $force);
            }
        }
    }
}
