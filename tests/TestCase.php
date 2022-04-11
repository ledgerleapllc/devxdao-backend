<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\DatabaseMigrations;

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Date;

use Spatie\Permission\Models\Role;

use App\User;
use App\Profile;
use App\OpsUser;
use App\ComplianceUser;

use App\Http\Helper;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, DatabaseMigrations;
    
    public function setUp(): void
    {
        parent::setUp();
        
        Artisan::call('config:clear');
        Artisan::call('cache:clear');
        Artisan::call('passport:install');

        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->registerPermissions();
        $this->app->make(\Spatie\Permission\PermissionRegistrar::class)->forgetCachedPermissions();
        
        if (!Role::where('name', 'admin')->where('guard_name', 'web')->first()) {
            Role::create(['name' => 'admin', 'guard_name' => 'web']);
        }

        if (!Role::where('name', 'admin')->where('guard_name', 'compliance_api')->first()) {
            Role::create(['name' => 'admin', 'guard_name' => 'compliance_api']);
        }

        if (!Role::where('name', 'participant')->first()) {
            Role::create(['name' => 'participant']);
        }

        if (!Role::where('name', 'member')->first()) {
            Role::create(['name' => 'member']);
        }

        if (!Role::where('name', 'proposer')->first()) {
            Role::create(['name' => 'proposer']);
        }

        if (!Role::where('name', 'guest')->first()) {
            Role::create(['name' => 'guest']);
        }

        if (!Role::where('name', 'super-admin')->first()) {
            Role::create(['name' => 'super-admin']);
        }

        if (!Role::where('name', 'assistant')->first()) {
            Role::create(['name' => 'assistant']);
        }
    }

    public function addAdmin() {
        $user = User::where(['email' => 'ledgerleapllc@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Ledger';
            $user->last_name = 'Leap';
            $user->email = 'ledgerleapllc@gmail.com';
            $user->password = Hash::make('ledgerleapllc');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin')) {
            $user->assignRole('admin');
        }

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'LedgerLeap';
            $profile->dob = '1989-12-1';
            $profile->country_citizenship = 'United States';
            $profile->country_residence = 'United States';
            $profile->address = 'New York';
            $profile->city = 'New York';
            $profile->zip = '10025';
            $profile->step_review = 1;
            $profile->step_kyc = 1;
            $profile->save();
        }
    }

    public function addOpsUser() {
        $ops_user = OpsUser::where(['email' => 'ledgerleapllcops@gmail.com'])->first();
        if (!$ops_user) {
            $ops_user = new OpsUser;
            $ops_user->first_name = 'Ledger';
            $ops_user->last_name = 'Leap';
            $ops_user->status = 'active';
            $ops_user->email = 'ledgerleapllcops@gmail.com';
            $ops_user->password = Hash::make('ledgerleapllc');
            $ops_user->is_super_admin = 1;
            $ops_user->save();
        }
    }

    public function addComplianceUser() {
        $compliance_user = ComplianceUser::where(['email' => 'ledgerleapllccompliance@gmail.com'])->first();
        if (!$compliance_user) {
            $compliance_user = new ComplianceUser;
            $compliance_user->first_name = 'Ledger';
            $compliance_user->last_name = 'Leap';
            $compliance_user->status = 'active';
            $compliance_user->email = 'ledgerleapllccompliance@gmail.com';
            $compliance_user->password = Hash::make('ledgerleapllc');
            $compliance_user->is_super_admin = 1;
            $compliance_user->email_verified_at = now();
            $compliance_user->save();
        }
    }

    public function getAdminToken() {
        $user = [
            'email' => 'ledgerleapllc@gmail.com',
            'password' => 'ledgerleapllc',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/login', $user);
    
        $apiResponse = $response->baseResponse->getData();
        $token = $apiResponse->user->accessTokenAPI;
        
        return $token;
    }
}
