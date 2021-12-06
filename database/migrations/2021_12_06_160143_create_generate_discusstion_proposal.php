<?php

use App\Proposal;
use App\ProposalChange;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateGenerateDiscusstionProposal extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $proposals = Proposal::where('type', 'admin-grant')->where('status', 'approved')->get();
        foreach ($proposals as $proposal) {
            $proposalChange = ProposalChange::where('proposal_id', $proposal->id)->where('what_section', 'general_discussion')->first();
            if (!$proposalChange) {
                $proposalChange = new ProposalChange();
                $proposalChange->proposal_id = $proposal->id;
                $proposalChange->user_id = $proposal->user_id;
                $proposalChange->what_section = "general_discussion";
                $proposalChange->created_at = $proposal->approved_at;
                $proposalChange->updated_at = $proposal->approved_at;
                $proposalChange->save();
            }
        }
    }
}
