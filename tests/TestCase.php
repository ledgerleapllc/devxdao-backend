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
use App\Proposal;

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

    public function addMember() {
        $user = User::where(['email' => 'testuser@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Test';
            $user->last_name = 'User';
            $user->email = 'testuser@gmail.com';
            $user->password = Hash::make('testuser');
            $user->confirmation_code = 'testuser';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->is_member = 1;
            $user->save();
        }

        if (!$user->hasRole('participant')) {
            $user->assignRole('participant');
        }

        if (!$user->hasRole('member')) {
            $user->assignRole('member');
        }

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'Test User';
            $profile->dob = '1989-12-1';
            $profile->country_citizenship = 'United States';
            $profile->country_residence = 'United States';
            $profile->address = 'New York';
            $profile->city = 'New York';
            $profile->zip = '10025';
            $profile->step_review = 1;
            $profile->step_kyc = 1;
            $profile->rep = 1000;
            $profile->save();
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

    public function getMemberVAToken() {
        $user = [
            'email' => 'testuser@gmail.com',
            'password' => 'testuser',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/compliance/login-user', $user);
        
        $apiResponse = $response->baseResponse->getData();
        $token = $apiResponse->user->accessTokenAPI;
        
        return $token;
    }
    
    public function getMemberToken() {
        $user = [
            'email' => 'testuser@gmail.com',
            'password' => 'testuser',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/login', $user);
        
        $apiResponse = $response->baseResponse->getData();
        $token = $apiResponse->user->accessTokenAPI;
        
        return $token;
    }

    public function getMember() {
        $user = [
            'email' => 'testuser@gmail.com',
            'password' => 'testuser',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/login', $user);
        
        $apiResponse = $response->baseResponse->getData();
        return $apiResponse->user;
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

        var_dump($apiResponse);
        exit();
        
        $token = $apiResponse->user->accessTokenAPI;
        
        return $token;
    }

    public function createProposal($token) {
        $params = [
            'title' => 'Test Proposal',
            'short_description' => 'Test Description',
            'explanation_benefit' => 'Test Explanation',
            'explanation_goal' => 'Test Goal',
            'total_grant' => '50',
            'resume' => 'https://example.com',
            'grants' => [
                [
                    'type' => '2',
                    'grant' => '1',
                    'percentage' => '100',
                    'type_other' => 'Rewards',
                ]
            ],
            'milestones' => [
                [
                    'title' => 'Test Milestone',
                    'details' => 'Test Details',
                    'deadline' => '2030-09-01',
                ]
            ],
            'relationship' => '3'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/user/proposal', $params);

        $apiResponse = $response->baseResponse->getData();
        return $apiResponse->proposal->id;
    }

    public function createPaymentProposal($userId) {
        $proposal = new Proposal;
        $proposal->title = 'Test Payment Proposal';
        $proposal->short_description = 'Test Description';
        $proposal->explanation_benefit = '';
        $proposal->explanation_goal = '';
        $proposal->total_grant = 100;
        $proposal->license = 0;
        $proposal->resume = '';
        $proposal->extra_notes = '';
        $proposal->license_other = '';
        $proposal->relationship = '';
        $proposal->received_grant_before = 0;
        $proposal->previous_work = '';
        $proposal->other_work = '';
        $proposal->user_id = $userId;
        $proposal->include_membership = 0;
        $proposal->status = 'payment';
        $proposal->save();

        return $proposal->id;
    }
}
