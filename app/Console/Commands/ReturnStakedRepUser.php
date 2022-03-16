<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Milestone;
use App\Reputation;
use App\User;
use App\Vote;
use Illuminate\Console\Command;

class ReturnStakedRepUser extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'return:staked-rep';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Return staked rep user';

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
        $reputations = Reputation::where('reputation.type', 'Staked')
            ->join('proposal', function ($join) {
                $join->on('proposal.id', '=', 'reputation.proposal_id');
                $join->where('proposal.status', '=', 'completed');
            })->select('reputation.*')
            ->get();
        foreach ($reputations as $reputation) {
            $value = abs($reputation->staked);
            Helper::updateRepProfile($reputation->user_id, $value);
            $user = User::find($reputation->user_id);
            Helper::createRepHistory($reputation->user_id, $value,  $user->profile->rep, 'Gained', 'Proposal Vote Result Staked', $reputation->proposal_id, $reputation->vote_id, 'ReturnStakedRepUser');
            $reputation->delete();
        }
    }
}
