<?php

namespace App\Console\Commands;

use App\FinalGrant;
use App\Mail\AdminReport;
use App\MilestoneReview;
use App\User;
use App\Vote;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class AdminTaskReport extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'admin:report';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Admin task report';

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
        $votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
            ->join('users', 'users.id', '=', 'proposal.user_id')
            ->where('vote.status', 'completed')
            ->where('vote.result', 'success')
            ->where('vote.type', 'informal')
            ->where('vote.content_type', '!=', 'grant')
            ->where('vote.formal_vote_id', 0)
            ->select([
                'proposal.id as proposalId',
                'proposal.type as proposalType',
                'proposal.title',
                'vote.*'
            ])
            ->orderBy('vote.updated_at', 'desc')
            ->get();
        $grants = FinalGrant::with(['proposal', 'proposal.user', 'proposal.milestones', 'user', 'signtureGrants'])
            ->where('final_grant.status', 'pending')
            ->has('proposal.milestones')
            ->has('user')
            ->orderBy('final_grant.id', 'desc')
            ->get();

        $milestoneReviews = MilestoneReview::where('milestone_review.status', 'pending')->with(['milestones'])
            ->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
            ->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
            ->join('users', 'proposal.user_id', '=', 'users.id')
            ->select([
                'milestone_review.milestone_id',
                'milestone_review.proposal_id',
                'milestone.*',
                'proposal.title as proposal_title',
                'users.id as user_id',
                'users.email'
            ])
            ->orderBy('milestone_review.created_at', 'desc')
            ->get();
        $admins = User::where('banned', 0)->where('is_admin', 1)->get();
        foreach($admins as $admin){
            Mail::to($admin->email)->send(new AdminReport($votes, $grants, $milestoneReviews));
        }
    }
}
