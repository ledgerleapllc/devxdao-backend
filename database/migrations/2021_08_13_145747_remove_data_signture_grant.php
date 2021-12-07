<?php

use App\SignatureGrant;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class RemoveDataSigntureGrant extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        SignatureGrant::where('signed', 0)->where('role', 'COO')->delete();
        SignatureGrant::where('signed', 0)->where('role', 'BP')->delete();
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
