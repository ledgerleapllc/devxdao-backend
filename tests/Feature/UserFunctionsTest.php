<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class UserFunctionsTest extends TestCase
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
        $this->addMember();
        $token = $this->getMemberToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/me');

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

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'proposal',
                ]);
    }
    
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

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'proposal',
                ]);
    }
    
    public function testSubmitAdvancePaymentProposal() {
        $this->addMember();
        $token = $this->getMemberToken();
        $proposalId = $this->createProposal($token);

        $params = [
            'total_grant' => 0,
            'amount_advance_detail' => 'Test',
            'proposal_id' => $proposalId
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/user/advance-payment-proposal', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'proposal',
                ]);
    }

    public function testSubmitProposalChange() {
        $this->addMember();
        $token = $this->getMemberToken();
        $proposalId = $this->createProposal($token);

        $params = [
            'proposal' => $proposalId,
            'what_section' => 'short_description',
            'additional_notes' => 'Test',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('post', '/api/user/proposal-change', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testUpdatePaymentProposal() {
        $this->addMember();
        $token = $this->getMemberToken();
        $proposalId = $this->createProposal($token);

        $params = [
            'dos_txid' => 'TEST',
            'dos_eth_amount' => 0.1
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/user/payment-proposal/' . $proposalId, $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                ]);
    }

    public function testCreatePaymentIntent() {
        $this->addMember();
        $token = $this->getMemberToken();
        $proposalId = $this->createProposal($token);

        $params = [
            'amount' => 20,
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/user/payment-proposal/' . $proposalId . '/payment-intent', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'secret',
                ]);
    }

    public function testStakeReputation() {
        $this->addMember();
        $user = $this->getMember();
        $proposalId = $this->createPaymentProposal($user->id);

        $params = [
            'rep' => 50,
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $user->accessTokenAPI,
        ])->json('put', '/api/user/payment-proposal/' . $proposalId . '/stake-reputation', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testStakeCC() {
        $this->addMember();
        $user = $this->getMember();
        $proposalId = $this->createPaymentProposal($user->id);

        $params = [];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $user->accessTokenAPI,
        ])->json('put', '/api/user/payment-proposal/' . $proposalId . '/stake-cc', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testUpdatePaymentForm() {
        $this->addMember();
        $token = $this->getMemberToken();
        $proposalId = $this->createProposal($token);

        $params = [
            'bank_name' => 'Test Bank',
            'iban_number' => 'Test IBan',
            'swift_number' => 'Swift',
            'holder_name' => 'Test Holder',
            'account_number' => 'Test Account',
            'bank_address' => 'Test Bank',
            'bank_city' => 'New York',
            'bank_country' => 'United States',
            'bank_zip' => '10016',
            'holder_address' => 'New York',
            'holder_city' => 'New York',
            'holder_country' => 'United States',
            'holder_zip' => '10016',
            'crypto_type' => 'eth',
            'crypto_address' => '0x1f82739ae412ff32cd28e4a1b2c80b96f4c770e3',
        ];

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('put', '/api/user/proposal/' . $proposalId . '/payment-form', $params);

        // $apiResponse = $response->baseResponse->getData();

        $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                ]);
    }

    public function testGetReputationTrack() {
        $this->addMember();
        $token = $this->getMemberToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/user/reputation-track', []);

        // $apiResponse = $response->baseResponse->getData();

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function testGetActiveProposals() {
        $this->addMember();
        $token = $this->getMemberToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/user/active-proposals', []);

        // $apiResponse = $response->baseResponse->getData();

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }

    public function testGetOnboardings() {
        $this->addMember();
        $token = $this->getMemberToken();

        $response = $this->withHeaders([
            'Accept' => 'application/json',
            'Authorization' => 'Bearer ' . $token,
        ])->json('get', '/api/user/onboardings', []);

        $apiResponse = $response->baseResponse->getData();

        $response
            ->assertStatus(200)
            ->assertJsonPath('success', true);
    }
}
?>
