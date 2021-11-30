<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;

use App\Http\Helper;

use App\User;
use App\Profile;
use App\PendingAction;
use App\Proposal;
use App\ProposalHistory;
use App\ProposalChange;
use App\ProposalChangeSupport;
use App\ProposalChangeComment;
use App\ProposalFile;
use App\Bank;
use App\Crypto;
use App\Grant;
use App\Citation;
use App\Exports\MyReputationExport;
use App\Milestone;
use App\FinalGrant;
use App\Team;
use App\Vote;
use App\VoteResult;
use App\Setting;
use App\OnBoarding;
use App\Reputation;
use App\Shuftipro;
use App\ShuftiproTemp;
use App\SponsorCode;
use App\log;

use App\Mail\AdminAlert;
use App\Mail\UserAlert;
use App\Mail\TwoFA;
use App\Mail\HelpRequest;
use PDF;

use App\Jobs\Test;
use App\MilestoneReview;
use App\ProposalDraft;
use App\ProposalDraftFile;
use App\Survey;
use App\SurveyDownVoteResult;
use App\SurveyResult;
use App\SurveyRfpBid;
use App\SurveyRfpResult;
use Carbon\Carbon;
use Maatwebsite\Excel\Facades\Excel;

class UserController extends Controller
{
	public function testJob()
	{
		Test::dispatch();
	}

	// Request Help
	public function requestHelp(Request $request) {
		$user = Auth::user();
		if ($user && $user->hasRole(['member', 'participant'])) {
			$text = $request->get('text');
			if ($text) {
				// Mail to Admin
				$admins = ['wulf@wulfkaal.com', 'timothy.messer@emergingte.ch', 'wulf.kaal@emergingte.ch', 'hayley.howe@emergingte.ch'];
    		Mail::to($admins)->send(new HelpRequest($user->email, $text));

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Send Hellosign Request
	public function sendHellosignRequest(Request $request) {
		$user = Auth::user();
		if ($user) {
			$client = new \HelloSign\Client(config('services.hellosign.api_key'));
    	$client_id = config('services.hellosign.client_id');

    	$request = new \HelloSign\TemplateSignatureRequest;
    	// $request->enableTestMode();

    	$request->setTemplateId(config('services.hellosign.associate_template_id'));
	    $request->setSubject('Program Associate Agreement');
	    $request->setSigner('User', $user->email, $user->first_name . ' ' . $user->last_name);

	    $request->setCustomFieldValue('FullName', $user->first_name . ' ' . $user->last_name);
	    $request->setCustomFieldValue('FullName2', $user->first_name . ' ' . $user->last_name);

	    $initial = strtoupper(substr($user->first_name, 0, 1)) . strtoupper(substr($user->last_name, 0, 1));
	    $request->setCustomFieldValue('Initial', $initial);

        $request->setClientId($client_id);

	    $embedded_request = new \HelloSign\EmbeddedSignatureRequest($request, $client_id);
	    $response = $client->createEmbeddedSignatureRequest($embedded_request);

		Helper::createHellosignLogging(
			$user->id,
			'Create Embedded Signature Request',
			'create_embedded_signature_request',
			json_encode([
				'Subject' => "Program Associate Agreement",
			])
		);

	    $signature_request_id = $response->getId();

	    $signatures = $response->getSignatures();
	    $signature_id = $signatures[0]->getId();

	    $response = $client->getEmbeddedSignUrl($signature_id);
	    $sign_url = $response->getSignUrl();

	    return [
	      'success' => true,
	      'url' => $sign_url,
	      'signature_request_id' => $signature_request_id
	    ];
		}

		return ['success' => false];
	}

	// Update Shuftipro Temp Status
  public function updateShuftiProTemp(Request $request) {
    // Validator
    $validator = Validator::make($request->all(), [
      'user_id' => 'required',
      'reference_id' => 'required'
    ]);
    if ($validator->fails()) return ['success' => false];

    $user_id = (int) $request->get('user_id');
    $reference_id = $request->get('reference_id');

    $record = ShuftiproTemp::where('user_id', $user_id)
                            ->where('reference_id', $reference_id)
                            ->first();

    if ($record) {
      $record->status = 'booked';
      $record->save();

      // Emailer User
			$user = User::find($user_id);
			if ($user) {
				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'AML Submit', $emailerData);
			}

			// Check if All Steps Completed
			// Further coding required

      return ['success' => true];
    }

    return ['success' => false];
  }

	// Save Shuftipro Temp
  public function saveShuftiproTemp(Request $request) {
    // Validator
    $validator = Validator::make($request->all(), [
      'user_id' => 'required',
      'reference_id' => 'required'
    ]);
    if ($validator->fails()) return ['success' => false];

    $user_id = (int) $request->get('user_id');
    $reference_id = $request->get('reference_id');

    ShuftiproTemp::where('user_id', $user_id)->delete();

    $record = new ShuftiproTemp;
    $record->user_id = $user_id;
    $record->reference_id = $reference_id;
    $record->save();

    return ['success' => true];
  }

	// Get Active Proposal By Id - For Discussions
	public function getActiveProposalById($proposalId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposal = Proposal::where('id', $proposalId)
													->with(['bank', 'citations', 'crypto', 'grants', 'milestones', 'members', 'files', 'votes'])
													->first();

			if ($proposal && $proposal->status == 'approved') {
				// Latest Changes
				$sections = ['short_description', 'total_grant', 'previous_work', 'other_work'];
				$changes = [];

				foreach ($sections as $section) {
					$change = ProposalChange::where('proposal_id', $proposal->id)
																	->where('what_section', $section)
																	->where('status', 'approved')
																	->orderBy('updated_at', 'desc')
																	->first();

					if ($change) {
						$changes[$section] = $change;
					}
				}

				$proposal->changes = $changes;

				// Has Pending Change
				$pendingCount = ProposalChange::where('proposal_id', $proposal->id)
																			->where('status', 'pending')
                                                                            ->where('what_section', '!=', 'general_discussion')
                                                                            ->where('user_id', '!=', $proposal->user_id)
																			->get()
																			->count();
				$proposal->pendingChangeCount = $pendingCount;
				$proposal->hasPendingChange = $pendingCount > 0 ? true : false;

				// Vote Results
				$voteResults = VoteResult::where('proposal_id', $proposal->id)
																	->where('user_id', $user->id)
																	->get();
				$proposal->voteResults = $voteResults;

				return [
					'success' => true,
					'proposal' => $proposal
				];
			}
		}

		return ['success' => false];
	}

	// Get My Denied Proposal By Id - For Edit
	public function getMyDeniedProposalById($proposalId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposal = Proposal::where('id', $proposalId)
													->with(['bank', 'crypto', 'grants', 'citations', 'milestones', 'members', 'files'])
													->first();

			if ($proposal && $proposal->status == 'denied' && $proposal->user_id == $user->id) {
				return [
					'success' => true,
					'proposal' => $proposal
				];
			}
		}

		return ['success' => false];
	}

