<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ComplianceFunctionsTest extends TestCase
{
    public function testLoginWithUserVa() {
        $this->addMember();

        $user = [
            'email' => 'testuser@gmail.com',
            'password' => 'testuser',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/compliance/login-user', $user);

        $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'user',
                    ]);
    }

    public function testGetCurrentAddressPaymentUser() {
        $this->addMember();
        $token = $this->getMemberVAToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/compliance/user/payment-address/current');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'message',
                    ]);
    }

    public function testChangePaymentAddress() {
        $this->addMember();
        $token = $this->getMemberVAToken();

        $params = [
            'cspr_address' => '0x1f82739ae412ff32cd28e4a1b2c80b96f4c770e3'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/compliance/user/payment-address/change', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                    ]);
    }

    public function testGetMe() {
        $this->addMember();
        $token = $this->getMemberVAToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/compliance/me');

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'me',
                    ]);
    }
}
