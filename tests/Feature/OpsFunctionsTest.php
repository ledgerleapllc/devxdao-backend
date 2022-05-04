<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class OpsFunctionsTest extends TestCase
{
	public function testOpsLogin() {
        $this->addOpsUser();

        $user = [
            'email' => 'ledgerleapllcops@gmail.com',
            'password' => 'WelcomeTest1@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/ops/login', $user);

        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user',
                ]);
    }

    public function testOpsGetMe() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/ops/me');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'me',
                ]);
    }

    public function testOpsAdminCreatePAUser() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();
        $response = $this->addOpsPAUser($token);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testOpsAdminRevokeUser() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();
        $response = $this->addOpsPAUser($token);

        $apiResponse = $response->baseResponse->getData();
        $user = $apiResponse->user;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/ops/admin/users/' . $user->id . '/revoke');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testOpsAdminUndoRevokeUser() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();
        $response = $this->addOpsPAUser($token);

        $apiResponse = $response->baseResponse->getData();
        $user = $apiResponse->user;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/ops/admin/users/' . $user->id . '/undo-revoke');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testOpsAdminResetPassword() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();
        $response = $this->addOpsPAUser($token);

        $apiResponse = $response->baseResponse->getData();
        $user = $apiResponse->user;

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/ops/admin/users/' . $user->id . '/reset-password', [
            'password' => 'WelcomeTest2@'
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testOpsCheckCurrentPassword() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/ops/shared/check-current-password', [
            'current_password' => 'WelcomeTest1@'
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testOpsCheckCurrentPasswordWrong() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/ops/shared/check-current-password', [
            'current_password' => 'WelcomeTest1@Wrong'
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonPath('success', false)
                ->assertJsonStructure([
                    'message',
                ]);
    }

    public function testOpsChangePassword() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/ops/shared/change-password', [
            'current_password' => 'WelcomeTest1@',
            'new_password' => 'WelcomeTest2@',
        ]);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }
    
    public function testOpsGetUserAll() {
        $this->addOpsUser();
        $token = $this->getOpsUserToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/ops/user/all');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'users',
                ]);
    }
}
?>
