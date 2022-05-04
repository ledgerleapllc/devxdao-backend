<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class AdminFunctionsTest extends TestCase
{
	public function testAdminLogin() {
        $this->addAdmin();

        $params = [
            'email' => 'ledgerleapllc@gmail.com',
            'password' => 'WelcomeTest1@',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/login', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user',
                ]);
    }

    public function testGetEmailerData() {
        $this->addAdmin();
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/admin/emailer-data');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'data',
                ]);
    }

    public function testGetAdminPendingUsers() {
        $this->addAdmin();
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/admin/pending-users');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'users',
                ]);
    }

    public function testGetPreRegisterUsers() {
        $this->addAdmin();
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/admin/pre-register-users');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'users',
                ]);
    }

    public function testGetUsers() {
        $this->addAdmin();
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/admin/users');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'users',
                ]);
    }
}
?>
