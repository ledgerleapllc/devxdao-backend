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
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerAdmin;
use Illuminate\Support\Str;

use App\Http\Helper;

use Carbon\Carbon;

use App\Mail\AdminAlert;
use App\Mail\ComplianceReview;
use App\Mail\UserAlert;
use App\Shuftipro;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckVote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'vote:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Vote';

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
        // Get Settings
		$settings = Helper::getSettings();
        $start = Carbon::createFromFormat("Y-m-d H:i:s", $vote->created_at, "UTC");
        $start->addMinutes($mins);
        $today = Carbon::now('UTC');

        if ($start->lt($today) || $force) {
            $proposal = Proposal::find((int) $vote->proposal_id);
            if (!$proposal) return;

            $op = User::find($proposal->user_id);
            if (!$op) return;

            if ($vote->result_count < $minMembers) {
                // Can't Proceed
                $vote->status = 'completed';
                $vote->result = 'no-quorum';
                $vote->save();

                // Emailer
                $emailerData = Helper::getEmailerData();
                Helper::triggerAdminEmail('Vote Complete with No Quorum', $emailerData, $proposal, $vote);
                Helper::triggerUserEmail($op, 'Vote Recieved No Quorum', $emailerData, $proposal, $vote);
            } else {
                // Needs to complete
                $result = Helper::getVoteResult($proposal, $vote, $settings);

                $vote->status = 'completed';
                $vote->result = $result;
                $vote->save();

                $emailerData = Helper::getEmailerData();

                if ($result == "success") {
                    Helper::createGrantTracking($proposal->id, "Informal vote passed", 'informal_vote_passed');
                    $shuftipro = Shuftipro::where('user_id', $proposal->user_id)->where('status', 'approved')->first();
                    if ($shuftipro) {
                        Helper::createGrantTracking($proposal->id, "KYC checks complete", 'kyc_checks_complete');
                    }
                    Helper::startOnboarding($proposal, $vote);
                    Helper::sendKycKangarooUser($op);
                    // Emailer
                    // Helper::triggerAdminEmail('Signatures Needed', $emailerData, $proposal);
                    // Helper::triggerUserEmail($op, 'Passed Informal Grant Vote', $emailerData, $proposal, $vote);
                    // Onboarding Begins Automatically
                    // if ( $settings['autostart_formal_votes'] == 'yes' ){
                        //send mail complance admin
                    // }
                    // else {
                    //     Helper::startOnboarding($proposal, $vote);
                    // }
                } else {
                    Helper::triggerUserEmail($op, 'Failed Informal Grant Vote', $emailerData, $proposal, $vote);
                }
            }
        }
    }

    public function checkFormal($settings, $mins, $vote, $force = false) {
        $quorumRate = (float) $settings['quorum_rate'];
        $voteInfomal = Vote::where('proposal_id', $vote->proposal_id)->where('type', 'informal')
            ->where('content_type', 'grant')->first();
        $totalMembers =  $voteInfomal->result_count ?? 0;
        $minMembers = $totalMembers  * $quorumRate / 100;
        $minMembers = ceil($minMembers);
        $start = Carbon::createFromFormat("Y-m-d H:i:s", $vote->created_at, "UTC");
        $start->addMinutes($mins);
        $today = Carbon::now('UTC');

        if ($start->lt($today) || $force) {
            $proposal = Proposal::with('milestones')
                                ->has('milestones')
                                ->where('id', $vote->proposal_id)
                                ->first();
            if (!$proposal) return false;

            $op = User::find($proposal->user_id);
            if (!$op) return;

            if ($vote->result_count < $minMembers) {
                // Can't Proceed
                $vote->status = 'completed';
                $vote->result = 'no-quorum';
                $vote->save();

                Helper::clearVoters($vote);

                // Emailer
                $emailerData = Helper::getEmailerData();
                Helper::triggerAdminEmail('Vote Complete with No Quorum', $emailerData, $proposal, $vote);
                Helper::triggerUserEmail($op, 'Vote Recieved No Quorum', $emailerData, $proposal, $vote);
            } else {
                // Needs to complete
                $result = Helper::getVoteResult($proposal, $vote, $settings);

                $vote->status = 'completed';
                $vote->result = $result;
                $vote->save();

                $emailerData = Helper::getEmailerData();

                if ($result == "success") {
                    // Emailer
                    Helper::triggerAdminEmail('Formal Vote Passed', $emailerData, $proposal, $vote);
                    Helper::triggerUserEmail($op, 'Formal Grant Vote Passed', $emailerData, $proposal, $vote);

                    Helper::runWinnerFlow($proposal, $vote, $settings);
                    Helper::startFinalGrant($proposal);
                    $op = User::find($proposal->user_id);
                    Helper::sendGrantHellosign($op, $proposal, $settings);
                    Helper::createGrantTracking($proposal->id, "Passed Formal vote", 'passed_formal_vote');
                } else {
                    // Emailer
                    Helper::triggerUserEmail($op, 'Formal Grant Vote Failed', $emailerData, $proposal, $vote);

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
            !$settings['time_informal'] ||
            !$settings['time_unit_informal'] ||
            !$settings['time_formal'] ||
            !$settings['time_unit_formal'] ||
            !$settings['pass_rate'] ||
            !$settings['quorum_rate']
        )
            return;

        $minsInformal = $minsFormal = 0;
        if ($settings['time_unit_informal'] == 'min')
            $minsInformal = (int) $settings['time_informal'];
        else if ($settings['time_unit_informal'] == 'hour')
            $minsInformal = (int) $settings['time_informal'] * 60;
        else if ($settings['time_unit_informal'] == 'day')
            $minsInformal = (int) $settings['time_informal'] * 60 * 24;

        if ($settings['time_unit_formal'] == 'min')
            $minsFormal = (int) $settings['time_formal'];
        else if ($settings['time_unit_formal'] == 'hour')
            $minsFormal = (int) $settings['time_formal'] * 60;
        else if ($settings['time_unit_formal'] == 'day')
            $minsFormal = (int) $settings['time_formal'] * 60 * 24;

        // Calculate Min Members
        $totalMembers = Helper::getTotalMembers();
        $quorumRate = (float) $settings['quorum_rate'];

        $minMembers = $totalMembers * $quorumRate / 100;
        $minMembers = ceil($minMembers);

        // Vote Records
        $informals = Vote::has('proposal')
                        ->where('content_type', 'grant')
                        ->where('type', 'informal')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->limit(15)
                        ->get();

        $formals = Vote::has('proposal')
                        ->where('content_type', 'grant')
                        ->where('type', 'formal')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->limit(15)
                        ->get();

        if ($informals) {
            foreach ($informals as $informal) {
                try {
                    $this->checkInformal($settings, $minsInformal, $minMembers, $informal, $force);
                } catch (\Exception $ex) {
                    Log::error($ex->getMessage());
                }
            }
        }

        if ($formals) {
            foreach ($formals as $formal) {
                try {
                    $this->checkFormal($settings, $minsFormal, $formal, $force);
                } catch (\Exception $ex) {
                    Log::error($ex->getMessage());
                }
            }
        }
    }
}
