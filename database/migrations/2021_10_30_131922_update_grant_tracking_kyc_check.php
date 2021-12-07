<?php

use App\GrantTracking;
use App\Proposal;
use App\Shuftipro;
use Illuminate\Database\Migrations\Migration;

class UpdateGrantTrackingKycCheck extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $proposals = Proposal::get();
        foreach ($proposals as $proposal) {
            $shuftipro = Shuftipro::where('user_id', $proposal->user_id)->where('status', 'approved')->first();
            if ($shuftipro) {
                $grantTracking = GrantTracking::where('proposal_id', $proposal->id)->where('key', 'kyc_checks_complete')->first();
                if (!$grantTracking) {
                    $grantTracking = new GrantTracking();
                    $grantTracking->proposal_id = $proposal->id;
                    $grantTracking->event = "KYC checks complete";
                    $grantTracking->key = 'kyc_checks_complete';
                    $grantTracking->save();
                }
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
