<?php

use App\OpsUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Log;

class CreateDataOpsUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $ops_user = OpsUser::where(['email' => 'ledgerleapllc@gmail.com'])->first();
        if (!$ops_user) {
            $ops_user = new OpsUser;
            $ops_user->first_name = 'Ledger';
            $ops_user->last_name = 'Leap';
            $ops_user->status = 'active';
            $ops_user->email = 'ledgerleapllc@gmail.com';
            $random_pw = Str::random(10);
            $ops_user->password = Hash::make($random_pw);
            Log::info('Created OPS admin');
            Log::info('Email: '.$ops_user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $ops_user->is_super_admin = 1;
            $ops_user->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
    }
}
