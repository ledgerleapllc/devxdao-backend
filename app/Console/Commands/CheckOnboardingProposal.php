<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Proposal;
use App\Vote;
use Illuminate\Console\Command;

class CheckOnboardingProposal extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'onboarding:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Onboarding check';

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
        $voteFormals = Vote::where('type', 'formal')->where('content_type', 'grant')->pluck('proposal_id');
        $proposal_ids = $voteFormals->toArray();
        $votes = Vote::where('type', 'informal')->where('content_type', 'grant')
            ->where('result', 'success')
            ->whereDoesntHave('onboarding')
            ->whereNotIn('proposal_id', $proposal_ids)
            ->get();
        foreach ($votes as $vote) {
            $proposal = Proposal::find($vote->proposal_id);
            if ($proposal) {
                Helper::startOnboarding($proposal, $vote);
            }
        }
    }
}
