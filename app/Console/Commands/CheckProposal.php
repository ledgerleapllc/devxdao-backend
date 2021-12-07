<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

use App\User;
use App\Vote;
use App\VoteResult;
use App\Setting;
use App\Proposal;
use App\OnBoarding;
use App\EmailerTriggerAdmin;
use App\EmailerAdmin;
use App\ProposalChange;

use App\Http\Helper;

use Carbon\Carbon;
use Carbon\CarbonImmutable;

use App\Mail\AdminAlert;

class CheckProposal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'proposal:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Proposals';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    public function checkInformal($proposal, $emailerData) {
        $pendingCount = ProposalChange::where('proposal_id', $proposal->id)
                                        ->where('status', 'pending')
                                        ->get()
                                        ->count();
        $extra['pendingChangesCount'] = $pendingCount;
        Helper::triggerUserEmail($proposal->user, 'Vote Ready to Start', $emailerData, $proposal, null, null, $extra);
        
        $proposal->informal_vote_ready_sent = 1;
        $proposal->save();
    }

    public function checkFormal($proposal, $emailerData) {
        Helper::triggerUserEmail($proposal->user, 'Grant Ready for Formal Vote', $emailerData, $proposal);
        $proposal->formal_vote_ready_sent = 1;
        $proposal->save();
    }

    public function checkGrant($proposal, $emailerData) {
        if (count($emailerData['admins'] ?? [])) {
            $proposal->grant_ready_sent = 1;
            $proposal->save();
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Get Settings
        $settings = [];
        $items = Setting::get();
        if ($items) {
            foreach ($items as $item) {
                $settings[$item->name] = $item->value;
            }
        }

        $timestamp = CarbonImmutable::now('UTC');
        $emailerData = Helper::getEmailerData();

        // Informal Vote Ready Check
        if ($settings['can_op_start_informal'] == "yes") {
            $mins = 0;
            if ($settings['time_unit_before_op_informal'] == 'min')
                $mins = (int) $settings['time_before_op_informal'];
            else if ($settings['time_unit_before_op_informal'] == 'hour')
                $mins = (int) $settings['time_before_op_informal'] * 60;
            else if ($settings['time_unit_before_op_informal'] == 'day')
                $mins = (int) $settings['time_before_op_informal'] * 60 * 24;

            $datetime = $timestamp->subMinutes($mins)->format("Y-m-d H:i:s");

            $proposals = Proposal::with(['user'])
                                    ->has('user')
                                    /*
                                    ->whereHas('changes', function ($query) {
                                        $query->where('proposal_change.status', 'pending');
                                    })
                                    */
                                    ->where('status', 'approved')
                                    ->doesntHave('votes')
                                    ->whereNotNull('approved_at')
                                    ->where('approved_at', '<', $datetime)
                                    ->where('informal_vote_ready_sent', 0)
                                    ->limit(15)
                                    ->get();

            
            if ($proposals) {
                foreach ($proposals as $proposal) {
                    $this->checkInformal($proposal, $emailerData);
                }
            }
        }

        // Formal Vote Ready Check
        $votes = Vote::with(['proposal'])
                        ->has('proposal.user')
                        ->whereHas('proposal.user.shuftipro', function ($query) {
                            $query->where('shuftipro.status', 'approved');
                        })
                        ->whereHas('proposal', function ($query) {
                            $query->where('proposal.formal_vote_ready_sent', 0)
                                    ->where('proposal.form_submitted', 1);
                        })
                        ->whereDoesntHave('proposal.signatures', function ($query) {
                            $query->where('signature.signed', 0);
                        })
                        ->where('type', 'informal')
                        ->where('status', 'completed')
                        ->where('formal_vote_id', 0)
                        ->limit(15)
                        ->get();

        if ($votes) {
            foreach ($votes as $vote) {
                $this->checkFormal($vote->proposal, $emailerData);
            }
        }

        // Grant Activation Ready Check
        /*
        $proposals = Proposal::with('user')
                                ->whereHas('user.shuftipro', function ($query) {
                                    $query->where('shuftipro.status', 'approved');
                                })
                                ->whereHas('onboarding', function ($query) {
                                    $query->where('onboarding.status', 'pending');
                                })
                                ->where('status', 'approved')
                                ->where('form_submitted', 1)
                                ->where('grant_ready_sent', 0)
                                ->limit(15)
                                ->get();

        if ($proposals) {
            foreach ($proposals as $proposal) {
                $this->checkGrant($proposal, $emailerData);
            }
        }
        */

        return 0;
    }
}
