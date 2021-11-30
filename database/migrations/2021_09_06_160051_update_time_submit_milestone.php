<?php

use App\Milestone;
use App\Vote;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTimeSubmitMilestone extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $milestones = Milestone::whereNull('submitted_time')->has('votes')->get();
        foreach($milestones as $milestone) {
            $vote = Vote::where('milestone_id', $milestone->id)->orderBy('created_at', 'asc')->first();
            if($vote){
                $milestone->submitted_time = $vote->created_at;
                $milestone->save();
            };
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
