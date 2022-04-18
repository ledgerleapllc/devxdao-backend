<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class CriticalFunctionsTest extends TestCase
{
	public function testPreRegisterUser() {
		$response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/pre-register-user');

        // $apiResponse = $response->baseResponse->getData();
        
        $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'data',
                    ]);
	}

	public function testGetAllProposals2() {
		$response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/shared/all-proposals-2');

        $response->assertStatus(200)
                    ->assertJsonStructure([
                        'success',
                        'proposals',
                        'finished'
                    ]);
	}

	public function testGetDetailProposal2() {
		$response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('get', '/api/shared/all-proposals-2/1');

        // $apiResponse = $response->baseResponse->getData();
        
        $response
            ->assertStatus(200)
            ->assertJsonPath('success', false)
            ->assertJsonPath('message', 'Proposal Not found');
	}

	public function testLoginSuccessful() {
        $this->addAdmin();

        $user = [
            'email' => 'ledgerleapllc@gmail.com',
            'password' => 'ledgerleapllc',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/login', $user);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user',
                ]);
	}

    public function testLoginFailure() {
        $user = [
            'email' => 'ledgerleapllc@gmail.com',
            'password' => 'ledgerleapllc',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/login', $user);

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                ]);
    }

    public function testRegister() {
        $user = [
            'email' => 'ledgerleapllc@gmail.com',
            'password' => 'ledgerleapllc',
            'first_name' => 'Ledger',
            'last_name' => 'Leap',
            'forum_name' => 'ledgerleapllc'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/register', $user);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user',
                ]);
    }

    public function testRegisterAdmin() {
        $user = [
            'email' => 'ledgerleapllc@gmail.com',
            'first_name' => 'Ledger',
            'last_name' => 'Leap'
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/pre-register', $user);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testOpsLogin() {
        $this->addOpsUser();

        $user = [
            'email' => 'ledgerleapllcops@gmail.com',
            'password' => 'ledgerleapllc',
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
	
    public function testComplianceLogin() {
        $this->addComplianceUser();

        $user = [
            'email' => 'ledgerleapllccompliance@gmail.com',
            'password' => 'ledgerleapllc',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
        ])->json('post', '/api/compliance/login', $user);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'user',
                ]);
    }
    
    public function testGetMe() {
        $this->addAdmin();
        $token = $this->getAdminToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/me', []);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'me',
                ]);
    }

    public function testSubmitProposal() {
        $this->addMember();
        $token = $this->getMemberToken();

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

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'proposal',
                ]);
    }

    /* // Not Working
    public function testSubmitSimpleProposal() {
        $this->addMember();
        $token = $this->getMemberToken();
        
        $params = [
            'title' => 'Test Simple Proposal',
            'short_description' => 'Test Short Description',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/user/simple-proposal', $params);

        $apiResponse = $response->baseResponse->getData();
    }
    */

    /* // Not Working
    public function testSubmitAdminGrantProposal() {
        $this->addMember();
        $token = $this->getMemberToken();

        $params = [
            'title' => 'Test Admin Grant Proposal',
            'total_grant' => '0',
            'things_delivered' => 'Test Note',
            'delivered_at' => '2021-01-08 19:00:00',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/user/admin-grant-proposal', $params);

        $apiResponse = $response->baseResponse->getData();

        var_dump($apiResponse);
        exit();
    }
    */

    public function TestSubmitAdvancePaymentProposal() {
        $this->addMember();
        $token = $this->getMemberToken();
    }
}
?>
