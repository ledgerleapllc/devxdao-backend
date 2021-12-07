<?php

use App\FinalGrant;
use App\GrantTracking;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RevomeGrantTrackingGrantActivated extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $grant_trackings = GrantTracking::where('key', 'grant_activated')->get();
        foreach($grant_trackings as $grant_tracking) {
            $final_grant = FinalGrant::where('status', 'pending')->where('proposal_id', $grant_tracking->proposal_id)->first();
            if($final_grant) {
                $grant_tracking->delete();
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
