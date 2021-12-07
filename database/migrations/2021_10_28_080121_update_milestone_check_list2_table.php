<?php

use App\MilestoneCheckList;
use App\MilestoneReview;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMilestoneCheckList2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('milestone_checklist', function (Blueprint $table) {
            $table->integer('milestone_review_id')->nullable();
        });
        $milestoneChecklists = MilestoneCheckList::get();
        foreach($milestoneChecklists as $milestoneChecklist) {
            $milestoneReview = MilestoneReview::where('milestone_id', $milestoneChecklist->milestone_id)->first();
            if($milestoneReview) {
                $milestoneChecklist->milestone_review_id = $milestoneReview->id;
                $milestoneChecklist->save();
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
