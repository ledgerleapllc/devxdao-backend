<?php

use App\MilestoneReview;
use App\MilestoneSubmitHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMilestoneSubmitHistory2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('milestone_submit_history', function (Blueprint $table) {
            $table->integer('milestone_review_id')->nullable();
        });
        $milestoneSubmitHistories = MilestoneSubmitHistory::get();
        foreach($milestoneSubmitHistories as $milestoneSubmitHistory) {
            $milestoneReview = MilestoneReview::where('milestone_id', $milestoneSubmitHistory->milestone_id)->first();
            if($milestoneReview) {
                $milestoneSubmitHistory->milestone_review_id = $milestoneReview->id;
                $milestoneSubmitHistory->save();
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
