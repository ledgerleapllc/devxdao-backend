<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Proposal;
use App\Vote;
use Carbon\Carbon;
use Illuminate\Console\Command;

class VADailySummary extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'va:daily-summary';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'VA daily summary';

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
        $totalMembersInfomal = Helper::getTotalMembers();
        // Get Settings
        $settings = Helper::getSettings();
		$minsInformal = $minsSimple = $minsMileStone = $minsFormal = 0;

        if ($settings['time_unit_formal'] == 'min')
        $minsFormal = (int) $settings['time_formal'];
    else if ($settings['time_unit_formal'] == 'hour')
        $minsFormal = (int) $settings['time_formal'] * 60;
    else if ($settings['time_unit_formal'] == 'day')
        $minsFormal = (int) $settings['time_formal'] * 60 * 24;

		if ($settings['time_unit_informal'] == 'min')
			$minsInformal = (int) $settings['time_informal'];
		else if ($settings['time_unit_informal'] == 'hour')
			$minsInformal = (int) $settings['time_informal'] * 60;
		else if ($settings['time_unit_informal'] == 'day')
			$minsInformal = (int) $settings['time_informal'] * 60 * 24;

		if ($settings['time_unit_simple'] == 'min')
			$minsSimple = (int) $settings['time_simple'];
		else if ($settings['time_unit_simple'] == 'hour')
			$minsSimple = (int) $settings['time_simple'] * 60;
		else if ($settings['time_unit_simple'] == 'day')
			$minsSimple = (int) $settings['time_simple'] * 60 * 24;

		if ($settings['time_unit_milestone'] == 'min')
			$minsMileStone = (int) $settings['time_milestone'];
		else if ($settings['time_unit_milestone'] == 'hour')
			$minsMileStone = (int) $settings['time_milestone'] * 60;
		else if ($settings['time_unit_milestone'] == 'day')
			$minsMileStone = (int) $settings['time_milestone'] * 60 * 24;
        $tommorow = Carbon::now('UTC')->addDay();
        $today = Carbon::now('UTC');
        $yesterday = $today->subDay();
        $discussions = Proposal::has('user')
            ->where('status', 'approved')
            ->whereNotNull('approved_at')
            ->where('approved_at', '>=', $yesterday)
            ->get();
        $votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
            ->where('vote.created_at', '>=', $yesterday)
            ->select(['vote.id', 'vote.type', 'vote.content_type', 'proposal.title'])->get();

        $noQuorumVotes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
            ->where('vote.result', 'no-quorum')
            ->where('vote.updated_at', '>=', $yesterday)
            ->select(['vote.id', 'vote.type', 'vote.content_type', 'proposal.title', 'vote.updated_at', 'vote.created_at'])->get();
        $noQuorumVotes2 = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
            ->where('vote.status', 'active')
            ->select(['vote.*', 'proposal.title'])->get();
        foreach ($noQuorumVotes2 as $vote) {
            if ($vote->content_type == 'grant') {
                $quorumRate = (float) $settings['quorum_rate'];
                if ($vote->type == 'informal') {
                    $timeLeft = Carbon::parse($vote->created_at)->addMinute($minsInformal);
                } else {
                    $timeLeft = Carbon::parse($vote->created_at)->addMinute($minsFormal);
                }
            } else if ($vote->content_type == 'milestone') {
                $quorumRate = (float) $settings['quorum_rate_milestone'];
                $timeLeft = Carbon::parse($vote->created_at)->addMinute($minsMileStone);
            } else if ($vote->content_type == 'simple') {
                $quorumRate = (float) $settings['quorum_rate_simple'];
                $timeLeft = Carbon::parse($vote->created_at)->addMinute($minsSimple);
            } else if ($vote->content_type == 'admin-grant') {
                $quorumRate = (float) $settings['quorum_rate_simple'];
                $timeLeft = Carbon::parse($vote->created_at)->addMinute($minsSimple);
            }
            else if ($vote->content_type == 'advance-payment') {
                $quorumRate = (float) $settings['quorum_rate_simple'];
                $timeLeft = Carbon::parse($vote->created_at)->addMinute($minsSimple);
            }
            if ($vote->type == 'informal' && $timeLeft <= $tommorow) {
                $minMembers = $totalMembersInfomal * $quorumRate / 100;
                $minMembers = ceil($minMembers);

                if ($vote->result_count < $minMembers) {
                    $noQuorumVotes->push($vote);
                }
            } else if ($vote->type == 'formal'  && $timeLeft <= $tommorow) {
                $voteInfomal = Vote::where('proposal_id', $vote->proposal_id)->where('type', 'informal')
                ->where('content_type', $vote->content_type)->first();
                $totalMembers =  $voteInfomal->result_count ?? 0;
                $minMembers = $totalMembers * $quorumRate / 100;
                $minMembers = ceil($minMembers);
                if ($vote->result_count < $minMembers) {
                    $noQuorumVotes->push($vote);
                }
            }
        }
        $emailerData = Helper::getEmailerData();
        Helper::triggerMemberEmail('VA daily summary', $emailerData, null, null, $discussions, $votes, $noQuorumVotes);
    }
}
