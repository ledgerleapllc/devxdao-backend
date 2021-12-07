<?php

use App\Milestone;
use App\MilestoneReview;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMilestoneReview3Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('milestone_review', function (Blueprint $table) {
            $table->integer('time_submit')->nullable();
        });
        $milestoneReviews = MilestoneReview::get();
        foreach($milestoneReviews as $milestoneReview) {
            $milestone = Milestone::find($milestoneReview->milestone_id);
            if($milestone) {
                $milestoneReview->time_submit = $milestone->time_submit;
                $milestoneReview->save();
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