	// Get Onboardings
	public function getOnboardings(Request $request)
	{
		$user = Auth::user();
		$onboardings = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'onboarding.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole(['participant', 'member'])) {
			// OnBoarding
			$onboardings = OnBoarding::join('proposal', 'proposal.id', '=', 'onboarding.proposal_id')
			->leftJoin('final_grant', 'onboarding.proposal_id', '=', 'final_grant.proposal_id')
			->with([
				'proposal', 'proposal.bank', 'proposal.crypto', 'user', 'user.shuftipro',
				'user.shuftiproTemp', 'vote'
			])
			->has('proposal')
			->has('user')
			->has('vote')
			->where('onboarding.user_id', $user->id)
				->where('onboarding.status', 'pending')
				// ->where('final_grant.id', null)
				// ->whereNotExists(function ($query) {
				// 	$query->select('id')
				// 	->from('vote')
				// 	->whereColumn('onboarding.proposal_id', 'vote.proposal_id')
				// 	->where('vote.type', 'formal');
				// })
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.member_reason', 'like', '%' . $search . '%');
					}
				})
				->select([
					'onboarding.*',
					'proposal.include_membership'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}

		$onboardings->each(function ($onboarding, $key) {
			$onboarding->user->makeVisible([
				"shuftipro",
				"shuftiproTemp",
			]);
		});

		return [
			'success' => true,
			'onboardings' => $onboardings,
			'finished' => count($onboardings) < $limit ? true : false
		];
	}

	// Get Reputation Track
	public function getReputationTrack(Request $request) {
		$user = Auth::user();
		$items = [];
		$total = 0;

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'reputation.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole(['member', 'participant'])) {
			$total_staked = DB::table('reputation')
													->where('user_id', $user->id)
                          ->where('type', 'Staked')
                          ->sum('staked');
      $total = round(abs($total_staked), 2);
			if ($total < 0) $total = 0;

			$items = Reputation::leftJoin('proposal', 'proposal.id', '=', 'reputation.proposal_id')
													->leftJoin('users', 'users.id', '=', 'proposal.user_id')
													->where('reputation.user_id', $user->id)
													->where(function ($query) use ($search) {
														if ($search) {
															$query->where('proposal.title', 'like', '%' . $search . '%')
																		->orWhere('reputation.type', 'like', '%' . $search . '%');
														}
													})
													->select([
														'reputation.*',
														'proposal.include_membership',
														'proposal.title as proposal_title',
														'users.first_name as op_first_name',
														'users.last_name as op_last_name'
													])
    											->orderBy($sort_key, $sort_direction)
													->offset($start)
													->limit($limit)
					                ->get();
		}

		return [
			'success' => true,
			'items' => $items,
			'finished' => count($items) < $limit ? true : false,
			'total' => $total
		];
	}

	// Get Active Proposals
	public function getActiveProposals(Request $request) {
		$user = Auth::user();
		$proposals = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole(['participant', 'member', 'guest'])) {
			$proposals = Proposal::with('votes')
														->whereIn('status', ['approved', 'completed'])
														->where(function ($query) use ($search) {
															if ($search) {
																$query->where('proposal.title', 'like', '%' . $search . '%')
																			->orWhere('proposal.member_reason', 'like', '%' . $search . '%');
															}
														})
														->orderBy($sort_key, $sort_direction)
														->offset($start)
														->limit($limit)
						                ->get();
		}

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get My Payment Proposals
	public function getMyPaymentProposals(Request $request) {
		$user = Auth::user();
		$proposals = [];

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposals = Proposal::where('proposal.user_id', $user->id)
														->whereIn('proposal.status', ['payment'])
														->where('dos_paid', 0)
														->orderBy('proposal.id', 'desc')
														->groupBy('proposal.id')
														->get();
		}

		return [
			'success' => true,
			'proposals' => $proposals
		];
	}

	// Get My Active Proposals
	public function getMyActiveProposals(Request $request) {
		$user = Auth::user();
		$proposals = [];

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposals = Proposal::leftJoin('proposal_change', function ($join) {
															$join->on('proposal_change.proposal_id', '=', 'proposal.id');
															$join->where('proposal_change.status', 'pending');
                                                            $join->where('proposal_change.what_section', '!=', 'general_discussion');
														})
														->selectRaw('proposal.*, count(proposal_change.proposal_id) as pendingCount')
														->where('proposal.user_id', $user->id)
														->whereIn('proposal.status', ['approved', 'completed'])
														->orderBy('proposal.id', 'desc')
														->groupBy('proposal.id')
														->get();
		}

		return [
			'success' => true,
			'proposals' => $proposals
		];
	}

	// Get My Pending Proposals
	public function getMyPendingProposals(Request $request) {
		$user = Auth::user();
		$proposals = [];

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposals = Proposal::where('user_id', $user->id)
														->whereIn('status', ['pending', 'denied'])
														->orderBy('created_at', 'desc')
														->get();
		}

		return [
			'success' => true,
			'proposals' => $proposals
		];
	}

	// Support UP Proposal Change
	public function supportUpProposalChange($proposalChangeId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposalChange = ProposalChange::find($proposalChangeId);
			if (!$proposalChange) {
				return [
					'success' => false,
					'message' => 'Invalid proposed change'
				];
			}

			$proposal = Proposal::find($proposalChange->proposal_id);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Proposal Status is not approved
			if ($proposal->status != 'approved') {
				return [
					'success' => false,
					'message' => "You can't support UP this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't support UP this proposed change"
				];
			}

			// Only Audience can do this action
			if ($user->id == $proposal->user_id || $user->id == $proposalChange->user_id) {
				return [
					'success' => false,
					'message' => "You can't support UP this proposed change"
				];
			}

			$support = ProposalChangeSupport::where('proposal_change_id', $proposalChangeId)->where('user_id', $user->id)->first();
			if ($support) {
				return [
					'success' => false,
					'message' => "You can't support UP this proposed change"
				];
			}

			$support = new ProposalChangeSupport;
			$support->proposal_change_id = $proposalChangeId;
			$support->user_id = $user->id;
			$support->value = 'up';
			$support->save();

			$proposalChange->up_count = (int) $proposalChange->up_count + 1;
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Support DOWN Proposal Change
	public function supportDownProposalChange($proposalChangeId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposalChange = ProposalChange::find($proposalChangeId);
			if (!$proposalChange) {
				return [
					'success' => false,
					'message' => 'Invalid proposed change'
				];
			}

			$proposal = Proposal::find($proposalChange->proposal_id);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Proposal Status is not approved
			if ($proposal->status != 'approved') {
				return [
					'success' => false,
					'message' => "You can't support DOWN this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't support DOWN this proposed change"
				];
			}

			// Only Audience can do this action
			if ($user->id == $proposal->user_id || $user->id == $proposalChange->user_id) {
				return [
					'success' => false,
					'message' => "You can't support DOWN this proposed change"
				];
			}

			$support = ProposalChangeSupport::where('proposal_change_id', $proposalChangeId)->where('user_id', $user->id)->first();
			if ($support) {
				return [
					'success' => false,
					'message' => "You can't support DOWN this proposed change"
				];
			}

			$support = new ProposalChangeSupport;
			$support->proposal_change_id = $proposalChangeId;
			$support->user_id = $user->id;
			$support->value = 'down';
			$support->save();

			$proposalChange->down_count = (int) $proposalChange->down_count + 1;
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Force Approve KYC
	public function forceApproveKYC(Request $request) {
		$user = Auth::user();

    if ($user) {
      $userId = (int) $user->id;

      $record = Shuftipro::where('user_id', $userId)->first();
      if (!$record) {
        $record = new Shuftipro;
        $record->user_id = $userId;
        $record->reference_id = 'SP_REQUEST_' . $userId . '_temp_' . time();
        $record->is_successful = 1;
        $record->data = '{}';
        $record->document_proof = 'test';
        $record->address_proof = 'test';
        $record->document_result = 1;
        $record->address_result = 1;
        $record->background_checks_result = 1;
        $record->save();
      }

      $record->status = 'approved';
      $record->reviewed = 1;
      $record->save();

      // Profile Update
      $profile = Profile::where('user_id', $user->id)->first();
      if ($profile) {
      	$profile->step_kyc = 1;
      	$profile->save();
    	}

      return ['success' => true];
    }

    return ['success' => false];
	}

	// Force Deny KYC
	public function forceDenyKYC(Request $request) {
		$user = Auth::user();

    if ($user) {
      $userId = (int) $user->id;

      $record = Shuftipro::where('user_id', $userId)->first();
      if (!$record) {
        $record = new Shuftipro;
        $record->user_id = $userId;
        $record->reference_id = 'SP_REQUEST_' . $userId . '_temp_' . time();
        $record->is_successful = 0;
        $record->data = '{}';
        $record->document_proof = 'test';
        $record->address_proof = 'test';
        $record->document_result = 1;
        $record->address_result = 1;
        $record->background_checks_result = 1;
        $record->save();
      }

      $record->status = 'denied';
      $record->reviewed = 0;
      $record->save();

      // Profile Update
      $profile = Profile::where('user_id', $user->id)->first();
      if ($profile) {
      	$profile->step_kyc = 0;
      	$profile->save();
    	}

      return ['success' => true];
    }

    return ['success' => false];
	}

	// Stake CC
	public function stakeCC($proposalId, Request $request) {
		$user = Auth::user();
		$setting = Helper::getSettings();
		$dos_fee_amount = $setting['dos_fee_amount'] ?? 0;
		if ($user) {
			// Profile Check
			$profile = Profile::where('user_id', $user->id)->first();
			if (!$profile) {
				return [
					'success' => false,
					'message' => 'Invalid User'
				];
			}

			// Proposal Check
			$proposal = Proposal::find($proposalId);
			if (!$proposal || $proposal->status != "payment" || $proposal->dos_paid || $proposal->user_id != $user->id) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Activates the Proposal ( Admin doesn't need to manually approve the payment by reputation )
			$proposal->dos_paid = 1;
			$proposal->status = 'approved';
			$proposal->dos_amount = $dos_fee_amount;
			$proposal->dos_cc_amount = $dos_fee_amount;
            $proposal->save();

            // Create Change Record
			$proposalChange = new ProposalChange();
			$proposalChange->proposal_id = $proposalId;
			$proposalChange->user_id = $user->id;
			$proposalChange->what_section = "general_discussion";
			$proposalChange->save();

			// Update Timestamp
			$proposal->approved_at = $proposal->updated_at;
			$proposal->save();

			// Emailer Member
	    $emailerData = Helper::getEmailerData();
	    Helper::triggerMemberEmail('New Proposal Discussion', $emailerData, $proposal);
		Helper::createGrantTracking($proposalId, "Entered discussion phase", 'discussion_phase');

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Stake Reputation
	public function stakeReputation($proposalId, Request $request) {
		$user = Auth::user();
		$dos_fee_amount = $setting['dos_fee_amount'] ?? 0;
		if ($user && $user->hasRole('member')) {
			// Member Check
			$profile = Profile::where('user_id', $user->id)->first();
			if (!$profile) {
				return [
					'success' => false,
					'message' => 'Invalid User'
				];
			}

			// Rep Check
			$rep = (float) $request->get('rep');
			$rep = round($rep, 2);

			$max = (float) $profile->rep / 2;
			// $max = round($max, 2);

			if ($rep > $max) {
				return [
					'success' => false,
					'message' => 'Invalid reputation amount'
				];
			}

			// Proposal Check
			$proposal = Proposal::find($proposalId);
			if (!$proposal || $proposal->status != "payment" || $proposal->dos_paid || $proposal->user_id != $user->id) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			$profile->rep = (float) $profile->rep - $rep;
			$profile->save();
			Helper::createRepHistory($profile->user_id, - $rep, $profile->rep, 'Staked', 'Proposal Payment', $proposal->id, null, 'stakeReputation');

			// Activates the Proposal ( Admin doesn't need to manually approve the payment by reputation )
			$proposal->rep = $rep;
			$proposal->dos_paid = 1;
			$proposal->status = 'approved';
			$proposal->save();

			// Update Timestamp
			$proposal->approved_at = $proposal->updated_at;
			$proposal->dos_amount = $dos_fee_amount;
			$proposal->save();

			// Save Reputation Track
			if ($rep != 0) {
				$reputation = new Reputation;
				$reputation->user_id = $user->id;
				$reputation->proposal_id = $proposalId;
				$reputation->staked = -$rep;
				$reputation->event = "Proposal Payment";
				$reputation->type = "Staked";
				$reputation->save();
			}

            // Create Change Record
			$proposalChange = new ProposalChange;
			$proposalChange->proposal_id = $proposalId;
			$proposalChange->user_id = $user->id;
			$proposalChange->what_section = "general_discussion";
			$proposalChange->save();

			// Emailer Member
            $emailerData = Helper::getEmailerData();
            Helper::triggerMemberEmail('New Proposal Discussion', $emailerData, $proposal);
			Helper::createGrantTracking($proposalId, "Entered discussion phase", 'discussion_phase');

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Update Payment Form
	public function updatePaymentForm($proposalId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$bank_name = $request->get('bank_name');
			$iban_number = $request->get('iban_number');
			$swift_number = $request->get('swift_number');
			$holder_name = $request->get('holder_name');
			$account_number = $request->get('account_number');
			$bank_address = $request->get('bank_address');
			$bank_city = $request->get('bank_city');
			$bank_country = $request->get('bank_country');
			$bank_zip = $request->get('bank_zip');
			$holder_address = $request->get('holder_address');
			$holder_city = $request->get('holder_city');
			$holder_country = $request->get('holder_country');
			$holder_zip = $request->get('holder_zip');

			$crypto_type = $request->get('crypto_type');
			$crypto_address = $request->get('crypto_address');

			$proposal = Proposal::find($proposalId);
			if ($proposal) {
				$proposal->form_submitted = 1;
				$proposal->save();
			}

			// Updating Bank
			Bank::where('proposal_id', $proposalId)->delete();
			$bank = new Bank;
			$bank->proposal_id = (int) $proposal->id;
			if ($bank_name)
				$bank->bank_name = $bank_name;
			if ($iban_number)
				$bank->iban_number = $iban_number;
			if ($swift_number)
				$bank->swift_number = $swift_number;
			if ($holder_name)
				$bank->holder_name = $holder_name;
			if ($account_number)
				$bank->account_number = $account_number;
			if ($bank_address)
				$bank->bank_address = $bank_address;
			if ($bank_city)
				$bank->bank_city = $bank_city;
			if ($bank_zip)
				$bank->bank_zip = $bank_zip;
			if ($bank_country)
				$bank->bank_country = $bank_country;
			if ($holder_address)
				$bank->address = $holder_address;
			if ($holder_city)
				$bank->city = $holder_city;
			if ($holder_zip)
				$bank->zip = $holder_zip;
			if ($holder_country)
				$bank->country = $holder_country;
			$bank->save();

			// Updating Crypto
			Crypto::where('proposal_id', $proposalId)->delete();
			$crypto = new Crypto;
			$crypto->proposal_id = (int) $proposal->id;
			if ($crypto_address)
				$crypto->public_address = $crypto_address;
			if ($crypto_type)
				$crypto->type = $crypto_type;
			$crypto->save();

			// Emailer User
	    $emailerData = Helper::getEmailerData();
	    Helper::triggerUserEmail($user, 'Payment Form Complete', $emailerData);

	    return ['success' => true];
		}

		return ['success' => false];
	}

	public function testStripe() {
		ini_set('display_errors', 1);
		error_reporting(E_ALL);

		$stripe = new \Stripe\StripeClient(
			config('services.stripe.sk_live')
		);
		$paymentIntent = $stripe->paymentIntents->create([
		  'amount' => 100,
		  'currency' => 'eur',
		  // 'payment_method_types' => ['card'],
		]);
	}

	// Create Payment Intent
	public function createPaymentIntent(Request $request) {
		/*
		config('services.stripe.sk_live')
		config('services.stripe.sk_test')
		*/

		$user = Auth::user();
		$amount = (float) $request->get('amount');

		if (
			$user &&
			$user->hasRole(['participant', 'member']) &&
			$amount > 0
		) {
			$amount = (int) (100 * $amount);

			$stripe = new \Stripe\StripeClient(
				config('services.stripe.sk_live')
			);

			try {
				$paymentIntent = $stripe->paymentIntents->create([
				  'amount' => $amount,
				  'currency' => 'eur',
				  // 'payment_method_types' => ['card'],
				]);

				if ($paymentIntent && isset($paymentIntent->client_secret)) {
					$secret = $paymentIntent->client_secret;

					return [
				  	'success' => true,
				  	'secret' => $secret,
				  ];
				} else {
					return [
				  	'success' => false,
				  	'paymentIntent' => $paymentIntent,
				  ];
				}
			} catch (Exception $e) {
				return [
					'success' => false,
					'message' => $e->getMessage()
				];
			}

			/*
			\Stripe\Stripe::setApiKey(config('services.stripe.sk_test'));

			$paymentIntent = \Stripe\PaymentIntent::create([
				'amount' => $amount,
				'currency' => 'eur',
		  ]);

		  $secret = $paymentIntent->client_secret;

		  return [
		  	'success' => true,
		  	'secret' => $secret,
		  ];
		  */
		}

		return ['success' => false];
	}

	// Update Payment Proposal - ETH
	public function updatePaymentProposal($proposalId, Request $request) {
		$user = Auth::user();
		$dos_fee_amount = $setting['dos_fee_amount'] ?? 0;
		if ($user && $user->hasRole(['participant', 'member'])) {
			$dos_txid = $request->get('dos_txid');
			$dos_eth_amount = (float) $request->get('dos_eth_amount');

			if (!$dos_txid) {
				return [
					'success' => false,
					'message' => 'TX ID is required'
				];
			}

			if ($dos_eth_amount <= 0) {
				return [
					'success' => false,
					'message' => 'ETH amount should be higher than 0'
				];
			}

			// TX ID Check
			$proposal = Proposal::where('dos_txid', $dos_txid)->first();
			if ($proposal && $dos_txid != config('services.crypto.eth.secret_code')) {
				return [
					'success' => false,
					'message' => 'TX ID is already used'
				];
			}

			// Proposal Check
			$proposal = Proposal::find($proposalId);
			if (!$proposal || $proposal->status != "payment" || $proposal->dos_paid || $proposal->user_id != $user->id) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			$proposal->dos_paid = 1;
			$proposal->dos_txid = $dos_txid;
			$proposal->dos_eth_amount = $dos_eth_amount;
			$proposal->dos_amount = $dos_fee_amount;
			$proposal->rep = 0;
			$proposal->save();

            // Create Change Record
			$proposalChange = new ProposalChange;
			$proposalChange->proposal_id = $proposalId;
			$proposalChange->user_id = $user->id;
			$proposalChange->what_section = "general_discussion";
			$proposalChange->save();

			// Emailer Admin
            $emailerData = Helper::getEmailerData();
            Helper::triggerAdminEmail('DOS Fee Paid', $emailerData, $proposal);

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Approve Proposal Change
	public function approveProposalChange($proposalChangeId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposalChange = ProposalChange::find($proposalChangeId);
			if (!$proposalChange) {
				return [
					'success' => false,
					'message' => 'Invalid proposed change'
				];
			}

			$proposal = Proposal::find($proposalChange->proposal_id);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Proposal Status is not approved
			if ($proposal->status != 'approved') {
				return [
					'success' => false,
					'message' => "You can't approve this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't approve this proposed change"
				];
			}

			// Only OP can do this action
			if ($user->id != $proposal->user_id) { // Not OP
				return [
					'success' => false,
					'message' => "You can't approve this proposed change"
				];
			}

			// Record Proposal History
			$proposalId = (int) $proposal->id;
			$history = ProposalHistory::where('proposal_id', $proposalId)
																->where('proposal_change_id', $proposalChangeId)
																->first();

			if (!$history) {
				$history = new ProposalHistory;
			}

			$history->proposal_id = $proposalId;
			$history->proposal_change_id = $proposalChangeId;
			$history->what_section = $proposalChange->what_section;

			// Apply Changes
			$what_section = $proposalChange->what_section;
			if ($what_section == "short_description") {
				$history->change_to_before = $proposal->short_description;
				$proposal->short_description = $proposalChange->change_to;
			} else if ($what_section == "total_grant") {
				$rate = (float) $proposalChange->change_to / (float) $proposal->total_grant;

				$history->change_to_before = $proposal->total_grant;
				$proposal->total_grant = (float) $proposalChange->change_to;

				// Grants
				$grants = Grant::where('proposal_id', $proposalId)->get();
				if ($grants) {
					foreach ($grants as $grant) {
						$temp = (float) $grant->grant * $rate;
						$temp = round($temp, 2);
						$grant->grant = $temp;
						$grant->save();
					}
				}

				// Milestones
				$milestones = Milestone::where('proposal_id', $proposalId)->get();
				if ($milestones) {
					foreach ($milestones as $milestone) {
						$temp = (float) $milestone->grant * $rate;
						$temp = round($temp, 2);
						$milestone->grant = $temp;
						$milestone->save();
					}
				}
			} else if ($what_section == "previous_work") {
				$history->change_to_before = $proposal->previous_work;
				$proposal->previous_work = $proposalChange->change_to;
			} else if ($what_section == "other_work") {
				$history->change_to_before = $proposal->other_work;
				$proposal->other_work = $proposalChange->change_to;
			} else if ($what_section == "remove_membership") {
				$history->member_reason = $proposal->member_reason;
				$history->member_benefit = $proposal->member_benefit;
				$history->linkedin = $proposal->linkedin;
				$history->github = $proposal->github;

				$proposal->include_membership = 0;
				$proposal->member_reason = null;
				$proposal->member_benefit = null;
				$proposal->linkedin = null;
				$proposal->github = null;
			} else if ($what_section == "milestone") {
				$milestoneTemp = json_decode($proposalChange->change_to);

				if (!is_array($milestoneTemp) && isset($milestoneTemp->id)) {
					$milestoneTemp = collect($milestoneTemp);

					Milestone::where('id', $milestoneTemp->get('id'))
						->where('proposal_id', $proposal->id)
						->update($milestoneTemp->only([
							'title',
							'details',
							'grant',
							'criteria',
							'url',
							'comment',
							'kpi',
							'deadline',
							'level_difficulty',
							'attest_accepted_definition',
							'attest_accepted_pm',
							'attest_submitted_accounting',
							'attest_work_adheres',
							'attest_submitted_corprus',
							'attest_accept_crdao',
						])->all());
				}
			}

			$history->save();
			$proposal->save();

			// Change Proposal Change
			$proposalChange->status = 'approved';
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Deny Proposal Change
	public function denyProposalChange($proposalChangeId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposalChange = ProposalChange::find($proposalChangeId);
			if (!$proposalChange) {
				return [
					'success' => false,
					'message' => 'Invalid proposed change'
				];
			}

			$proposal = Proposal::find($proposalChange->proposal_id);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Proposal Status is not approved
			if ($proposal->status != 'approved') {
				return [
					'success' => false,
					'message' => "You can't deny this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't deny this proposed change"
				];
			}

			// Only OP can do this action
			if ($user->id != $proposal->user_id) { // Not OP
				return [
					'success' => false,
					'message' => "You can't deny this proposed change"
				];
			}

			$proposalChange->status = 'denied';
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Withdraw Proposal Change
	public function withdrawProposalChange($proposalChangeId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$proposalChange = ProposalChange::find($proposalChangeId);
			if (!$proposalChange) {
				return [
					'success' => false,
					'message' => 'Invalid proposed change'
				];
			}

			$proposal = Proposal::find($proposalChange->proposal_id);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Proposal Status is not approved
			if ($proposal->status != 'approved') {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposed change"
				];
			}

			// Only OC can do this action
			if ($user->id != $proposalChange->user_id) { // Not OC
				return [
					'success' => false,
					'message' => "You can't withdraw this proposed change"
				];
			}

			$proposalChange->status = "withdrawn";
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Submit Vote
	public function submitVote(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('member')) {
			// Validator
	    $validator = Validator::make($request->all(), [
	    	'proposalId' => 'required',
	      'voteId' => 'required',
	      'type' => 'required'
	    ]);
	    if ($validator->fails()) {
	    	return [
	    		'success' => false,
	    		'message' => 'Provide all the necessary information'
	    	];
	    }

	    $proposalId = (int) $request->get('proposalId');
	    $voteId = (int) $request->get('voteId');
	    $type = $request->get('type');
	    $value = (float) $request->get('value');
		if($value <= 0) {
			return [
	    		'success' => false,
	    		'message' => 'Invalid value'
	    	];
		}

	    // Vote Check
	    $vote = Vote::find($voteId);
	    if (!$vote) {
	    	return [
	    		'success' => false,
	    		'message' => 'Invalid vote'
	    	];
	    }

	    if ($proposalId != $vote->proposal_id || $vote->status != 'active') {
	    	return [
	    		'success' => false,
	    		'message' => 'Invalid vote'
	    	];
	    }

	    // Proposal Check
	    $proposal = Proposal::find($proposalId);
	    if (!$proposal || $proposal->status != "approved") {
	    	return [
	    		'success' => false,
	    		'message' => 'Invalid proposal'
	    	];
	    }

	    if ($proposal->user_id == $user->id) {
	    	return [
	    		'success' => false,
	    		'message' => "OP can't submit a vote"
	    	];
	    }

	    // Voter Check
	    $profile = Profile::where('user_id', $user->id)->first();
	    if (!$profile) {
	    	return [
	    		'success' => false,
	    		'message' => 'Invalid Voter'
	    	];
	    }

	    if ((float) $profile->rep < $value) {
	    	return [
	    		'success' => false,
	    		'message' => "You don't have enough reputation to vote"
	    	];
	    }

	    // Vote Result Check
	    $voteResult = VoteResult::where('proposal_id', $proposalId)
	    												->where('vote_id', $voteId)
	    												->where('user_id', $user->id)
	    												->first();
	    if ($voteResult) {
	    	return [
	    		'success' => false,
	    		'message' => 'You had already voted'
	    	];
	    }

	    // Create Vote Result
	    $voteResult = new VoteResult;
	    $voteResult->proposal_id = $proposalId;
	    $voteResult->vote_id = $voteId;
	    $voteResult->user_id = $user->id;
	    $voteResult->value = $value;
	    $voteResult->type = $type;
	    $voteResult->save();

	    // Update Voter Reputation
	    if ($vote->type == "formal") {
		    $profile->rep = (float) $profile->rep - $value;
		    $profile->save();
			Helper::createRepHistory($profile->user_id, - $value, $profile->rep, 'Staked', 'Proposal Vote', $proposalId, $voteResult->id, 'submitVote');

		    // Create Reputation Track
		    if ($value != 0) {
			    $reputation = new Reputation;
			    $reputation->user_id = $user->id;
			    $reputation->proposal_id = $proposalId;
			    $reputation->vote_id = $vote->id;
			  	$reputation->staked = -$value;
			    $reputation->event = "Proposal Vote";
			    $reputation->type = "Staked";
			    $reputation->save();
			  }
		  }

	    // Update Vote
	    if ($type == 'for')
	    	$vote->for_value = (float) $vote->for_value + $value;
	    else
	    	$vote->against_value = (float) $vote->against_value + $value;
	    $vote->result_count = (int) $vote->result_count + 1;
	    $vote->save();

	    return ['success' => true];
		}

		return ['success' => false];
	}

	// Submit Proposal Change Comment
	public function submitProposalChangeComment(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			// Validator
	    $validator = Validator::make($request->all(), [
	    	'proposalChange' => 'required',
	      'comment' => 'required',
	    ]);
	    if ($validator->fails()) {
	    	return [
	    		'success' => false,
	    		'message' => 'Provide all the necessary information'
	    	];
	    }

	    $proposalChangeId = (int) $request->get('proposalChange');
	    $comment = $request->get('comment');

	    $proposalChange = ProposalChange::find($proposalChangeId);
	    if (!$proposalChange || $proposalChange->status != 'pending') {
	    	return [
	    		'success' => false,
	    		'message' => 'Invalid proposed change'
	    	];
	    }

	    $proposalId = (int) $proposalChange->proposal_id;
	    $proposal = Proposal::find($proposalId);

	    if (!$proposal || $proposal->status != 'approved') {
	    	return [
	    		'success' => false,
	    		'message' => 'Invalid proposal'
	    	];
	    }

	    $commentObject = new ProposalChangeComment;
	    $commentObject->proposal_change_id = $proposalChangeId;
	    $commentObject->user_id = (int) $user->id;
	    $commentObject->comment = $comment;
	    $commentObject->save();

	    // Proposal Comment Count
	    $proposal->comments = (int) $proposal->comments + 1;
	    $proposal->save();

	    // Proposal Change Comment Count
	    $proposalChange->comments = (int) $proposalChange->comments + 1;
	    $proposalChange->save();

	    return ['success' => true];
		}

		return ['success' => false];
	}

	// Submit Proposal Change
	public function submitProposalChange(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			// Validator
	    $validator = Validator::make($request->all(), [
	    	'proposal' => 'required',
	      'what_section' => 'required',
	      	// 'change_to' => 'required',
	    	// 'additional_notes' => 'required',
		]);
	    if ($validator->fails()) {
	    	return [
	    		'success' => false,
	    		'message' => 'Provide all the necessary information'
	    	];
	    }

	    $proposalId = (int) $request->get('proposal');
	    $proposal = Proposal::find($proposalId);

	    $what_section = $request->get('what_section');
	    $change_to = $request->get('change_to');
		$additional_notes = $request->get('additional_notes');
		if ($what_section != 'extra_notes_update' && !$additional_notes) {
			return [
	    		'success' => false,
	    		'message' => 'Provide all the necessary information'
	    	];
		}

			$grant = (float) $request->get('grant');
			$grant = round($grant, 2);

			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Create Change Record
			$proposalChange = new ProposalChange;
			$proposalChange->proposal_id = $proposalId;
			$proposalChange->user_id = $user->id;
			$proposalChange->what_section = $what_section;
			$proposalChange->change_to = $change_to;
			$proposalChange->additional_notes = $additional_notes;
			if ($grant > 0) $proposalChange->grant = $grant;
			$proposalChange->save();

			// Increase Change Count
			$proposal->changes = (int) $proposal->changes + 1;
			$proposal->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Submit Simple Proposal
	public function submitSimpleProposal(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('member')) {
			// Validator
	    $validator = Validator::make($request->all(), [
	      'title' => 'required',
	      'short_description' => 'required',
	    ]);
	    if ($validator->fails()) {
	    	return [
	    		'success' => false,
	    		'message' => 'Provide all the necessary information'
	    	];
	    }

	    $title = $request->get('title');
			$short_description = $request->get('short_description');

			$proposal = Proposal::where('title', $title)->first();
			if ($proposal) {
				return [
					'success' => false,
					'message' => "Proposal with the same title already exists"
				];
			}

			// Creating Proposal
			$proposal = new Proposal;
			$proposal->title = $title;
			$proposal->short_description = $short_description;
			$proposal->user_id = $user->id;
			$proposal->type = "simple";
			$proposal->save();

			// Emailer
	    $emailerData = Helper::getEmailerData();
	    Helper::triggerAdminEmail('New Proposal', $emailerData);
	    Helper::triggerUserEmail($user, 'New Proposal', $emailerData);

	    return [
				'success' => true,
				'proposal' => $proposal
			];
		}

		return ['success' => false];
	}

	// Submit Simple Proposal
	public function submitAdminGrantProposal(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('member')) {
			// Validator
			$validator = Validator::make($request->all(), [
			'title' => 'required',
			'total_grant' => 'required',
			'things_delivered' => 'required',
			'delivered_at' => 'required',
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$title = $request->get('title');
			$total_grant = $request->get('total_grant');
			$things_delivered = $request->get('things_delivered');
			$delivered_at = $request->get('delivered_at');
			$extra_notes = $request->get('extra_notes');
			$names = $request->get('names');
			$files = $request->file('files');

			$proposal = Proposal::where('title', $title)->first();
			if ($proposal) {
				return [
					'success' => false,
					'message' => "Proposal with the same title already exists"
				];
			}

			// Creating Proposal
			$proposal = new Proposal;
			$proposal->title = $title;

			$proposal->total_grant = $total_grant;
			$proposal->things_delivered = $things_delivered;
			$proposal->delivered_at = $delivered_at;
			$proposal->extra_notes = $extra_notes;

			$proposal->user_id = $user->id;
			$proposal->type = "admin-grant";
			$proposal->save();

			// Add Files
			if (
				$files &&
				$names &&
				is_array($files) &&
				is_array($names) &&
				count($files) == count($names)
			) {
				for ($i = 0; $i < count($files); $i++) {
					$file = $files[$i];
					$name = $names[$i];

					if ($file && $name) { // New File
						$path = $file->store('proposal');
						$url = Storage::url($path);

						$proposalFile = new ProposalFile;
						$proposalFile->proposal_id = $proposal->id;
						$proposalFile->name = $name;
						$proposalFile->path = $path;
						$proposalFile->url = $url;
						$proposalFile->save();
					}
				}
			}

			// Emailer
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('New Proposal', $emailerData);
			Helper::triggerUserEmail($user, 'New Proposal', $emailerData);

			$pdf = PDF::loadView('proposal_pdf', compact('proposal'));
			$fullpath = 'pdf/proposal/proposal_' . $proposal->id . '.pdf';
			Storage::disk('local')->put($fullpath, $pdf->output());
			$url = Storage::disk('local')->url($fullpath);
			$proposal->pdf = $url;
			$proposal->save();
			return [
				'success' => true,
				'proposal' => $proposal
			];
		}

		return ['success' => false];
    }

    public function submitAdvancePaymentProposal(Request $request)
    {
		$user = Auth::user();
		if ($user && $user->hasRole('member')) {
			// Validator
			$validator = Validator::make($request->all(), [
                'total_grant' => 'required',
                'proposal_id' => 'required',
                'amount_advance_detail' => 'required',
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}
            $proposalRequest = Proposal::where('status', '!=', 'completed')->where('id', $request->proposal_id)->first();
            if(!$proposalRequest ) {
                return [
					'success' => false,
					'message' => 'Provide request invalid'
				];
            }
			$names = $request->get('names');
			$files = $request->file('files');
            $title = "Payment advance for grant $request->proposal_id";
			$proposal = Proposal::where('title', $title)->first();
			if ($proposal) {
				return [
					'success' => false,
					'message' => "Proposal with the same title already exists"
				];
			}

			// Creating Proposal
			$proposal = new Proposal;
			$proposal->title = $title;
			$proposal->total_grant = $request->total_grant;
			$proposal->proposal_request_payment = $request->proposal_id;
			$proposal->amount_advance_detail = $request->amount_advance_detail;
			$proposal->user_id = $user->id;
			$proposal->type = "advance-payment";
			$proposal->proposal_advance_status = "requested";
			$proposal->save();

			$proposalRequest->proposal_request_from = $proposal->id;
			$proposalRequest->save();

			// Add Files
			if (
				$files &&
				$names &&
				is_array($files) &&
				is_array($names) &&
				count($files) == count($names)
			) {
				for ($i = 0; $i < count($files); $i++) {
					$file = $files[$i];
					$name = $names[$i];

					if ($file && $name) { // New File
						$path = $file->store('proposal');
						$url = Storage::url($path);

						$proposalFile = new ProposalFile;
						$proposalFile->proposal_id = $proposal->id;
						$proposalFile->name = $name;
						$proposalFile->path = $path;
						$proposalFile->url = $url;
						$proposalFile->save();
					}
				}
			}

			// Emailer
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('New Proposal', $emailerData);
			Helper::triggerUserEmail($user, 'New Proposal', $emailerData);

			$pdf = PDF::loadView('proposal_pdf', compact('proposal'));
			$fullpath = 'pdf/proposal/proposal_' . $proposal->id . '.pdf';
			Storage::disk('local')->put($fullpath, $pdf->output());
			$url = Storage::disk('local')->url($fullpath);
			$proposal->pdf = $url;
			$proposal->save();
			return [
				'success' => true,
				'proposal' => $proposal
			];
		}

		return ['success' => false];
    }

	// Check Sponsor Code
	public function checkSponsorCode(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('participant')) {
			$code = $request->get('code');
			if (!$code) return ['success' => false];

			$codeObject = SponsorCode::with(['user', 'user.profile'])
																->has('user')
																->has('user.profile')
																->where('code', $code)
																->where('used', 0)->first();

			if ($codeObject) {
				return [
					'success' => true,
					'codeObject' => $codeObject
				];
			}
		}

		return ['success' => false];
	}

	// Get Sponsor Codes
	public function getSponsorCodes(Request $request) {
		$user = Auth::user();
		$codes = [];

		if ($user && $user->hasRole('member')) {
			$sort_key = $sort_direction = $search = '';
			$data = $request->all();
			if ($data && is_array($data)) extract($data);

			if (!$sort_key) $sort_key = 'sponsor_codes.id';
			if (!$sort_direction) $sort_direction = 'desc';

			$codes = SponsorCode::where('user_id', $user->id)
													->orderBy($sort_key, $sort_direction)
													->get();
		}

		return [
			'success' => true,
			'codes' => $codes
		];
	}

	// Revoke Sponsor Code
	public function revokeSponsorCode($codeId, Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('member')) {
			$codeObject = SponsorCode::find($codeId);
			if (!$codeObject || $codeObject->user_id != $user->id || $codeObject->used) {
				return [
					'success' => false,
					'message' => 'Invalid sponsor code'
				];
			}

			$codeObject->delete();
			return ['success' => true];
		}

		return ['success' => false];
	}

	// Create New Sponsor Code
	public function createSponsorCode(Request $request) {
		$user = Auth::user();

		if ($user && $user->hasRole('member')) {
			$code = Helper::generateRandomString(6);
			$codeObject = SponsorCode::where('code', $code)->first();
			if ($codeObject) return ['success' => false];

			$codeObject = SponsorCode::where('used', 0)
																->where('user_id', $user->id)
																->first();

			if ($codeObject) {
				return [
					'success' => false,
					'message' => 'You already have an unused code'
				];
			}

			$codeObject = new SponsorCode;
			$codeObject->code = $code;
			$codeObject->used = 0;
			$codeObject->user_id = $user->id;
			$codeObject->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Submit Milestone
	public function submitMilestone(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			// Validator
			$validator = Validator::make($request->all(), [
				'proposalId' => 'required',
				'milestoneId' => 'required',
				'url' => 'required',
				'comment' => 'required',
				'attest_accepted_definition' => 'required|in:1',
				'attest_accepted_pm' => 'required|in:0,1',
				'attest_submitted_accounting' => 'required|in:1',
				'attest_work_adheres' => 'required|in:1',
				'attest_submitted_corprus' => 'required|in:1',
				'attest_accept_crdao' => 'required|in:1',
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$proposalId = (int) $request->get('proposalId');
			$milestoneId = (int) $request->get('milestoneId');
			$url = $request->get('url');
			$comment = $request->get('comment');

			$finalGrant = FinalGrant::where('proposal_id', $proposalId)->where('status', 'active')->first();
			$milestone = Milestone::find($milestoneId);

			if (
				!$finalGrant || !$milestone
			) {
				return [
					'success' => false,
					'message' => 'Invalid grant',
				];
			}
			$milestone->url = $url;
			$milestone->comment = $comment;
			$milestone->submitted_time = now();
			$milestone->attest_accepted_definition = $request->attest_accepted_definition;
			$milestone->attest_accepted_pm = $request->attest_accepted_pm;
			$milestone->attest_submitted_accounting = $request->attest_submitted_accounting;
			$milestone->attest_work_adheres = $request->attest_work_adheres;
			$milestone->attest_submitted_corprus = $request->attest_submitted_corprus;
			$milestone->attest_accept_crdao = $request->attest_accept_crdao;
			$milestone->save();
			$position = Helper::getPositionMilestone($milestone);
			Helper::createGrantTracking($proposalId, "Milestone $position submitted", 'milestone_' .$position.'_submitted');
			$setting = Helper::getSettings();
			if ($setting['gate_new_milestone_votes'] == 'yes') {
				$milestoneReview = MilestoneReview::where('milestone_id', $milestoneId)->orderBy('id', 'desc')->first();
				if ($milestoneReview && ($milestoneReview->status == 'pending' || $milestoneReview->status == 'active')) {
					return [
						'success' => false,
						'message' => "Cannot submit milestone because it's waiting for review!",
					];
				}
				Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'OP', 'User submitted the work for review.');
				$milestone->time_submit = $milestone->time_submit +1;
				$milestone->save();
				$statusReview = 'pending';
				$milestoneReview = new MilestoneReview();
				$milestoneReview->milestone_id = $milestoneId;
				$milestoneReview->proposal_id = $proposalId;
				$milestoneReview->status = $statusReview;
				$milestoneReview->time_submit = $milestone->time_submit;
				$milestoneReview->save();
				Helper::createMilestoneSubmitHistory($milestone, $user->id, $milestoneReview->id);
				return ['success' => true];
			} else {
				Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'OP', 'User submitted the work for review.');
				$milestone->time_submit = $milestone->time_submit + 1;
				$milestone->save();
				Helper::createMilestoneSubmitHistory($milestone, $user->id, null);
				$vote = Vote::where('proposal_id', $proposalId)
					->where(
						'type',
						'informal'
					)
					->where('content_type', 'milestone')
					->where('milestone_id', $milestoneId)
					->first();

				if (!$vote) {
					Helper::createMilestoneLog($milestoneId, null, null, 'System', 'Vote started');
					$milestonePosition = Helper::getPositionMilestone($milestone);
					Helper::createGrantTracking($milestone->proposal_id, "Milestone $milestonePosition started informal vote", 'milestone_' . $milestonePosition . '_started_informal_vote');
					// Submit
					$vote = new Vote;
					$vote->proposal_id = $proposalId;
					$vote->type = "informal";
					$vote->status = "active";
					$vote->content_type = "milestone";
					$vote->milestone_id = $milestoneId;
					$vote->save();

					$finalGrant->milestones_submitted = (int) $finalGrant->milestones_submitted + 1;
					$finalGrant->save();



					$emailerData = Helper::getEmailerData();
					Helper::triggerUserEmail($user, 'Milestone Submitted', $emailerData);

					return ['success' => true];
				} else {
					// Re-Submit
					$finalVote = Vote::where('proposal_id', $proposalId)
						->where('type', 'formal')
						->where('content_type', 'milestone')
						->where('milestone_id', $milestoneId)
						->orderBy('id', 'desc')
						->first();

					if ($finalVote && $finalVote->result == "fail") {
						Helper::createMilestoneLog($milestoneId, null, null, 'System', 'Vote re-started');
						// Submit
						$vote = new Vote;
						$vote->proposal_id = $proposalId;
						$vote->type = "informal";
						$vote->status = "active";
						$vote->content_type = "milestone";
						$vote->milestone_id = $milestoneId;
						$vote->save();

						return ['success' => true];
					}
				}
			}
		}

		return ['success' => false];
	}

	// Submit Proposal
	public function submitProposal(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			// Validator
			$validator = Validator::make($request->all(), [
				'title' => 'required',
				'short_description' => 'required',
				'explanation_benefit' => 'required',
				//'explanation_goal' => 'required',
				'total_grant' => 'required',
				'resume' => 'required',
				//   'extra_notes' => 'required',
				// 'citations' => 'required|array',
				// 'members' => 'required|array',
				'grants' => 'required|array',
				'milestones' => 'required|array',
				'relationship' => 'required'
				// 'previous_work' => 'required',
				// 'other_work' => 'required',
				//   'formField1' => 'required',
				//   'formField2' => 'required',
				//   'purpose' => 'required'
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$include_membership = (int) $request->get('include_membership');
			$member_reason = $request->get('member_reason');
			$member_benefit = $request->get('member_benefit');
			$linkedin = $request->get('linkedin');
			$github = $request->get('github');

			if ($include_membership) {
				if (!$member_reason || !$member_benefit) {
					return [
						'success' => false,
						'message' => 'Provide all the necessary information'
					];
				}
			}

			$title = $request->get('title');
			$short_description = $request->get('short_description');
			$explanation_benefit = $request->get('explanation_benefit');
			// $explanation_goal = $request->get('explanation_goal');

			$license = (int) $request->get('license');
			$license_other = $request->get('license_other');

			$resume = $request->get('resume');
			$extra_notes = $request->get('extra_notes');

			$total_grant = (float) $request->get('total_grant');

			$members = $request->get('members');
			$grants = $request->get('grants');
			$milestones = $request->get('milestones');
			$citations = $request->get('citations');

			$bank_name = $request->get('bank_name');
			$iban_number = $request->get('iban_number');
			$swift_number = $request->get('swift_number');
			$holder_name = $request->get('holder_name');
			$account_number = $request->get('account_number');
			$bank_address = $request->get('bank_address');
			$bank_city = $request->get('bank_city');
			$bank_country = $request->get('bank_country');
			$bank_zip = $request->get('bank_zip');
			$holder_address = $request->get('holder_address');
			$holder_city = $request->get('holder_city');
			$holder_country = $request->get('holder_country');
			$holder_zip = $request->get('holder_zip');

			$crypto_type = $request->get('crypto_type');
			$crypto_address = $request->get('crypto_address');

			$relationship = $request->get('relationship');

			$received_grant_before = (int) $request->get('received_grant_before');
			$grant_id = $request->get('grant_id');
			$has_fulfilled = (int) $request->get('has_fulfilled');
			$previous_work = $request->get('previous_work');
			$other_work = $request->get('other_work');
			// $received_grant = (int) $request->get('received_grant');
			// $foundational_work = $request->get('foundational_work');

			// $yesNo1 = (int) $request->get('yesNo1');
			// $yesNo1Exp = $request->get('yesNo1Exp');
			// $yesNo2 = (int) $request->get('yesNo2');
			// $yesNo2Exp = $request->get('yesNo2Exp');
			// $yesNo3 = (int) $request->get('yesNo3');
			// $yesNo3Exp = $request->get('yesNo3Exp');
			// $yesNo4 = (int) $request->get('yesNo4');
			// $yesNo4Exp = $request->get('yesNo4Exp');
			// $formField1 = $request->get('formField1');
			// $formField2 = $request->get('formField2');
			// $purpose = $request->get('purpose');
			// $purposeOther = $request->get('purposeOther');
			$tags = $request->get('tags');

			$memberRequired = (int) $request->get('memberRequired');

			if ($memberRequired && (!$members || !count($members))
			) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$proposal = Proposal::where('title', $title)->first();
			if ($proposal) {
				return [
					'success' => false,
					'message' => "Proposal with the same title already exists"
				];
			}

			$codeObject = null;
			ProposalDraft::where('user_id', $user->id)->where('title', $title)->delete();
			// Creating Proposal
			$proposal = new Proposal;
			$proposal->title = $title;
			$proposal->short_description = $short_description;
			$proposal->explanation_benefit = $explanation_benefit;
			//$proposal->explanation_goal = $explanation_goal;
			$proposal->total_grant = $total_grant;
			$proposal->license = $license;
			$proposal->resume = $resume;
			$proposal->extra_notes = $extra_notes;
			if ($license_other)
				$proposal->license_other = $license_other;
			$proposal->relationship = $relationship;
			$proposal->received_grant_before = $received_grant_before;
			if ($received_grant_before) {
				$proposal->grant_id = $grant_id;
				$proposal->has_fulfilled = $has_fulfilled;
			}
			$proposal->previous_work = $previous_work;
			$proposal->other_work = $other_work;
			// $proposal->received_grant = $received_grant;
			// if ($received_grant)
			// 	$proposal->foundational_work = $foundational_work;
			$proposal->user_id = $user->id;
			$proposal->include_membership = $include_membership;
			$proposal->member_reason = $member_reason;
			$proposal->member_benefit = $member_benefit;
			$proposal->linkedin = $linkedin;
			$proposal->github = $github;

			if ($codeObject)
				$proposal->sponsor_code_id = $codeObject->id;

			// $proposal->yesNo1 = $yesNo1;
			// $proposal->yesNo2 = $yesNo2;
			// $proposal->yesNo3 = $yesNo3;
			// $proposal->yesNo4 = $yesNo4;

			// if ($yesNo1) $proposal->yesNo1Exp = $yesNo1Exp;
			// if ($yesNo2) $proposal->yesNo2Exp = $yesNo2Exp;
			// if (!$yesNo3) $proposal->yesNo3Exp = $yesNo3Exp;
			// if ($yesNo4) $proposal->yesNo4Exp = $yesNo4Exp;

			// $proposal->formField1 = $formField1;
			// $proposal->formField2 = $formField2;

			// entity
			$isCompanyOrOrganization = (int) $request->get('is_company_or_organization');
			$nameEntity = $request->get('name_entity');
			$entityCountry = $request->get('entity_country');
			$proposal->is_company_or_organization = $isCompanyOrOrganization;
			if ($isCompanyOrOrganization) {
				$proposal->name_entity = $nameEntity;
				$proposal->entity_country = $entityCountry;
			}

			// mentor
			$haveMentor = (int) $request->get('have_mentor');
			$nameMentor = $request->get('name_mentor');
			$totalHoursMentor = $request->get('total_hours_mentor');
			$proposal->have_mentor = $haveMentor;
			if ($haveMentor) {
				$proposal->name_mentor = $nameMentor;
				$proposal->total_hours_mentor = $totalHoursMentor;
			}

			$agree1 = (int) $request->get('agree1');
			$agree2 = (int) $request->get('agree2');
			$agree3 = (int) $request->get('agree3');
			$proposal->agree1 = $agree1;
			$proposal->agree2 = $agree2;
			$proposal->agree3 = $agree3;

			// $proposal->purpose = $purpose;
			// $proposal->purposeOther = $purposeOther;

			if ($tags && count($tags))
			$proposal->tags = implode(",", $tags);

			$proposal->member_required = $memberRequired;
			$proposal->save();


			if ($codeObject) {
				$codeObject->used = 1;
				$codeObject->proposal_id = $proposal->id;
				$codeObject->save();
			}

			// Creating Team
			if ($memberRequired) {
				foreach ($members as $member) {
					$full_name = $bio = $address = $city = $zip = $country = '';
					extract($member);

					if (
						$full_name &&
						$bio
					) {
						$team = new Team;
						$team->full_name = $full_name;
						$team->bio = $bio;
						$team->address = $address;
						$team->city = $city;
						$team->zip = $zip;
						$team->country = $country;
						$team->proposal_id = (int) $proposal->id;
						$team->save();
					}
				}
			}

			// Creating Grant
			foreach ($grants as $grantData) {
				$type = -1;
				$grant = $percentage = 0;
				$type_other = '';
				extract($grantData);

				$type = (int) $type;
				$percentage = (int) $percentage;
				$grant = (float) $grant;

				if ($type >= 0 && $grant) {
					$grantModel = new Grant;
					$grantModel->type = $type;
					$grantModel->grant = $grant;
					if ($type_other)
						$grantModel->type_other = $type_other;
					$grantModel->proposal_id = (int) $proposal->id;
					$grantModel->percentage = $percentage;
					$grantModel->save();
				}
			}

			// Creating Bank
			$bank = new Bank;
			$bank->proposal_id = (int) $proposal->id;
			if ($bank_name)
			$bank->bank_name = $bank_name;
			if ($iban_number)
				$bank->iban_number = $iban_number;
			if ($swift_number)
				$bank->swift_number = $swift_number;
			if ($holder_name)
				$bank->holder_name = $holder_name;
			if ($account_number)
				$bank->account_number = $account_number;
			if ($bank_address)
				$bank->bank_address = $bank_address;
			if ($bank_city)
			$bank->bank_city = $bank_city;
			if ($bank_zip)
			$bank->bank_zip = $bank_zip;
			if ($bank_country)
				$bank->bank_country = $bank_country;
			if ($holder_address)
				$bank->address = $holder_address;
			if ($holder_city)
				$bank->city = $holder_city;
			if ($holder_zip)
				$bank->zip = $holder_zip;
			if ($holder_country)
				$bank->country = $holder_country;
			$bank->save();

			// Creating Crypto
			$crypto = new Crypto;
			$crypto->proposal_id = (int) $proposal->id;
			if ($crypto_address)
				$crypto->public_address = $crypto_address;
			if ($crypto_type)
				$crypto->type = $crypto_type;
			$crypto->save();

			// Creating Milestone
			foreach ($milestones as $milestoneData) {
				$title = $details = $criteria = $deadline = $level_difficulty = '';
				$grant = 0;
				extract($milestoneData);
				$grant = (float) $grant;

				if ($grant && $title && $details
				) {
					$milestone = new Milestone;
					$milestone->proposal_id = (int) $proposal->id;
					$milestone->title = $title;
					$milestone->details = $details;
					$milestone->criteria = $criteria;
					// $milestone->kpi = $kpi;
					$milestone->grant = $grant;
					$milestone->deadline = $deadline;
					$milestone->level_difficulty = $level_difficulty;
					$milestone->save();
				}
			}

			// Creating Citation
			if ($citations && count($citations)) {
				foreach ($citations as $citation) {
					if (
						isset($citation['proposalId']) &&
						isset($citation['explanation']) &&
						isset($citation['percentage']) &&
						isset($citation['validProposal']) &&
						isset($citation['checked'])
					) {
						$percentage = (int) $citation['percentage'];
						$repProposalId = (int) $citation['proposalId'];
						$explanation = $citation['explanation'];

						$citation = new Citation;
						$citation->proposal_id = (int) $proposal->id;
						$citation->rep_proposal_id = (int) $repProposalId;
						$citation->explanation = $explanation;
						$citation->percentage = $percentage;
						$citation->save();
					}
				}
			}
			$pdf = PDF::loadView('proposal_pdf', compact('proposal'));
			$fullpath = 'pdf/proposal/proposal_' . $proposal->id . '.pdf';
			Storage::disk('local')->put($fullpath, $pdf->output());
			$url = Storage::disk('local')->url($fullpath);
			$proposal->pdf = $url;
			$proposal->save();
			// save user
			$user->press_dismiss = 1;
			$user->save();
			// save file
			$proposal_draft_id = $request->get('proposal_draft_id');
			if($proposal_draft_id) {
				$proposal_draft_files = ProposalDraftFile::where('proposal_draft_id', $proposal_draft_id)->get();
				foreach($proposal_draft_files as $file) {
					$proposalFile = new ProposalFile;
					$proposalFile->proposal_id = $proposal->id;
					$proposalFile->name = $file->name;
					$proposalFile->path = $file->path;
					$proposalFile->url = $file->url;
					$proposalFile->save();
				}
			}
			// Emailer
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('New Proposal', $emailerData);
			Helper::triggerUserEmail($user, 'New Proposal', $emailerData);

            Helper::createGrantTracking($proposal->id, "Proposal $proposal->id submitted", 'proposal_submitted');
            $shuftipro = Shuftipro::where('user_id', $proposal->user_id)->where('status', 'approved')->first();
            if ($shuftipro) {
                Helper::createGrantTracking($proposal->id, "KYC checks complete", 'kyc_checks_complete');
            }
			return [
				'success' => true,
				'proposal' => $proposal
			];
		}

		return ['success' => false];
	}

	public function associateAgreement()
	{
		$user = Auth::user();
		if ($user && $user->hasRole(['participant', 'member'])) {
			$profile = $user->profile;
			if (!$profile) {
				return ['success' => false];
			}
			$profile->step_review = 1;
			$profile->associate_agreement_at = now();
			$profile->save();
			return ['success' => true];
		}
		return ['success' => false];
	}

	public function pressDismiss() {
		$user = Auth::user();
		$user->press_dismiss = 1;
		$user->save();
		return ['success' => true];
	}

	public function checkActiveGrant() {
		$user = Auth::user();
		$user->check_active_grant = 0;
		$user->save();
		return ['success' => true];
	}

	// Start Formal Milestone Voting
	public function startFormalMilestoneVoting(Request $request, $proposalId) {
		$user = Auth::user();

		if ($user && $user->hasRole(['participant', 'member'])) {
			$voteId = (int) $request->get('voteId');

			$proposal = Proposal::find($proposalId);
			$informalVote = Vote::find($voteId);
			$milestone = Milestone::find($informalVote->milestone_id);

			// Proposal Check
			if (!$proposal || $proposal->user_id != $user->id) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			if (
				!$informalVote ||
				$informalVote->proposal_id != $proposal->id ||
				$informalVote->formal_vote_id ||
				$informalVote->content_type != "milestone"
			) {
				return [
					'success' => false,
					'message' => "Formal vote can't be started"
				];
			}

			$vote = new Vote;
			$vote->proposal_id = $informalVote->proposal_id;
			$vote->type = "formal";
			$vote->status = "active";
			$vote->content_type = "milestone";
			$vote->milestone_id = $informalVote->milestone_id;
			$vote->save();

			$informalVote->formal_vote_id = $vote->id;
			$informalVote->save();
			Helper::createMilestoneLog($informalVote->milestone_id, null, null, 'System', 'Vote started');
			$milestonePosition = Helper::getPositionMilestone($milestone);
			Helper::createGrantTracking($proposal->id, "Milestone $milestonePosition stared formal vote", 'milestone_' . $milestonePosition .'_started_formal_vote');
			// Emailer Admin
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('Vote Started', $emailerData, $proposal, $vote);

			// Emailer Member
	   		Helper::triggerMemberEmail('New Vote', $emailerData, $proposal, $vote);

			return ['success' => true];
		}

		return ['success' => false];
	}

	public function checkFirstCompletedProposal() {
		$user = Auth::user();
		$user->check_first_compeleted_proposal = 0;
		$user->save();
		return ['success' => true];
	}

	public function submitDraftProposal(Request $request)
	{
		$user = Auth::user();
		if ($user && $user->hasRole(['participant', 'member'])) {
			$title = $request->title;
			if (!$title) {
				return [
					'success' => false,
					'message' => 'Title must be has value'
				];
			}
			$user_id = $user->id;
			$proposal_draft =  ProposalDraft::updateOrCreate([
				'title' => $request->title,
				'user_id' => $user_id,
			], [
				'short_description'  => $request->short_description,
				'explanation_benefit'=> $request->explanation_benefit,
				'license'=> $request->license,
				'license_other'  => $request->license_other,
				'total_grant'=> $request->total_grant,
				'member_required'=> $request->member_required,
				'members'=> $request->members ? json_encode( $request->members ) : null,
				'grants' => $request->grants ? json_encode($request->grants) : null,
				'bank_name'  => $request->bank_name,
				'iban_number'=> $request->iban_number,
				'swift_number' => $request->swift_number,
				'holder_name'=> $request->holder_name,
				'account_number' => $request->account_number,
				'bank_address' => $request->bank_address,
				'bank_city'  => $request->bank_city,
				'bank_country'     => $request->bank_country,
				'holder_country' => $request->holder_country,
				'holder_zip' => $request->holder_zip,
				'crypto_type'=> $request->crypto_type,
				'crypto_address' => $request->crypto_address,
				'milestones' => $request->milestones ? json_encode($request->milestones) : null,
				'citations'  => $request->citations ? json_encode($request->citations) : null,
				'relationship' => $request->relationship,
				'received_grant_before'  => $request->received_grant_before,
				'grant_id' => $request->grant_id,
				'has_fulfilled' => $request->has_fulfilled,
				'previous_work' => $request->previous_work,
				'other_work' => $request->other_work,
				'include_membership' => $request->include_membership,
				'member_reason'  => $request->member_reason,
				'member_benefit' => $request->member_benefit,
				'linkedin' => $request->linkedin,
				'github' => $request->github,
				'sponsor_code_id'=> $request->sponsor_code_id,
				'name_entity'=> $request->name_entity,
				'entity_country' => $request->entity_country,
				'have_mentor'=> $request->have_mentor,
				'name_mentor'=> $request->name_mentor,
				'total_hours_mentor' => $request->total_hours_mentor,
				'agree1' => $request->agree1,
				'agree2' => $request->agree2,
				'agree3' => $request->agree3,
				'tags'   => $request->tags ? json_encode($request->tags) : null	,
				'resume' => $request->resume,
				'extra_notes'=> $request->extra_notes,
				'is_company_or_organization'=> $request->is_company_or_organization,
			]);
			return [
				'success' => true,
				'proposal_draft' => $proposal_draft,
			];
		}
		return ['success' => false];
	}

	public function getDraftProposal(Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal_draft.updated_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = 30;
		$start = $limit * ($page_id - 1);

		// Records
		$proposals = ProposalDraft::where('user_id', $user->id)
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();
		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	public function getDraftProposalDetail($id)
	{
		$user = Auth::user();
		$proposal = ProposalDraft::with(['files'])->where('user_id', $user->id)->where('id', $id)->first();
		if ($proposal) {
			return [
				'success' => true,
				'proposal' => $proposal,
			];
		}
		return [
			'success' => false,
			'message' => 'Proposal draft not found'
		];
	}

	public function deleteDraftProposal($id)
	{
		$user = Auth::user();
		$proposal = ProposalDraft::where('user_id', $user->id)->where('id', $id)->first();
		if ($proposal) {
			$proposal->delete();
			return [
				'success' => true,
			];
		}
		return [
			'success' => false,
			'message' => 'Proposal draft not found'
		];
	}

	public function submitSurvey(Request $request, $id)
	{
		$user = Auth::user();
		if ($user->hasRole('member') || true) {
			$survey = Survey::where('id', $id)->where('status', 'active')->first();
			if (!$survey) {
				return [
					'success' => false,
					'message' => 'The survey not exist'
				];
			}
			$survey_result = SurveyResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
			$survey_downvote_result = SurveyDownVoteResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
			if ($survey_result > 0 || ($survey->downvote && $survey_downvote_result > 0)) {
				return [
					'success' => false,
					'message' => 'You submmited survey'
				];
			}
			$rule = [
				'upvote_responses' => 'required|array',
				'upvote_responses.*.proposal_id' => 'required|numeric|min:1|distinct',
				'upvote_responses.*.place_choice' => "required|numeric|distinct|min:1|max:$survey->number_response",
			];
			if ($survey->downvote) {
				$rule = array_merge($rule, [
					'downvote_responses' => 'required|array',
					'downvote_responses.*.proposal_id' => 'required|numeric|min:1|distinct',
					'downvote_responses.*.place_choice' => "required|numeric|distinct|min:1|max:$survey->number_response",
				]);
			}
			$validator = Validator::make($request->all(), $rule);
			if ($validator->fails()) {
				$errors = [];
				foreach (collect($validator->errors()) as $field => $error) {
					$errors[$field] = $error[0];
				}
				return [
					'success' => false,
					'message' => $errors
				];
			}

			// Upvotes
			$upvoteDatas = $request->upvote_responses ?? [];
			$upvoteResult = [];
			foreach ($upvoteDatas as $data) {
				$proposal_id = $data['proposal_id'];
				$proposal = Proposal::where('proposal.status', 'approved')->where('id', $proposal_id)
					->doesntHave('votes')->first();
				if (!$proposal) {
					return [
						'success' => false,
						'message' => "Proposal discusstion $proposal_id does not exist"
					];
				}
				$data['point'] = Helper::getPointSurvey($data['place_choice']);
				$data['user_id'] = $user->id;
				$data['survey_id'] = $id;
				$data['created_at'] = now();
				$data['updated_at'] = now();
				array_push($upvoteResult, $data);
			}
			SurveyResult::where('survey_id', $id)->where('user_id', $user->id)->delete();
			SurveyResult::insert($upvoteResult);

			// Downvotes
			if ($survey->downvote) {
				$downvoteDatas = $request->downvote_responses ?? [];
				$downvoteResult = [];
				foreach ($downvoteDatas as $data) {
					$proposal_id = $data['proposal_id'];
					$proposal = Proposal::where('proposal.status', 'approved')->where('id', $proposal_id)
						->doesntHave('votes')->first();
					if (!$proposal) {
						return [
							'success' => false,
							'message' => "Proposal discusstion $proposal_id does not exist"
						];
					}
					$data['point'] = Helper::getPointSurvey($data['place_choice']);
					$data['user_id'] = $user->id;
					$data['survey_id'] = $id;
					$data['created_at'] = now();
					$data['updated_at'] = now();
					array_push($downvoteResult, $data);
				}
				SurveyDownVoteResult::where('survey_id', $id)->where('user_id', $user->id)->delete();
				SurveyDownVoteResult::insert($downvoteResult);
			}

			// Update submissions complete of survey
			$survey->update(['user_responded' =>  $survey->user_responded + 1]);
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	public function getCurentSurvey()
	{
		$user = Auth::user();
		$survey = Survey::with(['surveyRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyRanks.proposal'])
			->with(['surveyDownvoteRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyDownvoteRanks.proposal'])
			->with(['surveyRfpRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyRfpRanks.surveyRfpBid', 'surveyRfpBids'])

			->orderBy('created_at', 'desc')->first();

		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		$survey_result = SurveyResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
		$survey->is_submitted = $survey_result > 0 ? true : false;
		$time_left = null;
		if ($survey->status == 'active') {
			$time_left = Carbon::parse($survey->end_time)->diff(now())->format('%dd %hh:%mm');;
		}
		$survey->time_left = $time_left;
		return [
			'success' => true,
			'survey' => $survey,
		];
	}

	public function uploadFiletDraftProposal(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposalDraftId = (int) $request->get('proposal_draft_id');
			$proposalDraft = ProposalDraft::where('id', $proposalDraftId)->where('user_id', $user->id)->first();
			$ids_to_remove = $request->get('ids_to_remove');
			$names = $request->get('names');
			$files = $request->file('files');
			if (!$proposalDraft) {
				return [
					'success' => false,
					'message' => 'Invalid proposal draft'
				];
			}

			// Remove Files
			if ($ids_to_remove) {
				$temp = explode(",", $ids_to_remove);
				foreach ($temp as $id) {
					if ((int) $id)
						ProposalDraftFile::where('id', (int) $id)->where('proposal_draft_id', $proposalDraftId)->delete();
				}
			}

			// Add Files
			if (
				$files &&
				$names &&
				is_array($files) &&
				is_array($names) &&
				count($files) == count($names)
			) {
				for ($i = 0; $i < count($files); $i++) {
					$file = $files[$i];
					$name = $names[$i];

					if ($file && $name) { // New File
						$path = $file->store('proposal');
						$url = Storage::url($path);

						$proposalFile = new ProposalDraftFile();
						$proposalFile->proposal_draft_id = $proposalDraftId;
						$proposalFile->name = $name;
						$proposalFile->path = $path;
						$proposalFile->url = $url;
						$proposalFile->save();
					}
				}
			}

			return ['success' => true];
		}

		return ['success' => false];
	}

	public function getListUserVA(Request $request)
	{
		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;
		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		$users = User::join('profile', 'users.id', '=', 'profile.user_id')
		->where('users.is_admin', 0)
		->where('users.is_guest', 0)
		->where('can_access', 1)
		->where('users.is_member', 1)
		->where(function ($query) use ($search) {
			if ($search) {
				$query->where('users.first_name', 'like', '%' . $search . '%')
					->orWhere('users.last_name', 'like', '%' . $search . '%')
					->orWhere('users.email', 'like', '%' . $search . '%');
			}
		})
		->select([
			'users.*',
			'profile.company',
			'profile.dob',
			'profile.country_citizenship',
			'profile.country_residence',
			'profile.address',
			'profile.city',
			'profile.zip',
			'profile.step_review',
			'profile.step_kyc',
			'profile.rep',
			'profile.forum_name',
			'profile.telegram',
		])->get();
		foreach ($users as $user) {
			$member_at = Carbon::parse($user->member_at)->format('Y-m-d');
				$total_informal_votes = Vote::where('type', 'informal')->where('result', '!=', 'no-quorum')
					->where('created_at', '>=', $member_at)
					->whereHas('proposal', function ($query) use ($user) {
						$query->where('proposal.user_id', '!=', $user->id);
					})->count();
				$total_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
				->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')
				->where('vote.result', '!=', 'no-quorum')->where('vote.created_at', '>=', $member_at)->count();
			$user->total_vote_percent = $total_informal_votes > 0 ? ($total_voted / $total_informal_votes) * 100 : 0 ;
			$total_staked = DB::table('reputation')
			->where('user_id', $user->id)
				->where('type', 'Staked')
				->sum('staked');
			$user->total_rep = abs($total_staked) + $user->rep;

			// get last month
			$firstDayofPreviousMonth = Carbon::now()->startOfMonth()->subMonth()->format('Y-m-d');
			$lastDayofPreviousMonth = Carbon::now()->subMonth()->endOfMonth()->format('Y-m-d');
			$start_date = $member_at >= $firstDayofPreviousMonth  ?  $member_at : $firstDayofPreviousMonth;
			$last_month_informal_votes = Vote::where('type', 'informal')
				->where('vote.result', '!=', 'no-quorum')
				->whereHas('proposal', function ($query) use ($user) {
					$query->where('proposal.user_id', '!=', $user->id);
				})
				->where(function ($query) use ($start_date, $lastDayofPreviousMonth) {
					if ($start_date) {
						$query->whereDate('created_at', '>=', $start_date);
					}
					if ($lastDayofPreviousMonth) {
						$query->whereDate('created_at', '<=', $lastDayofPreviousMonth);
					}
				})->count();
			$last_month_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
			->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')
			->where('vote.result', '!=', 'no-quorum')
			->where(function ($query) use ($start_date, $lastDayofPreviousMonth) {
				if ($start_date) {
					$query->whereDate('vote.created_at', '>=', $start_date);
				}
				if ($lastDayofPreviousMonth) {
					$query->whereDate('vote.created_at', '<=', $lastDayofPreviousMonth);
				}
			})->count();
			$user->last_month_vote_percent = $last_month_informal_votes > 0 ? ($last_month_voted / $last_month_informal_votes) * 100 : 0 ;

			$firstDayofMonth = Carbon::now()->startOfMonth()->format('Y-m-d');
			$start_date = $member_at >= $firstDayofMonth  ?  $member_at : $firstDayofMonth;
			$this_month_informal_votes = Vote::where('type', 'informal')
				->where('vote.result', '!=', 'no-quorum')
				->whereHas('proposal', function ($query) use ($user) {
					$query->where('proposal.user_id', '!=', $user->id);
				})
				->where(function ($query) use ($start_date) {
					if ($start_date) {
						$query->whereDate('created_at', '>=', $start_date);
					}
				})->count();
			$this_month_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
			->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')
			->where('vote.result', '!=', 'no-quorum')
			->where(function ($query) use ($start_date) {
				if ($start_date) {
					$query->whereDate('vote.created_at', '>=', $start_date);
				}
			})->count();
			$user->this_month_vote_percent = $this_month_voted > 0 ? ($this_month_voted / $this_month_informal_votes) * 100 : 0 ;
		}
		if ($sort_direction == 'asc') {
			$sorted = $users->sortBy($sort_key)->values();
		} else {
			$sorted = $users->sortByDesc($sort_key)->values();
		}
		$response = $sorted->slice($start, $limit)->values();
		return [
			'success' => true,
			'total_members' => Helper::getTotalMembers(),
			'users' => $response,
			'finished' => count($response) < $limit ? true : false
		];
	}

	public function sendKycKangaroo()
	{
		$user = Auth::user();
		$shuftipro_temp = ShuftiproTemp::where('user_id', $user->id)->first();
		$invite_id =  $shuftipro_temp->invite_id ?? null;
		$shuftipro = Shuftipro::where('user_id', $user->id)->where('status', 'approved')->first();
		if(!$shuftipro) {
			ShuftiproTemp::where('user_id', $user->id)->delete();
			$kyc_response = Helper::inviteKycKangaroo("$user->first_name $user->last_name", $user->email, $invite_id);
			if(isset($kyc_response['success']) && $kyc_response['success'] == false) {
				Helper::processKycKangaroo($kyc_response, $user->id);
				return [
					'success' => false,
					'message' => $kyc_response['message'] ?? '',
					'invite' => $kyc_response['invite'] ?? ''
				];
			}
			$shuftipro_temp = new ShuftiproTemp();
			$shuftipro_temp->user_id = $user->id;
			$shuftipro_temp->reference_id = '';
			$shuftipro_temp->status = 'booked';
			$shuftipro_temp->invite_id = $kyc_response['invite_id'] ?? null;
			$shuftipro_temp->invited_at = now();
			$shuftipro_temp->save();
			return [
				'success' => true,
			];
		} else {
			return [
				'success' => false,
			];
		}
	}

	// Get Reputation Track
	public function exportCSVReputationTrack(Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = $search = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'reputation.id';
		if (!$sort_direction) $sort_direction = 'desc';

		$items = Reputation::leftJoin('proposal', 'proposal.id', '=', 'reputation.proposal_id')
		->leftJoin('users', 'users.id', '=', 'proposal.user_id')
		->where('reputation.user_id', $user->id)
		->where(function ($query) use ($search) {
			if ($search) {
				$query->where('proposal.title', 'like', '%' . $search . '%')
				->orWhere('reputation.type', 'like', '%' . $search . '%');
			}
		})
		->select([
			'reputation.*',
			'proposal.include_membership',
			'proposal.title as proposal_title',
			'users.first_name as op_first_name',
			'users.last_name as op_last_name'
		])
		->orderBy($sort_key, $sort_direction)
		->get();
		return Excel::download(new MyReputationExport($items), 'my_reputation.csv');
	}

	public function checkMentor(Request $request)
	{
		$user = Auth::user();
		$user = User::where('users.is_admin', 0)->where('users.id', '!=', $user->id)
		->join('profile', 'users.id', '=', 'profile.user_id')
		->where(function ($query) use ($request) {
				$query->where('users.email', $request->name_mentor)
				->orWhere('profile.forum_name',$request->name_mentor);
		})->first();
		if($user) {
			return [
				'success' => true,
				'user' => $user,
			];
		} else {
			return ['success' => false];
		}
	}

	public function settingDailyCSVReputation(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'setting' => 'required|in:0,1',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'setting should be 0 or 1'
			];
		}
		$user = Auth::user();
		$setting = $request->setting;
		$user->notice_send_reputation = $setting;
		$user->save();
		return [
			'success' => true,
		];
	}

	public function checkSendKyc() {
		$user = Auth::user();
		$user->check_send_kyc = 0;
		$user->save();
		return ['success' => true];
	}

	public function getMilestoneNotSubmit($proposalId)
	{
		$user = Auth::user();
		$milestones = Milestone::where('milestone.proposal_id', $proposalId)
			->doesntHave('votes')
			->leftJoin('milestone_review', 'milestone.id', '=', 'milestone_review.milestone_id')
			->where(function ($query) {
				$query->where('milestone_review.status', 'denied')
				->orWhere('milestone_review.status', null);
			})
			->select(['milestone.*'])
			->orderBy('milestone.created_at', 'asc')
			->get();
		foreach ($milestones as $milestone) {
			$milestone->milestone_posittion = Helper::getPositionMilestone($milestone);
		}
		return [
			'success' => true,
			'milestones' => $milestones,
		];
	}

	public function submitDownVoteSurvey(Request $request, $id)
	{
		$user = Auth::user();
		if ($user->hasRole('member')) {
			$survey = Survey::where('id', $id)->where('status', 'active')->first();
			if (!$survey) {
				return [
					'success' => false,
					'message' => 'The survey not exist'
				];
			}
			$survey_downvote_result = SurveyDownVoteResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
			if ($survey_downvote_result > 0) {
				return [
					'success' => false,
					'message' => 'You submmited survey'
				];
			}
			$validator = Validator::make($request->all(), [
				'responses.*.proposal_id' => 'required|numeric|min:1|distinct',
				'responses.*.place_choice' => "required|numeric|distinct|min:1|max:$survey->number_response",
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => $validator->errors()
				];
			}
			$datas = $request->responses;
			$result = [];
			foreach ($datas as $data) {
				$proposal_id = $data['proposal_id'];
				$proposal = Proposal::where('proposal.status', 'approved')->where('id', $proposal_id)
					->doesntHave('votes')->first();
				if (!$proposal) {
					return [
						'success' => false,
						'message' => "Proposal discusstion $proposal_id does not exist"
					];
				}
				$data['point'] = Helper::getPointSurvey($data['place_choice']);
				$data['user_id'] = $user->id;
				$data['survey_id'] = $id;
				$data['created_at'] = now();
				$data['updated_at'] = now();
				array_push($result, $data);
			}
			SurveyDownVoteResult::where('survey_id', $id)->where('user_id', $user->id)->delete();
			SurveyDownVoteResult::insert($result);
			$survey->update(['user_responded' =>  $survey->user_responded + 1]);
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	public function getProposalRequestPayment(Request $request)
	{
        $user = Auth::user();
		$propossal_request_payment_ids = Proposal::whereNotNull('proposal_request_payment')->pluck('proposal_request_payment');
		$propossal_request_from_ids = Proposal::whereNotNull('proposal_request_from')->pluck('proposal_request_from');
		$proposals = Proposal::where('status', '!=', 'completed')->where('type', 'grant')->where('user_id', $user->id)
			->whereNotIn('id',$propossal_request_payment_ids->toArray())
			->whereNotIn('id', $propossal_request_from_ids->toArray())
			->get();
		return [
			'success' => true,
			'proposals' => $proposals,
		];

	}

	public function checkShowUnvotedInformal(Request $request) {
		$user = Auth::user();
		$user->show_unvoted_informal = $request->check;
		$user->save();
		return ['success' => true];
	}

	public function checkShowUnvotedFormal(Request $request) {
		$user = Auth::user();
		$user->show_unvoted_formal = $request->check;
		$user->save();
		return ['success' => true];
	}

	public function getShuftiproStatus(Request $request)
	{
		$user = Auth::user();
		$shuftiproTemp = ShuftiproTemp::where('user_id', $user->id)->first();

		if ($shuftiproTemp->reference_id ?? false) {
			$keys = [
			'production' => [
				'clientId' => config('services.shuftipro.client_id_prod'),
				'clientSecret' => config('services.shuftipro.client_secret_prod'),
			],
			'test' => [
				'clientId' => config('services.shuftipro.client_id_test'),
				'clientSecret' => config('services.shuftipro.client_secret_test'),
			]
			];

			$mode = 'production';

			$url = 'https://api.shuftipro.com/status';
			$client_id  = $keys[$mode]['clientId'];
			$secret_key = $keys[$mode]['clientSecret'];

			$response = Http::withBasicAuth($client_id, $secret_key)->post($url, [
				'reference' => $shuftiproTemp->reference_id
			]);

			if ($response->successful()) {
				return [
					'success' => true,
					'result' => $response->json(),
				];
			}
		}

		return [
			'success' => false
		];
	}

	public function submitSurveyRfp(Request $request, $id)
	{
		$user = Auth::user();
		if ($user->hasRole('member')) {
			$survey = Survey::where('id', $id)->where('status', 'active')->where('type', 'rfp')->first();
			if (!$survey) {
				return [
					'success' => false,
					'message' => 'The survey not exist'
				];
			}
			$survey_rfp_result = SurveyRfpResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
			if ($survey_rfp_result > 0) {
				return [
					'success' => false,
					'message' => 'You submmited survey'
				];
			}
			$validator = Validator::make($request->all(), [
				'responses.*.bid' => 'required|numeric|min:1|distinct',
				'responses.*.place_choice' => "required|numeric|distinct|min:1|max:$survey->number_response",
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => $validator->errors()
				];
			}
			$datas = $request->responses;
			$result = [];
			foreach ($datas as $data) {
				$bid = SurveyRfpBid::where('survey_id', $id)->where('bid', $data['bid'])->first();
				$data['bid_id'] = $bid->id;
				$data['bid'] = $data['bid'];
				$data['point'] = Helper::getPointSurvey($data['place_choice']);
				$data['user_id'] = $user->id;
				$data['survey_id'] = $id;
				$data['created_at'] = now();
				$data['updated_at'] = now();
				array_push($result, $data);
			}
			SurveyRfpResult::where('survey_id', $id)->where('user_id', $user->id)->delete();
			SurveyRfpResult::insert($result);
			$survey->update(['user_responded' =>  $survey->user_responded + 1]);
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	public function getSurveys(Request $request)
	{
		$user = Auth::user();

		// Variables
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = $request->limit ?? 15;
		$start = $limit * ($page_id - 1);
		$status = $request->status == 'active' ? 'active' : 'completed';
		$type = $request->type == 'grant' ? 'grant' : 'rfp';
		$total_member = Helper::getTotalMembers();
		// Records
		$survey_ids_vote = SurveyResult::where('user_id', $user->id)->distinct('survey_id')->pluck('survey_id')->toArray();
		$survey_ids_downvote = SurveyDownVoteResult::where('user_id', $user->id)->distinct('survey_id')->pluck('survey_id')->toArray();
		$survey_ids_rfp = SurveyRfpResult::where('user_id', $user->id)->distinct('survey_id')->pluck('survey_id')->toArray();
		$survey_ids = array_merge($survey_ids_vote, $survey_ids_downvote, $survey_ids_rfp);
		$surveys = Survey::where('status', $status)
		->where('type', $type)
		->where(function ($query) use ($survey_ids, $status) {
			if ($status == 'completed') {
				$query->whereIn('id', $survey_ids);
			}
		})
		->with(['surveyResults' => function ($q) use ($user) {
			$q->where('user_id', $user->id)->with(['proposal'])
				->orderBy('place_choice', 'asc');
		}])
		->with(['surveyDownvoteResults' => function ($q) use ($user) {
			$q->where('user_id', $user->id)->with(['proposal'])
				->orderBy('place_choice', 'asc');
		}])
		->with(['surveyRfpResults' => function ($q) use ($user) {
			$q->where('user_id', $user->id)->with(['surveyRfpBid'])
				->orderBy('place_choice', 'asc');
		}])
		->orderBy($sort_key, $sort_direction)
		->offset($start)
		->limit($limit)
		->get();
		$now = Carbon::now();
		foreach ($surveys as $survey) {
            $survey->total_member = $total_member;
            if($status == 'active') {
				$end_date = Carbon::createFromFormat("Y-m-d H:i:s", $survey->end_time, "UTC");
				$survey->hours_left = $end_date->diffInHours($now);
			}
		}
		return [
			'success' => true,
			'surveys' => $surveys,
			'finished' => count($surveys) < $limit ? true : false
		];
	}

	public function getSurveyDetail($id)
	{
		$user = Auth::user();
		$survey = Survey::with(['surveyRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyRanks.proposal'])
			->with(['surveyDownvoteRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyDownvoteRanks.proposal'])
			->with(['surveyRfpRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyRfpRanks.surveyRfpBid', 'surveyRfpBids'])

			->where('id', $id)->first();

		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		$survey_result = SurveyResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
		$survey_rfp_result = SurveyRfpResult::where('survey_id', $survey->id)->where('user_id', $user->id)->count();
		$survey->is_submitted = ($survey_result > 0 || $survey_rfp_result > 0) ? true : false;
		$time_left = null;
		if ($survey->status == 'active') {
			$time_left = Carbon::parse($survey->end_time)->diff(now())->format('%dd %hh:%mm');;
		}
		$survey->time_left = $time_left;
		return [
			'success' => true,
			'survey' => $survey,
		];
	}
}
