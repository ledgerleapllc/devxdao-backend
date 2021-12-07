<?php

use App\Proposal;
use App\ProposalChange;
use Illuminate\Database\Migrations\Migration;

class UpdateProposalChangeStakeCC extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $proposals = Proposal::where('dos_paid', 1)->where('status', 'approved')->where('dos_cc_amount', '>', 0)->get();
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
