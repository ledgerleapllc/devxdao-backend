<?php

use App\ComplianceUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Role;
use Log;


class CreateDataComplianceUser extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $role = Role::where(['name' => 'admin', 'guard_name' => 'compliance_api'])->first();
        if (!$role) Role::create(['name' => 'admin',  'guard_name' =>'compliance_api']);

        $compliance_user = ComplianceUser::where(['email' => 'ledgerleapllc@gmail.com'])->first();
        if (!$compliance_user) {
            $compliance_user = new ComplianceUser;
            $compliance_user->first_name = 'Ledger';
            $compliance_user->last_name = 'Leap';
            $compliance_user->status = 'active';
            $compliance_user->email = 'ledgerleapllc@gmail.com';
            $random_pw = Str::random(10);
            $compliance_user->password = Hash::make($random_pw);
            Log::info('Created Compliance admin');
            Log::info('Email: '.$compliance_user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $compliance_user->is_super_admin = 1;
            $compliance_user->email_verified_at = now();
            $compliance_user->save();
          
        }
        // if (!$compliance_user->hasRole('admin'))
        // $compliance_user->assignRole('admin');
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
