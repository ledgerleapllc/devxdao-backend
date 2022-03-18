<?php

use App\Http\Helper;
use App\Proposal;
use App\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTotalUserVaTopic extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $proposals =  Proposal::whereNotNull('discourse_topic_id')->whereNull('total_user_va')->where('status', 'approved')->get();
        $totalVA = Helper::getTotalMembers();
        foreach ($proposals as $proposal) {
            $proposal->total_user_va = $totalVA;
            $proposal->save();
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
