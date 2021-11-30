<?php

use App\Http\Helper;
use App\Proposal;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataPaymentCC extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $setting = Helper::getSettings();
		$dos_fee_amount = $setting['dos_fee_amount'] ?? 100;
        $proposals = Proposal::where('dos_paid', 1)->whereNull('dos_txid')->whereNull('rep')->get();
        foreach($proposals as $proposal) {
            $proposal->dos_cc_amount = $dos_fee_amount;
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
