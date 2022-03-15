<?php

use App\Proposal;
use App\User;
use App\Vote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;

class UpdateTotalUserVAProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $proposals =  Proposal::whereNotNull('discourse_topic_id')->whereNull('total_user_va')->get();
        foreach ($proposals as $proposal) {
            $vote = Vote::where('proposal_id', $proposal->id)->where('content_type', 'grant')
                ->where('type', 'informal')->first();
            if($vote) {
                $totalVA = User::where('is_member', 1)->where('member_at', '<=', $vote->created_at)->count();
                $proposal->total_user_va = $totalVA;
                $proposal->save();
            }
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
