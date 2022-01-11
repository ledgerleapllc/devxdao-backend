<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Proposal;
use App\Reputation;
use App\User;
use Illuminate\Console\Command;

class ReturnRepForUsers extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'return-rep';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'return rep for users';

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
        $this->returnRepProposalDeleted();
        $this->returnRepUserDeleted();
    }

    public function returnRepProposalDeleted()
    {
        $proposalIds = Proposal::pluck('id')->all();
        $proposalDeletedIds = Reputation::whereNotIn('proposal_id', $proposalIds)
            ->whereNotNull('proposal_id')
            ->whereNotNull('vote_id')
            ->pluck('proposal_id')->all();
        $proposalDeletedIds = array_unique($proposalDeletedIds);
        $proposalReults = [];
        foreach ($proposalDeletedIds as $proposalId) {
            $checks = Reputation::where('proposal_id', $proposalId)->where('type', '!=', 'Staked')->count();
            if ($checks > 0) {
                continue; 
            }
            array_push($proposalReults, $proposalId);
        }
        foreach ($proposalReults as $proposalId) {
            Helper::returenRepProposalDeleted($proposalId);
        }
    }

    public function returnRepUserDeleted()
    {
        $userIds = User::pluck('id')->all();
        $proposalIds = Proposal::whereNotIn('user_id', $userIds)->pluck('id')->all();
        $proposalReults = [];
        foreach ($proposalIds as $proposalId) {
            $checks = Reputation::where('proposal_id', $proposalId)->where('type', '!=', 'Staked')->count();
            if ($checks > 0) {
                continue;
            }
            array_push($proposalReults, $proposalId);
        }
        foreach ($proposalReults as $proposalId) {
            Helper::returenRepProposalDeleted($proposalId);
        }
    }
}
