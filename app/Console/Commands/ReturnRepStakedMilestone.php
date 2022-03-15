<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Milestone;
use App\Profile;
use App\Proposal;
use App\Reputation;
use App\Vote;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class ReturnRepStakedMilestone extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'return:rep-milestone';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Return staked rep milestone';

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
        $proposalIds = Proposal::where('type', 'grant')->where('status', 'approved')->pluck('id');
        $votes = Vote::whereIn('proposal_id', $proposalIds)->where('type', 'formal')
            ->where('content_type', 'grant')->where('result', 'success')->get();
        foreach ($votes as $vote) {
            try {
                DB::beginTransaction();
                $proposal = Proposal::find($vote->proposal_id);
                $voteMilestones = Vote::where('proposal_id', $vote->proposal_id)->where('type', 'formal')->where('content_type', 'milestone')->where('result', 'success')->get();
                foreach ($voteMilestones as $voteMilestone) {
                    $milestone = Milestone::find($voteMilestone->milestone_id);
                    $milestonePosition = Helper::getPositionMilestone($milestone);
                    $milestone_extra = (float) $milestone->grant / $proposal->total_grant;
                    // return rep minted pending
                    $reputations = Reputation::where('vote_id', $vote->id)->where('type', 'Minted Pending')->get();
                    foreach ($reputations as $reputationInfo) {
                        $extraMintedFormalVote = $reputationInfo->pending * $milestone_extra;
                        $profile = Profile::find($reputationInfo->user_id);
                        $profile->rep_pending = (float) $profile->rep_pending - $extraMintedFormalVote;
                        if ((float) $profile->rep_pending < 0) {
                            $profile->rep_pending = 0;
                        }
                        $profile->save();
                        Helper::updateRepProfile($reputationInfo->user_id, $extraMintedFormalVote);
                        $reputation = new Reputation;
                        $reputation->user_id = $reputationInfo->user_id;
                        $reputation->proposal_id = $reputationInfo->proposal_id;
                        $reputation->vote_id = $reputationInfo->vote_id;
                        $reputation->value = $extraMintedFormalVote;
                        $reputation->event = "Return Minted Proposal $proposal->id milestone $milestonePosition";
                        $reputation->type = "Minted";
                        $reputation->return_type = "Return Minted";
                        $reputation->created_at = $voteMilestone->updated_at;
                        $reputation->save();
                        Helper::createRepHistory($reputationInfo->user_id, $extraMintedFormalVote, $profile->rep, 'Minted', 'Proposal Vote Result Minted Formal Vote', $proposal->id, null, 'return minted formal by milestone');
                    }
                }
                DB::commit();
            } catch (Exception $e) {
                DB::rollBack();
                Log::error("Return staked rep milestone in vote: $vote->id");
            }
        }
    }
}
