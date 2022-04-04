<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Proposal;
use App\Reputation;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ReturnMintedProposalCompleted extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'return:minted-pending';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Return minted pending proposal complete manual';

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
        $proposals = Proposal::where('proposal.type', 'grant')
            ->where('proposal.status', 'completed')
            ->where(function ($query) {
                $query->where('proposal.rep', 0)
                    ->orWhere('proposal.rep', null);
            })
            ->get();
        foreach ($proposals as $proposal) {
            $op = User::where('id', $proposal->user_id)->where('is_member', 1)->first();
            if ($op) {
                $repuations = Reputation::where('user_id', $op->id)->where('proposal_id', $proposal->id)->where('type', 'Minted Pending')->get();
                foreach ($repuations as $repuation) {
                    Log::info("Proposal: $repuation->proposal_id , user: $op->id");
                    $value = (float) $repuation->pending;
                    if ($value > 0) {
                        $op->profile->rep_pending = (float) $op->profile->rep_pending - $value;
                        if ((float) $op->profile->rep_pending < 0) {
                            $op->profile->rep_pending = 0;
                        }
                        $op->profile->save();
                        Helper::updateRepProfile($op->id, $value);
                        Helper::createRepHistory($op->id, $value, $op->profile->rep, 'Minted', $repuation->event, $proposal->id, null, 'completeProposal');
                    }
                    $repuation->type = 'Minted';
                    $repuation->value = (float) $repuation->pending;
                    $repuation->pending = 0;
                    $repuation->save();
                }
            }
        }
    }
}
