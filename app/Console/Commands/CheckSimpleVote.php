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
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerAdmin;

use App\Http\Helper;

use Carbon\Carbon;

use App\Mail\AdminAlert;
use App\Mail\UserAlert;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class CheckSimpleVote extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'simplevote:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Simple Vote';

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

        // Get Settings
		$settings = Helper::getSettings();

        if ($start->lt($today) || $force) {
            $proposal = Proposal::find((int) $vote->proposal_id);
            if (!$proposal) return;

            $op = User::find($proposal->user_id);
            if (!$op) return;

            $emailerData = Helper::getEmailerData();

            if ($vote->result_count < $minMembers) {
                // Can't Proceed
                $vote->status = 'completed';
                $vote->result = 'no-quorum';
                $vote->save();

                // Emailer
                Helper::triggerAdminEmail('Vote Complete with No Quorum', $emailerData, $proposal, $vote);
                Helper::triggerUserEmail($op, 'Vote Recieved No Quorum', $emailerData, $proposal, $vote);
            } else {
                // Needs to complete
                $result = Helper::getVoteResult($proposal, $vote, $settings);

                $vote->status = 'completed';
                $vote->result = $result;
                $vote->save();

                if ($result == "success") {
                    if ($proposal->type == 'simple'
                        && ($settings['autostart_simple_formal_votes'] ?? null) == 'yes') {
                        Helper::startFormalVote($vote);
                    }

                    Helper::triggerUserEmail($op, 'Simple Vote Passed', $emailerData, $proposal, $vote);
                } else if ($result == "fail") {
                    Helper::triggerUserEmail($op, 'Simple Vote Failed', $emailerData, $proposal, $vote);
                }
            }
        }
    }

    public function checkFormal($settings, $mins, $vote, $force = false) {
        $quorumRate = (float) $settings['quorum_rate_simple'];
        $voteInfomal = Vote::where('proposal_id', $vote->proposal_id)->where('type', 'informal')
            ->where('content_type', 'simple')->first();
        $totalMembers =  $voteInfomal->result_count ?? 0;
        $minMembers = $totalMembers  * $quorumRate / 100;
        $minMembers = ceil($minMembers);
        $start = Carbon::createFromFormat("Y-m-d H:i:s", $vote->created_at, "UTC");
        $start->addMinutes($mins);
        $today = Carbon::now('UTC');

        if ($start->lt($today) || $force) {
            $proposal = Proposal::find((int) $vote->proposal_id);
            if (!$proposal) return;

            $op = User::find($proposal->user_id);
            if (!$op) return;

            $emailerData = Helper::getEmailerData();

            if ($vote->result_count < $minMembers) {
                // Can't Proceed
                $vote->status = 'completed';
                $vote->result = 'no-quorum';
                $vote->save();

                Helper::clearVoters($vote);

                // Emailer
                Helper::triggerAdminEmail('Vote Complete with No Quorum', $emailerData, $proposal, $vote);
                Helper::triggerUserEmail($op, 'Vote Recieved No Quorum', $emailerData, $proposal, $vote);
            } else {
                // Needs to complete
                $result = Helper::getVoteResult($proposal, $vote, $settings);

                $vote->status = 'completed';
                $vote->result = $result;
                $vote->save();

                if ($result == "success") {
                    Helper::runWinnerFlow($proposal, $vote, $settings);
                    Helper::completeProposal($proposal);

                    Helper::triggerUserEmail($op, 'Simple Vote Passed', $emailerData, $proposal, $vote);
                } else {
                    Helper::runLoserFlow($proposal, $vote, $settings);

                    Helper::triggerUserEmail($op, 'Simple Vote Failed', $emailerData, $proposal, $vote);
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
            !$settings['time_simple'] ||
            !$settings['time_unit_simple'] ||
            !$settings['pass_rate_simple'] ||
            !$settings['quorum_rate_simple']
        )
            return;

        $mins = 0;
        if ($settings['time_unit_simple'] == 'min')
            $mins = (int) $settings['time_simple'];
        else if ($settings['time_unit_simple'] == 'hour')
            $mins = (int) $settings['time_simple'] * 60;
        else if ($settings['time_unit_simple'] == 'day')
            $mins = (int) $settings['time_simple'] * 60 * 24;

        // Calculate Min Members
        $totalMembers = Helper::getTotalMembers();
        $quorumRate = (float) $settings['quorum_rate_simple'];

        $minMembers = $totalMembers * $quorumRate / 100;
        $minMembers = ceil($minMembers);

        // Vote Records
        $informals = Vote::has('proposal')
                        ->where('content_type', 'simple')
                        ->where('type', 'informal')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->limit(15)
                        ->get();

        $formals = Vote::has('proposal')
                        ->where('content_type', 'simple')
                        ->where('type', 'formal')
                        ->where('status', 'active')
                        ->orderBy('created_at', 'asc')
                        ->limit(15)
                        ->get();

        if ($informals) {
            foreach ($informals as $informal) {
                try {
                    DB::beginTransaction();
                    $this->checkInformal($settings, $mins, $minMembers, $informal, $force);
                    DB::commit();
                } catch (\Exception $ex) {
                    DB::rollBack();
                    Log::error($ex->getMessage());
                }
            }
        }

        if ($formals) {
            foreach ($formals as $formal) {
                try {
                    DB::beginTransaction();
                    $this->checkFormal($settings, $mins, $formal, $force);
                    DB::commit();
                } catch (\Exception $ex) {
                    DB::rollBack();
                    Log::error($ex->getMessage());
                }
            }
        }
    }
}
