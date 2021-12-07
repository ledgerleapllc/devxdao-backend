<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

use App\Http\Helper;

use App\User;
use App\PreRegister;
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
use App\Milestone;
use App\Citation;
use App\Team;
use App\Setting;
use App\Vote;
use App\VoteResult;
use App\OnBoarding;
use App\Reputation;
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerAdmin;
use App\Exports\ProposalExport;
use App\FinalGrant;
use App\GrantTracking;
use App\Signature;
use App\Exports\VoteResultExport;

use App\Mail\TwoFA;
use App\Mail\AdminAlert;
use App\Mail\UserAlert;
use App\MilestoneReview;
use App\ShuftiproTemp;
use App\SignatureGrant;
use App\Survey;
use App\SurveyDownVoteRank;
use App\SurveyRank;
use Carbon\Carbon;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use PDF;

class SharedController extends Controller
{
	// Hellosign Hook
	public function hellosignHook(Request $request)
	{
		$payload = $request->get('json');
		if (!$payload) return "error";

		$data = json_decode($payload, true);
		$api_key = config('services.hellosign.api_key');
		if (!is_array($data)) return "error";

		$md5_header_check = base64_encode(hash_hmac('md5', $payload, $api_key));
		$md5_header = $request->header('Content-MD5');

		if ($md5_header != $md5_header_check)
			return "error";
		// Get Settings
		$settings = Helper::getSettings();
		$emailerData = Helper::getEmailerData();
		// Valid Request
		if (
			isset($data['event']) &&
			$data['event']['event_type'] == 'signature_request_all_signed' &&
			isset($data['signature_request'])
		) {
			$signature_request_id = $data['signature_request']['signature_request_id'];
			$filepath = 'hellosign/hellosign_' . $signature_request_id . '.pdf';

			// $client = new \HelloSign\Client(config('services.hellosign.api_key'));
			// $client->getFiles($signature_request_id, $filepath, \HelloSign\SignatureRequest::FILE_TYPE_PDF);

			$profile = Profile::where('signature_request_id', $signature_request_id)->first();
			$proposal = Proposal::where('signature_request_id', $signature_request_id)->first();
			$proposalGrant = Proposal::where('signature_grant_request_id', $signature_request_id)->first();

			if ($profile) {
				$profile->hellosign_form = $filepath;
				$profile->save();
			} else if ($proposal) {
				$proposal->hellosign_form = $filepath;
				$proposal->save();
				$signObject = Signature::where('proposal_id', $proposal->id)->where('signed', 0)->first();
				if ($signObject) {
					$signObject->signed = 1;
					$signObject->save();
					$informalVote = Vote::where('proposal_id',  $proposal->id)
						->where('type', 'informal')
						->where('content_type', '!=', 'milestone')
						->where('status', 'completed')
						->first();
					if ($informalVote
						&& $proposal->type == 'grant'
						&& ($settings['autostart_grant_formal_votes'] ?? null) == 'yes'
					) {
						$vote = Helper::startFormalVote($informalVote);
						if ($vote) {
							// Emailer Admin
							Helper::triggerAdminEmail('Vote Started', $emailerData, $proposal, $vote);

							// Emailer Member
							Helper::triggerMemberEmail('New Vote', $emailerData, $proposal, $vote);
						}
					}
				}
			} else if ($proposalGrant) {
				$proposalGrant->grant_hellosign_form = $filepath;
				$proposalGrant->save();
				$signObject = SignatureGrant::where('proposal_id', $proposalGrant->id)->where('signed', 0)->first();
				if ($signObject) {
					$signObject->signed = 1;
					$signObject->save();

					$finalGrant = FinalGrant::where('proposal_id', $proposalGrant->id)->first();
					Helper::createGrantLogging([
						'proposal_id' => $proposalGrant->id,
						'final_grant_id' => $finalGrant->id,
						'user_id' => $signObject->user_id,
						'email' => $signObject->email,
						'role' => $signObject->role,
						'type' => 'signed',
					]);
				}
			} else {
				$proposal = Proposal::where('membership_signature_request_id', $signature_request_id)->first();
				if ($proposal) {
					$proposal->membership_hellosign_form = $filepath;
					$proposal->save();

					$op = User::find($proposal->user_id);
					if ($op) {
						Helper::upgradeToVotingAssociate($op);

						$emailerData = Helper::getEmailerData();
						Helper::triggerUserEmail($op, 'New Voting Associate', $emailerData);
					}
					Helper::completeProposal($proposal);
				}
			}
		} else if (
			isset($data['event']) &&
			$data['event']['event_type'] == 'signature_request_signed' &&
			isset($data['signature_request'])
		) {
			// One Signer Signed
			$signature_request_id = $data['signature_request']['signature_request_id'];
			$signature_request = $data['signature_request'];
			$filepath = 'hellosign/hellosign_' . $signature_request_id . '.pdf';
			$proposal = Proposal::where('signature_request_id', $signature_request_id)->first();

			// if (isset($signature_request['signatures']) && $proposal) {
			// 	$signatures = $signature_request['signatures'];

			// 	if (is_array($signatures)) {
			// 		$proposal->signed_count = (int) $proposal->signed_count + 1;
			// 		$proposal->save();

			// 		foreach ($signatures as $signature) {
			// 			if ($signature['status_code'] == "signed") {
			//     			$signObject = Signature::where('proposal_id', $proposal->id)
			//     															->where('email', $signature['signer_email_address'])
			//     															->where('role', $signature['signer_role'])
			//     															->where('signed', 0)
			//     															->first();
			//     			if ($signObject) {
			//     				$signObject->name = $signature['signer_name'];
			//     				$signObject->signed = 1;
			//     				$signObject->save();
			// 					$informalVote = Vote::where('proposal_id',  $proposal->id)
			// 						->where('type', 'informal')
			// 						->where('content_type', '!=', 'milestone')
			// 						->where('status', 'completed')
			// 						->first();
			// 					if($informalVote){
			// 						$vote = Helper::startFormalVote($informalVote);
			// 						if ($vote) {
			// 							// Emailer Admin
			// 							$emailerData = Helper::getEmailerData();
			// 							Helper::triggerAdminEmail('Vote Started', $emailerData, $proposal, $vote);
			// 							// Emailer Member
			// 							Helper::triggerMemberEmail('New Vote', $emailerData, $proposal, $vote);
			// 						}
			// 					}
			// 				}
			// 			}
			// 		}
			// 	}
			// }

			$proposalGrant = Proposal::where('signature_grant_request_id', $signature_request_id)->first();

			if (isset($signature_request['signatures']) && $proposalGrant) {
				$proposalGrant->grant_hellosign_form = $filepath;
				$proposalGrant->save();
				$signatures = $signature_request['signatures'];
				$finalGrant = FinalGrant::where('proposal_id', $proposalGrant->id)->first();

				if (is_array($signatures)) {
					foreach ($signatures as $signature) {
						if ($signature['status_code'] == "signed") {
							$signObject = SignatureGrant::where('proposal_id', $proposalGrant->id)
								->where('email', $signature['signer_email_address'])
								->where('role', $signature['signer_role'])
								->where('signed', 0)
								->first();
							if ($signObject) {
								$signObject->name = $signature['signer_name'];
								$signObject->signed = 1;
								$signObject->save();

								Helper::createGrantLogging([
									'proposal_id' => $proposalGrant->id,
									'final_grant_id' => $finalGrant->id,
									'user_id' => $signObject->user_id,
									'email' => $signObject->email,
									'role' => $signObject->role,
									'type' => 'signed',
								]);
							}
						}
					}
				}
				if ($settings['autoactivate_grants'] == 'yes') {
					$signatureGrantsSigned = SignatureGrant::where('proposal_id', $proposalGrant->id)->where('signed', 1)->count();
					$signatureGrantsTotal = SignatureGrant::where('proposal_id', $proposalGrant->id)->count();
					if ($signatureGrantsSigned == $signatureGrantsTotal) {
						if ($finalGrant && $finalGrant->status == "pending") {
							$finalGrant->status = 'active';
							$finalGrant->save();

							Helper::createGrantLogging([
								'proposal_id' => $proposalGrant->id,
								'final_grant_id' => $finalGrant->id,
								'user_id' => null,
								'email' => null,
								'role' => 'system',
								'type' => 'completed',
							]);
     						Helper::createGrantTracking($proposalGrant->id, 'Grant activated by ETA', 'grant_activated');
						}
					}
				}
			}
		}

		return "Hello API Event Received";
	}

	// Check 2FA Login
	public function checkLogin2FA(Request $request)
	{
		$user = Auth::user();
		$code = $request->get('code');

		if ($code && $user) {
			$profile = Profile::where('user_id', $user->id)->first();

			if ($profile && $profile->twoFA_login && $profile->twoFA_login_active) {
				if ($profile->twoFA_login_code == $code) {
					$profile->twoFA_login_active = 0;
					$profile->twoFA_login_code = null;
					$profile->twoFA_login_time = null;
					$profile->save();

					return ['success' => true];
				} else {
					return [
						'success' => false,
						'message' => 'Two-Factor authentication code is wrong'
					];
				}
			}
		}

		return ['success' => false];
	}

	// Enable Two-FA Login
	public function enable2FALogin(Request $request)
	{
		$user = Auth::user();
		if ($user) {
			$profile = Profile::where('user_id', $user->id)->first();
			if ($profile) {
				$profile->twoFA_login = 1;
				$profile->save();
			}
		}
		return ['success' => false];
	}

	// Disable Two-FA Login
	public function disable2FALogin(Request $request)
	{
		$user = Auth::user();
		if ($user) {
			$profile = Profile::where('user_id', $user->id)->first();
			if ($profile) {
				$profile->twoFA_login = 0;
				$profile->save();
			}
		}
		return ['success' => false];
	}

	// Generate 2FA
	public function generate2FA(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$email = $request->get('email');
			$code = Str::random(6);
			$profile = Profile::where('user_id', $user->id)->first();

			if ($profile) {
				$profile->twoFA = $code;
				$profile->twoFA_time = time();
				$profile->save();

				if ($email)
					Mail::to($email)->send(new TwoFA($code));
				else
					Mail::to($user)->send(new TwoFA($code));

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Check Proposal
	public function checkProposal(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposalId = (int) $request->get('proposalId');

			if ($proposalId) {
				$proposal = Proposal::with(['user', 'user.profile'])
					->has('user')
					->has('user.profile')
					->where('type', 'grant')
					->whereIn('status', ["approved", "completed"])
					->where('id', $proposalId)
					->first();

				$vote = Vote::where('proposal_id', $proposalId)->where('type', 'formal')
					->where('result', 'success')->where('content_type', 'grant')->first();
				if ($proposal && $vote) {
					return [
						'success' => true,
						'proposal' => $proposal
					];
				}
			}
		}

		return ['success' => false];
	}

	// Check 2FA
	public function check2FA(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$code = $request->get('code');
			$profile = Profile::where('user_id', $user->id)->first();

			if ($profile) {
				if ($profile->twoFA != $code) {
					return [
						'success' => false,
						'message' => 'Your 2FA code is invalid'
					];
				}

				$stime = time() - 300;
				$twoFA_time = (int) $profile->twoFA_time;

				$profile->twoFA = null;
				$profile->twoFA_time = null;
				$profile->save();

				if ((int) $twoFA_time < $stime) {
					return [
						'success' => false,
						'message' => 'Your 2FA code is expired'
					];
				}

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	public function getPreRegisterUser(Request $request)
	{
		$data = null;
		$hash = $request->get('hash');

		if ($hash) {
			$data = PreRegister::where('hash', $hash)
				->where('status', 'approved')
				->first();
		}

		return [
			'success' => true,
			'data' => $data
		];
	}

	// Update Account Info
	public function updateAccountInfo(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$email = $request->get('email');

			if ($email) {
				$temp = User::where('email', $email)->first();
				if ($temp) {
					return [
						'success' => false,
						'message' => 'This email is already used'
					];
				}

				$user->email = $email;
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Update Profile Info
	public function updateProfileInfo(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$profile = Profile::where('user_id', $user->id)->first();
			if ($profile) {
				$first_name = $request->get('first_name');
				$last_name = $request->get('last_name');
				$dob = $request->get('dob');
				$country_citizenship = $request->get('country_citizenship');
				$country_residence = $request->get('country_residence');
				$address = $request->get('address');
				$address_2 = $request->get('address_2');
				$city = $request->get('city');
				$zip = $request->get('zip');

				if ($first_name) $user->first_name = $first_name;
				if ($last_name) $user->last_name = $last_name;
				$user->save();

				if ($dob) $profile->dob = $dob;
				if ($country_citizenship) $profile->country_citizenship = $country_citizenship;
				if ($country_residence) $profile->country_residence = $country_residence;
				if ($address) $profile->address = $address;
				$profile->address_2 = $address_2;
				if ($city) $profile->city = $city;
				if ($zip) $profile->zip = $zip;
				$profile->save();
			}
		}

		return ['success' => true];
	}

	// Update Profile
	public function updateProfile(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$forum_name = $request->get('forum_name');

			if ($forum_name) {
				$profile = Profile::where('forum_name', $forum_name)
					->where('user_id', '!=', $user->id)
					->first();

				if ($profile) {
					return [
						'success' => false,
						'message' => 'The forum name is already in use'
					];
				}

				$profile = Profile::where('user_id', $user->id)->first();
				if ($profile) {
					$profile->forum_name = $forum_name;
					$profile->save();
				}
			}
		}

		return ['success' => true];
	}

	// Change Password
	public function changePassword(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			// Validator
			$validator = Validator::make($request->all(), [
				'current_password' => 'required',
				'new_password' => 'required'
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$current_password = $request->get('current_password');
			$new_password = $request->get('new_password');

			if ($current_password == $new_password) {
				return [
					'success' => false,
					'message' => 'New password cannot be same as the current one'
				];
			}

			if (!Hash::check($current_password, $user->password)) {
				return [
					'success' => false,
					'message' => 'Current password is wrong'
				];
			}

			$password = Hash::make($new_password);
			$user->password = $password;
			$user->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Get Global Settings
	public function getGlobalSettings(Request $request)
	{
		$items = Setting::get();
		$settings = [];

		if ($items) {
			foreach ($items as $item) {
				$settings[$item->name] = $item->value;
			}
		}

		return [
			'success' => true,
			'settings' => $settings
		];
	}

	// Restart Voting
	public function restartVoting(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$voteId = (int) $request->get('voteId');
			$vote = Vote::with('proposal')
				->has('proposal')
				->where('id', $voteId)
				->first();

			if (!$vote || $vote->result != "no-quorum") {
				return [
					'success' => false,
					'message' => 'Invalid vote'
				];
			}

			if (!$user->hasRole('admin') && $user->id != $vote->proposal->user_id) {
				return [
					'success' => false,
					'message' => 'Invalid vote'
				];
			}

			$proposalId = (int) $vote->proposal->id;

			Helper::clearVoters($vote);

			// Clear Vote Result
			VoteResult::where('proposal_id', $proposalId)
				->where('vote_id', $vote->id)
				->delete();

			$vote->status = "active";
			$vote->for_value = 0;
			$vote->against_value = 0;
			$vote->result_count = 0;
			$vote->result = null;
			$vote->save();
			$vote->created_at = $vote->updated_at;
			$vote->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Start Formal Voting - Only for Simple
	public function startFormalVoting(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposalId = (int) $request->get('proposalId');
			$proposal = Proposal::find($proposalId);

			// Proposal Check
			if (!$proposal || !in_array($proposal->type, ["simple", "admin-grant", "advance-payment"])) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Informal Vote Check
			$informalVote = Vote::where('proposal_id', $proposalId)
				->where('type', 'informal')
				->where('content_type', 'simple')
				->where('status', 'completed')
				->first();
			if (!$informalVote) {
				return [
					'success' => false,
					'message' => "Formal vote can't be started"
				];
			}

			$vote = Helper::startFormalVote($informalVote);
			if (!$vote) {
				return [
					'success' => false,
					'message' => 'Formal vote has been already started'
				];
			}

			// Emailer Admin
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('Vote Started', $emailerData, $proposal, $vote);

			// Emailer Member
			Helper::triggerMemberEmail('New Vote', $emailerData, $proposal, $vote);

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Start Informal Voting
	public function startInformalVoting(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposalId = (int) $request->get('proposalId');
			$proposal = Proposal::find($proposalId);

			// Proposal Check
			$statuses = ["approved"];
			if (!$proposal || !in_array($proposal->status, $statuses)) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// OP Check
			if (!$user->hasRole('admin') && $proposal->user_id != $user->id) {
				return [
					'success' => false,
					'message' => 'Only OP can start the informal voting'
				];
			}

			// Pending Change Check
			$change = ProposalChange::where('proposal_id', $proposalId)
				->where('status', 'pending')
				->where('what_section', '!=', 'general_discussion')
				->where('user_id', '!=', $proposal->user_id)
				->first();
			if ($change) {
				return [
					'success' => false,
					'message' => 'This proposal has pending proposed changes'
				];
			}

			// Vote Check
			$vote = Vote::where('proposal_id', $proposalId)->first();
			if ($vote) {
				return [
					'success' => false,
					'message' => 'Vote has been already started'
				];
			}

			$proposal->status = "approved";
			$proposal->save();

			$vote = new Vote;
			$vote->proposal_id = $proposalId;
			$vote->type = 'informal';
			if ($proposal->type == "grant")
				$vote->content_type = "grant";
			else if ($proposal->type == "simple")
				$vote->content_type = "simple";
			else if ($proposal->type == "admin-grant")
				$vote->content_type = "admin-grant";
			else if ($proposal->type == "advance-payment") {
                $vote->content_type = "advance-payment";
                $proposal->proposal_advance_status = 'in-voting';
                $proposal->save();
            }
			$vote->save();

			// Emailer Admin
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('Vote Started', $emailerData, $proposal, $vote);

			// Emailer Member
			Helper::triggerMemberEmail('New Vote', $emailerData, $proposal, $vote);

			Helper::createGrantTracking($proposalId, "Informal vote started", 'informal_vote_started');

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Force Withdraw Proposal
	public function forceWithdrawProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposal = Proposal::find($proposalId);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Check User Access
			if (!$user->hasRole('admin') && $user->id != (int) $proposal->user_id) {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposal"
				];
			}

			// Check Proposal Status
			if ($proposal->status != "approved" && $proposal->status != "pending") {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposal"
				];
			}

			// Check Onboarding
			// $onboarding = OnBoarding::where('proposal_id', $proposal->id)->first();
			// if (!$onboarding || $onboarding->status != 'pending') {
			// 	return [
			// 		'success' => false,
			// 		'message' => "You can't withdraw this proposal"
			// 	];
			// }

			// Check Formal Vote
			$formalVote = Vote::where('proposal_id', $proposal->id)
				->where('type', 'formal')
				->first();

			if ($formalVote) {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposal"
				];
			}

			// Give Rep Back
			$rep = (float) $proposal->rep;
			if ($rep > 0) {
				$op = User::with('profile')
					->has('profile')
					->where('id', $proposal->user_id)
					->first();
				if ($op) {
					$op->profile->rep = (float) $op->profile->rep + $rep;
					$op->profile->save();
					Helper::createRepHistory($op->id, $rep,	$op->profile->rep, 'Gained', 'forceWithdrawProposal', null);

				}
			}
			// remove proposal change
			$proposalChange = ProposalChange::where('proposal_id', $proposalId)->pluck('id');
			if ($proposalChange) {
				ProposalChangeComment::whereIn('proposal_change_id', $proposalChange)->delete();
				ProposalChangeSupport::whereIn('proposal_change_id', $proposalChange)->delete();
			}
			// Remove Proposal
			ProposalHistory::where('proposal_id', $proposalId)->delete();
			ProposalChange::where('proposal_id', $proposalId)->delete();
			Bank::where('proposal_id', $proposalId)->delete();
			Crypto::where('proposal_id', $proposalId)->delete();
			Grant::where('proposal_id', $proposalId)->delete();
			Milestone::where('proposal_id', $proposalId)->delete();
			Citation::where('proposal_id', $proposalId)->delete();
			Citation::where('rep_proposal_id', $proposalId)->delete();
			OnBoarding::where('proposal_id', $proposalId)->delete();

			FinalGrant::where('proposal_id', $proposalId)->delete();
			ProposalFile::where('proposal_id', $proposalId)->delete();
			Reputation::where('proposal_id', $proposalId)->delete();

			Team::where('proposal_id', $proposalId)->delete();
			VoteResult::where('proposal_id', $proposalId)->delete();
			Vote::where('proposal_id', $proposalId)->delete();
			Proposal::where('id', $proposalId)->delete();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Withdraw Proposal
	public function withdrawProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposal = Proposal::with(['votes'])->find($proposalId);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Check User Access
			if (!$user->hasRole('admin') && $user->id != (int) $proposal->user_id) {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposal"
				];
			}

			// Check Proposal Status
			if (
				($proposal->status != "payment" && $proposal->status != "pending" && $proposal->status != "approved") ||
				$proposal->doc_paid || $proposal->votes->count() > 0
			) {
				return [
					'success' => false,
					'message' => "You can't withdraw this proposal"
				];
			}
			// Remove Proposal Change
			$proposalChange = ProposalChange::where('proposal_id', $proposalId)->pluck('id');
			if ($proposalChange) {
				ProposalChangeComment::whereIn('proposal_change_id', $proposalChange)->delete();
				ProposalChangeSupport::whereIn('proposal_change_id', $proposalChange)->delete();
			}
			// Remove Proposal
			ProposalHistory::where('proposal_id', $proposalId)->delete();
			ProposalChange::where('proposal_id', $proposalId)->delete();
			Bank::where('proposal_id', $proposalId)->delete();
			Crypto::where('proposal_id', $proposalId)->delete();
			Grant::where('proposal_id', $proposalId)->delete();
			Milestone::where('proposal_id', $proposalId)->delete();
			Citation::where('proposal_id', $proposalId)->delete();
			OnBoarding::where('proposal_id', $proposalId)->delete();

			FinalGrant::where('proposal_id', $proposalId)->delete();
			ProposalFile::where('proposal_id', $proposalId)->delete();
			Reputation::where('proposal_id', $proposalId)->delete();

			Team::where('proposal_id', $proposalId)->delete();
			VoteResult::where('proposal_id', $proposalId)->delete();
			Vote::where('proposal_id', $proposalId)->delete();
			Proposal::where('id', $proposalId)->delete();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Update Simple Proposal
	public function updateSimpleProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user) {
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

			$proposal = Proposal::find($proposalId);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			if ($user->hasRole('admin')) {
				// Admin can only edit pending proposal
				if ($proposal->status != 'pending' && $proposal->status != 'approved') {
					return [
						'success' => false,
						'message' => 'Invalid proposal'
					];
				}
			} else {
				// OP can only edit denied proposal
				if  ($proposal->user_id != $user->id) {
					return [
						'success' => false,
						'message' => 'Invalid proposal'
					];
				}
			}

			$title = $request->get('title');
			$short_description = $request->get('short_description');

			$otherProposal = Proposal::where('title', $title)
				->where('id', '!=', $proposalId)
				->first();

			if ($otherProposal) {
				return [
					'success' => true,
					'message' => "Another proposal with the same title already exists"
				];
			}

			// Updating Proposal
			$proposal->title = $title;
			$proposal->short_description = $short_description;
			$proposal->save();

			return [
				'success' => true,
				'proposal' => $proposal
			];
		}

		return ['success' => false];
	}

	// Update Proposal
	public function updateProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user) {
			// Validator
			$validator = Validator::make($request->all(), [
				'title' => 'required',
				'short_description' => 'required',
				'explanation_benefit' => 'required',
				// 'explanation_goal' => 'required',
				'total_grant' => 'required',
				'resume' => 'required',
				// 'extra_notes' => 'required',
				// 'members' => 'required|array',
				'grants' => 'required|array',
				'milestones' => 'required|array',
				'relationship' => 'required',
				// 'previous_work' => 'required',
				// 'other_work' => 'required'
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$proposal = Proposal::find($proposalId);
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Can not find proposal'
				];
			}

			if ($user->hasRole('admin')) {
				// Admin can only edit pending proposal
				if ($proposal->status != 'pending' && $proposal->status != 'approved') {
					return [
						'success' => false,
						'message' => 'Proposal is not in pending or approved'
					];
				}
			} else {
				// OP can only edit proposal
				if ($proposal->user_id != $user->id) {
					return [
						'success' => false,
						'message' => 'Only OP can edit proposal'
					];
				}
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
			// $bank_name = $request->get('bank_name');
			// $iban_number = $request->get('iban_number');
			// $swift_number = $request->get('swift_number');
			// $holder_name = $request->get('holder_name');
			// $account_number = $request->get('account_number');
			// $bank_address = $request->get('bank_address');
			// $bank_city = $request->get('bank_city');
			// $bank_country = $request->get('bank_country');
			// $bank_zip = $request->get('bank_zip');
			// $holder_address = $request->get('holder_address');
			// $holder_city = $request->get('holder_city');
			// $holder_country = $request->get('holder_country');
			// $holder_zip = $request->get('holder_zip');

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

			if ($memberRequired && (!$members || !count($members))) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$otherProposal = Proposal::where('title', $title)
				->where('id', '!=', $proposalId)
				->first();

			if ($otherProposal) {
				return [
					'success' => true,
					'message' => "Another proposal with the same title already exists"
				];
			}

			// Updating Proposal
			$proposal->title = $title;
			$proposal->short_description = $short_description;
			$proposal->explanation_benefit = $explanation_benefit;
			// $proposal->explanation_goal = $explanation_goal;
			$proposal->total_grant = $total_grant;
			$proposal->license = $license;
			$proposal->resume = $resume;
			$proposal->extra_notes = $extra_notes;
			if ($license_other)
				$proposal->license_other = $license_other;
			else
				$proposal->license_other = null;
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
			// else
			// 	$proposal->foundational_work = null;
			$proposal->include_membership = $include_membership;
			$proposal->member_reason = $member_reason;
			$proposal->member_benefit = $member_benefit;
			$proposal->linkedin = $linkedin;
			$proposal->github = $github;

			// $proposal->yesNo1 = $yesNo1;
			// $proposal->yesNo2 = $yesNo2;
			// $proposal->yesNo3 = $yesNo3;
			// $proposal->yesNo4 = $yesNo4;

			// if ($yesNo1) $proposal->yesNo1Exp = $yesNo1Exp;
			// if ($yesNo2) $proposal->yesNo2Exp = $yesNo2Exp;
			// if (!$yesNo3) $proposal->yesNo3Exp = $yesNo3Exp;
			// if ($yesNo4) $proposal->yesNo4Exp = $yesNo4Exp;
			// entity
			$isCompanyOrOrganization = (int) $request->get('is_company_or_organization');
			$nameEntity = $request->get('name_entity');
			$entityCountry = $request->get('entity_country');
			$proposal->is_company_or_organization = $isCompanyOrOrganization;
			if ($isCompanyOrOrganization) {
				$proposal->name_entity = $nameEntity;
				$proposal->entity_country = $entityCountry;
			} else {
				$proposal->name_entity = null;
				$proposal->entity_country = null;
			}

			// mentor
			$haveMentor = (int) $request->get('have_mentor');
			$nameMentor = $request->get('name_mentor');
			$totalHoursMentor = $request->get('total_hours_mentor');
			$proposal->have_mentor = $haveMentor;
			if ($haveMentor) {
				$proposal->name_mentor = $nameMentor;
				$proposal->total_hours_mentor = $totalHoursMentor;
			} else {
				$proposal->name_mentor = null;
				$proposal->total_hours_mentor = null;
			}

			$agree1 = (int) $request->get('agree1');
			$agree2 = (int) $request->get('agree2');
			$agree3 = (int) $request->get('agree3');
			$proposal->agree1 = $agree1;
			$proposal->agree2 = $agree2;
			$proposal->agree3 = $agree3;

			// $proposal->formField1 = $formField1;
			// $proposal->formField2 = $formField2;

			// $proposal->purpose = $purpose;
			// $proposal->purposeOther = $purposeOther;

			if ($tags && count($tags))
				$proposal->tags = implode(",", $tags);

			$proposal->member_required = $memberRequired;
			// if ($proposal->pdf) {

			// 	$pdf = PDF::loadView('proposal_pdf', compact('proposal'));
			// 	Storage::disk('local')->put(substr($proposal->pdf, 9), $pdf->output());
			// }

			$proposal->save();

			// Updating Team
			Team::where('proposal_id', $proposalId)->delete();
			if ($memberRequired) {
				foreach ($members as $member) {
					$full_name = $bio = '';
					extract($member);

					if ($full_name && $bio) {
						$team = new Team;
						$team->full_name = $full_name;
						$team->bio = $bio;
						$team->proposal_id = (int) $proposal->id;
						$team->save();
					}
				}
			}

			// Updating Grant
			Grant::where('proposal_id', $proposalId)->delete();
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

			// Updating Bank
			// Bank::where('proposal_id', $proposalId)->delete();
			// $bank = new Bank;
			// $bank->proposal_id = (int) $proposal->id;
			// if ($bank_name)
			// 	$bank->bank_name = $bank_name;
			// if ($iban_number)
			// 	$bank->iban_number = $iban_number;
			// if ($swift_number)
			// 	$bank->swift_number = $swift_number;
			// if ($holder_name)
			// 	$bank->holder_name = $holder_name;
			// if ($account_number)
			// 	$bank->account_number = $account_number;
			// if ($bank_address)
			// 	$bank->bank_address = $bank_address;
			// if ($bank_city)
			// 	$bank->bank_city = $bank_city;
			// if ($bank_zip)
			// 	$bank->bank_zip = $bank_zip;
			// if ($bank_country)
			// 	$bank->bank_country = $bank_country;
			// if ($holder_address)
			// 	$bank->address = $holder_address;
			// if ($holder_city)
			// 	$bank->city = $holder_city;
			// if ($holder_zip)
			// 	$bank->zip = $holder_zip;
			// if ($holder_country)
			// 	$bank->country = $holder_country;
			// $bank->save();

			// Updating Crypto
			Crypto::where('proposal_id', $proposalId)->delete();
			$crypto = new Crypto;
			$crypto->proposal_id = (int) $proposal->id;
			if ($crypto_address)
				$crypto->public_address = $crypto_address;
			if ($crypto_type)
				$crypto->type = $crypto_type;
			$crypto->save();

			// Updating Milestone
			Milestone::where('proposal_id', $proposalId)->delete();
			foreach ($milestones as $milestoneData) {
				$title = $details = $criteria = $kpi = $deadline = $level_difficulty = '';
				$grant = 0;
				extract($milestoneData);
				$grant = (float) $grant;

				if ($grant && $title && $details) {
					$milestone = new Milestone;
					$milestone->proposal_id = (int) $proposal->id;
					$milestone->title = $title;
					$milestone->details = $details;
					$milestone->grant = $grant;
					$milestone->criteria = $criteria;
					// $milestone->kpi = $kpi;
					$milestone->deadline = $deadline;
					$milestone->level_difficulty = $level_difficulty;
					$milestone->save();
				}
			}

			// Updating Citation
			Citation::where('proposal_id', $proposalId)->delete();
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

			return [
				'success' => true,
				'proposal' => $proposal
			];
		}

		return ['success' => false];
	}

	// update grant Proposal
	public function updateAdminGrantProposal($proposalId, Request $request) {
		$user = Auth::user();

		if ($user) {
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

			$proposal = Proposal::where('id', $proposalId)->where('type', 'admin-grant')->first();
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Can not find proposal'
				];
			}
			if ($user->hasRole('admin')) {
				// Admin can only edit pending proposal
				if ($proposal->status != 'pending' && $proposal->status != 'approved') {
					return [
						'success' => false,
						'message' => 'Proposal is not in pending or approved'
					];
				}
			} else {
				// OP can only edit proposal
				if ($proposal->user_id != $user->id) {
					return [
						'success' => false,
						'message' => 'Only OP can edit proposal'
					];
				}
			}
			$otherProposal = Proposal::where('title', $title)
			->where('id', '!=', $proposalId)
			->first();

			if ($otherProposal) {
				return [
					'success' => true,
					'message' => "Another proposal with the same title already exists"
				];
			}
			// update Proposal
			$proposal->title = $title;
			$proposal->total_grant = $total_grant;
			$proposal->things_delivered = $things_delivered;
			$proposal->delivered_at = $delivered_at;
			$proposal->extra_notes = $extra_notes;
			$proposal->save();

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

	// Upload Proposal Files
	public function uploadProposalFiles(Request $request)
	{
		$user = Auth::user();

		if ($user) {
			$proposalId = (int) $request->get('proposal');
			$proposal = Proposal::find($proposalId);

			$ids_to_remove = $request->get('ids_to_remove');
			$names = $request->get('names');
			$files = $request->file('files');

			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Remove Files
			if ($ids_to_remove) {
				$temp = explode(",", $ids_to_remove);
				foreach ($temp as $id) {
					if ((int) $id)
						ProposalFile::where('id', (int) $id)->delete();
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

						$proposalFile = new ProposalFile;
						$proposalFile->proposal_id = $proposalId;
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

	// Get Single Proposal
	public function getSingleProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		$proposal = Proposal::where('id', $proposalId)
			->with([
				'proposalRequestPayment',
				'proposalRequestFrom',
				'user',
				'user.profile',
				'user.shuftipro',
				'bank',
				'crypto',
				'grants',
				'milestones',
				'milestones.milestoneReview',
				'milestones.milestoneReview.user',
				'milestones.milestoneReview.milestoneCheckList',
				'milestones.milestoneReview.milestoneSubmitHistory',
				'citations',
				'citations.repProposal',
				'citations.repProposal.user',
				'citations.repProposal.user.profile',
				'members',
				'files',
				'votes',
				'onboarding',
				'surveyRanks.survey',
				'surveyDownVoteRanks.survey',
			])
			->with(['surveyRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->with(['surveyDownVoteRanks' => function ($q) {
				$q->orderBy('rank', 'desc');
			}])
			->has('user')
			->has('user.profile')
			->first();
		if ($proposal) {
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

			// Sponsor
			$proposal->sponsor = Helper::getSponsor($proposal);

			// Total Members
			$proposal->totalMembers = Helper::getTotalMemberProposal($proposalId);


			if ($proposal->votes && count($proposal->votes)) {
				foreach ($proposal->votes as $vote) {
					$vote->totalVotes = VoteResult::where('proposal_id', $proposal->id)
						->where('vote_id', $vote->id)
						->get()
						->count();

					$vote->results = VoteResult::join('profile', 'profile.user_id', '=', 'vote_result.user_id')
						->where('vote_id', $vote->id)
						->where('proposal_id', $proposal->id)
						->select([
							'vote_result.*',
							'profile.forum_name'
						])
						->orderBy('vote_result.created_at', 'asc')
						->get();
				}
			}

			$proposal->loser = $proposal->surveyDownVoteRanks->first(function ($value, $key) {
				return $value->is_winner && $value->is_approved;
			});
			$proposal->winner = $proposal->surveyRanks->first(function ($value, $key) {
				return $value->is_winner;
			});

			$proposal->makeVisible('user');
			$proposal->user->makeVisible('profile');
			$proposal->citations->each(function ($citation) {
				$citation->repProposal->makeVisible('user');
				$citation->repProposal->user->makeVisible('profile');
			});

			return [
				'success' => true,
				'proposal' => $proposal,
			];
		}

		return ['success' => false];
	}

	// Get Single Proposal for Edit
	public function getSingleProposalEdit($proposalId, Request $request)
	{
		$user = Auth::user();

		$proposal = Proposal::where('id', $proposalId)
			->with([
				'bank',
				'crypto',
				'grants',
				'citations',
				'citations.repProposal',
				'citations.repProposal.user',
				'citations.repProposal.user.profile',
				'milestones',
				'members',
				'files',
				'votes'
			])
			->first();
		if ($proposal) {
			if ($user->hasRole('admin')) {
				// Admin can edit only pending proposal
				if ($proposal->status != 'pending')
					return ['success' => false];
			} else {
				// Only OP can edit the denied proposal
				if ($proposal->user_id != $user->id || $proposal->status != 'denied')
					return ['success' => false];
			}

			// OP
			$op = User::find($proposal->user_id);
			$op->membership = Helper::getMembershipProposal($op);

			$proposal->op = $op;

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

		return ['success' => false];
	}

	// Get Grants
	public function getGrants(Request $request)
	{
		$user = Auth::user();
		$proposals = [];

		// Variables
		$status = $request->status;
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'final_grant.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);
		$hide_completed = $request->hide_completed;

		if ($user->hasRole('admin')) {
			$proposals = FinalGrant::with(['proposal', 'proposal.user', 'proposal.milestones', 'proposal.milestones.votes', 'proposal.milestones.milestoneReview', 'user', 'signtureGrants', 'grantLogs']);
			if ($status == 'pending') {
				$proposals = $proposals->where('final_grant.status', $status);
			} else {
				$proposals = $proposals->where('final_grant.status', '!=', 'pending');
			}
			$proposals = $proposals->has('proposal.milestones')
			->has('user')
			->where(function ($subQuery)  use ($search) {
				$subQuery->whereHas('proposal', function ($query) use ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%');
				})
					->orWhereHas('user', function ($query)  use ($search) {
						$query->where('users.email', 'like', '%' . $search . '%');
					});
			})
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
			foreach ($proposals as $proposal) {
				$in_review = 0;
				$milestones = $proposal->proposal->milestones;
				if (count($milestones)) {
					$milestone_ids = $milestones->pluck('id')->toArray();
					$milestoneReview = MilestoneReview::whereIn('milestone_id', $milestone_ids)->whereIn('status', ['pending', 'active'])->first();
					if($milestoneReview)  {
						$in_review = 1;
					}
				}
				$proposal->in_review = $in_review;
			}
		} else {
			$proposals = FinalGrant::with(['proposal', 'proposal.user', 'proposal.milestones', 'proposal.milestones.votes', 'proposal.milestones.milestoneReview', 'proposal.votes', 'user', 'signtureGrants'])
			->has('proposal.milestones')
			->has('proposal.votes')
			->has('user')
			->whereHas('proposal', function ($query) use ($search , $hide_completed) {
				if($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%');
				}
				if($hide_completed) {
					$query->where('final_grant.status', '!=', 'completed');
				}
			})
				->where('user_id', $user->id)
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
			foreach ($proposals as $proposal) {
				$in_review = 0;
				$milestones = $proposal->proposal->milestones;
				if (count($milestones)) {
					$milestone_ids = $milestones->pluck('id')->toArray();
					$milestoneReview = MilestoneReview::whereIn('milestone_id', $milestone_ids)->whereIn('status', ['pending', 'active'])->first();
					if($milestoneReview)  {
						$in_review = 1;
					}
				}
				$proposal->in_review = $in_review;
			}
		}

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get Completed Proposals
	public function getCompletedProposals(Request $request)
	{
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

		if ($user && $user->hasRole('admin')) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->join('users', 'users.id', '=', 'proposal.user_id')
				->join('profile', 'profile.user_id', '=', 'proposal.user_id')
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%');
					}
				})
				->where('proposal.status', 'completed')
				->select([
					'proposal.*',
					'users.first_name',
					'users.last_name',
					'profile.forum_name'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		} else if ($user->hasRole(['participant', 'member'])) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->join('users', 'users.id', '=', 'proposal.user_id')
				->join('profile', 'profile.user_id', '=', 'proposal.user_id')
				->where('proposal.status', 'completed')
				->where('proposal.user_id', $user->id)
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%');
					}
				})
				->select([
					'proposal.*',
					'users.first_name',
					'users.last_name',
					'profile.forum_name'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}

		$proposals->each(function ($proposal, $key) {
			$proposal->makeHidden([ 'onboarding' ]);
		});

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get All Proposals 2
	public function getAllProposals2(Request $request)
	{
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

		// Proposals
		$proposals = Proposal::with(['votes', 'onboarding', 'milestones'])
			->join('users', 'users.id', '=', 'proposal.user_id')
			->join('profile', 'profile.user_id', '=', 'proposal.user_id')
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.status', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%')
						->orWhere('proposal.type', 'like', '%' . $search . '%');
				}
			})
			->select([
				'proposal.*',
				'users.first_name',
				'users.last_name',
				'profile.forum_name'
			])
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();

		$proposals->each(function ($proposal, $key) {
			$proposal->makeHidden([ 'onboarding' ]);
		});

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get All Proposals
	public function getAllProposals(Request $request)
	{
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

		if ($user && $user->hasRole('admin')) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->join('users', 'users.id', '=', 'proposal.user_id')
				->join('profile', 'profile.user_id', '=', 'proposal.user_id')
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
							->orWhere('proposal.status', 'like', '%' . $search . '%')
							->orWhere('proposal.id', 'like', '%' . $search . '%')
							->orWhere('proposal.short_description', 'like', '%' . $search . '%')
							->orWhere('proposal.type', 'like', '%' . $search . '%');
					}
				})
				->select([
					'proposal.*',
					'users.first_name',
					'users.last_name',
					'profile.forum_name',
					'profile.telegram'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		} else if ($user->hasRole(['participant', 'member'])) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->join('users', 'users.id', '=', 'proposal.user_id')
				->join('profile', 'profile.user_id', '=', 'proposal.user_id')
				->where(function ($query) use ($user) {
					$query->where('proposal.user_id', $user->id)
						->orWhereIn('proposal.status', ['approved', 'completed']);
				})
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
							->orWhere('proposal.status', 'like', '%' . $search . '%')
							->orWhere('proposal.id', 'like', '%' . $search . '%')
							->orWhere('proposal.type', 'like', '%' . $search . '%');
					}
				})
				->select([
					'proposal.*',
					'users.first_name',
					'users.last_name',
					'profile.forum_name'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}

		foreach ($proposals as $proposal) {
			$votes = $proposal->votes;
			if ($votes) {
				foreach ($votes as $vote) {
					if ($vote->milestone_id) {
						$milestone = Milestone::where('id', $vote->milestone_id)->first();
						$vote->milestone_grant = $milestone->grant;
					} else {
						$vote->milestone_grant = null;
					}
				}
			}

			$proposal->euros = $proposal->total_grant;
		}

		$proposals->each(function ($proposal, $key) {
			$proposal->makeHidden([ 'onboarding' ]);
		});

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get Active Proposals
	public function getActiveProposals(Request $request)
	{
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
		if ($user && $user->hasRole('admin')) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->join('users', 'users.id', '=', 'proposal.user_id')
				// ->whereIn('proposal.status', ['approved', 'completed'])
				->whereIn('proposal.status', ['approved'])
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
							->orWhere('proposal.status', 'like', '%' . $search . '%');
					}
				})
				->select([
					'proposal.*',
					'users.first_name',
					'users.last_name'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		} else if ($user) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->leftJoin('proposal_change', function ($join) {
					$join->on('proposal_change.proposal_id', '=', 'proposal.id');
					$join->where('proposal_change.status', 'pending');
					$join->where('proposal_change.what_section', '!=', 'general_discussion');
				})
				->selectRaw('proposal.*, count(proposal_change.proposal_id) as pendingCount')
				->where('proposal.user_id', $user->id)
				->where('proposal.status', '!=', 'completed')
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
							->orWhere('proposal.status', 'like', '%' . $search . '%');
					}
				})
				->orderBy($sort_key, $sort_direction)
				->groupBy('proposal.id')
				->offset($start)
				->limit($limit)
				->get();
		}

		$proposals->each(function ($proposal, $key) {
			$proposal->makeHidden([ 'onboarding' ]);
		});

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get Pending Proposals
	public function getPendingProposals(Request $request)
	{
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
		if ($user && $user->hasRole('admin')) {
			$proposals = Proposal::join('users', 'users.id', '=', 'proposal.user_id')
				->whereIn('proposal.status', ['pending', 'payment'])
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%');
					}
				})
				->select([
					'proposal.*',
					'users.first_name',
					'users.last_name'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		} else if ($user) {
			// Not Used for Now
			/*$proposals = Proposal::where('user_id', $user->id)
														->whereIn('status', ['pending', 'denied'])
														->orderBy('created_at', 'desc')
														->get();*/
		}

		return [
			'success' => true,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	// Get Active Discussions
	public function getActiveDiscussions(Request $request)
	{
		$user = Auth::user();
		$proposals = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$ignore_previous_winner = $request->ignore_previous_winner;
		$ignore_previous_loser = $request->ignore_previous_loser;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);
		$is_winner = $request->is_winner;

		// Record
		if ($user) {
			$proposals = Proposal::where('proposal.status', 'approved')
				->with(['surveyRanks' => function ($q) {
					$q->where('is_winner', 1)
					->orderBy('rank', 'desc');
				}])
				->doesntHave('votes')
				->where(function ($query) use ($search, $is_winner, $ignore_previous_winner, $ignore_previous_loser) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%');
					}
					if($is_winner) {
						$query->whereHas('surveyRanks', function($q){
							$q->where('is_winner', 1);
						});
					}
					if($ignore_previous_winner == 1) {
						$survey_rank_ids = SurveyRank::where('is_winner', 1)->pluck('proposal_id');
						$survey_downvote_rank_ids = SurveyDownVoteRank::where('is_winner', 1)->pluck('proposal_id');
						$query->whereNotIn('proposal.id', $survey_rank_ids->toArray())
							->whereNotIn('proposal.id', $survey_downvote_rank_ids->toArray());
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

	// Get Completed Discussions
	public function getCompletedDiscussions(Request $request)
	{
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

		// Record
		if ($user) {
			$proposals = Proposal::with('votes')
				->whereIn('proposal.status', ['approved', 'completed'])
				->has('votes')
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

	// Get Proposal Changes
	public function getProposalChanges($proposalId, Request $request)
	{
		$user = Auth::user();
		$changes = [];

		if ($user) {
			$changes = ProposalChange::with(['user', 'user.profile'])
				->has('user')
				->has('user.profile')
				->where('proposal_id', $proposalId)
				->orderBy('created_at', 'desc')
				->get();
		}

		return [
			'success' => true,
			'changes' => $changes
		];
	}

	// Get Single Proposal Change
	public function getSingleProposalChange($proposalId, $proposalChangeId)
	{
		$user = Auth::user();
		$proposal = $proposalChange = null;

		if ($user) {
			$temp = ProposalChange::with(['user', 'user.profile'])
				->has('user')
				->has('user.profile')
				->where('id', $proposalChangeId)
				->first();
			if ($temp && $temp->proposal_id == $proposalId) {
				$proposalChange = $temp;
				$proposal = Proposal::where('id', $proposalId)
					->with(['user', 'user.profile', 'votes', 'bank', 'crypto', 'grants', 'citations', 'citations.repProposal',
						'citations.repProposal.user', 'citations.repProposal.user.profile', 'milestones', 'members', 'files', 'onboarding'])
					->first();

				if ($proposal) {
					// Check Support Record
					$proposalChange->supported_by_you = false;
					if ($user->hasRole(['participant', 'member'])) {
						$support = ProposalChangeSupport::where('proposal_change_id', $proposalChangeId)->where('user_id', $user->id)->first();
						if ($support) $proposalChange->supported_by_you = true;
					}

					// History Record
					$history = ProposalHistory::where('proposal_id', $proposal->id)
						->where('proposal_change_id', $proposalChange->id)
						->first();
						$proposal->makeVisible('user');
						$proposal->user->makeVisible('profile');
						$proposal->citations->each(function ($citation) {
							$citation->repProposal->makeVisible('user');
							$citation->repProposal->user->makeVisible('profile');
						});
					return [
						'success' => true,
						'proposal' => $proposal,
						'change' => $proposalChange,
						'history' => $history
					];
				}
			}
		}

		return ['success' => false];
	}

	// Get Proposal Change Comments
	public function getProposalChangeComments($proposalId, $proposalChangeId, Request $request)
	{
		$user = Auth::user();
		$comments = [];

		if ($user) {
			$comments =
				ProposalChangeComment::join('users', 'users.id', '=', 'proposal_change_comment.user_id')
				->join('profile', 'profile.user_id', '=', 'users.id')
				->where('proposal_change_comment.proposal_change_id', $proposalChangeId)
				->select([
					'proposal_change_comment.*',
					'users.first_name',
					'users.last_name',
					'profile.forum_name as forum_name'
				])
				->orderBy('proposal_change_comment.created_at', 'desc')
				->get();
		}

		return [
			'success' => true,
			'comments' => $comments
		];
	}

	// Get Completed Votes
	public function getCompletedVotes(Request $request)
	{
		$user = Auth::user();
		$votes = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'vote.updated_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 20;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user) {
			if ($user->hasRole('member')) {
				$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
					->join('users', 'users.id', '=', 'proposal.user_id')
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->leftJoin('vote_result', function ($join) use ($user) {
						$join->on('vote_result.vote_id', '=', 'vote.id');
						$join->where('vote_result.user_id', $user->id);
					})
					->where('vote.status', 'completed')
					->where(function ($query) use ($search) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('vote.type', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
					})
					->select([
						'proposal.id as proposalId',
						'proposal.type as proposalType',
						'proposal.title',
						'proposal.include_membership',
						'users.first_name',
						'users.last_name',
						'users.id as user_id',
						'vote.*',
						'vote_result.type as vote_result_type',
						'vote_result.value as vote_result_value',
						DB::raw('(CASE WHEN vote.content_type = \'grant\' OR proposal.type = \'admin-grant\' OR proposal.type = \'advance-payment\'  THEN proposal.total_grant WHEN vote.content_type = \'milestone\' THEN milestone.grant ELSE null END) AS euros')
					])
					->orderBy($sort_key, $sort_direction)
					->groupBy('vote.id')
					->offset($start)
					->limit($limit)
					->get();
			} else {
				$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
					->join('users', 'users.id', '=', 'proposal.user_id')
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->where('vote.status', 'completed')
					->where(function ($query) use ($search) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('vote.type', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
					})
					->select([
						'proposal.id as proposalId',
						'proposal.type as proposalType',
						'proposal.title',
						'proposal.include_membership',
						'users.first_name',
						'users.last_name',
						'users.id as user_id',
						'vote.*',
						DB::raw('(CASE WHEN vote.content_type = \'grant\' OR proposal.type = \'admin-grant\' OR proposal.type = \'advance-payment\' THEN proposal.total_grant WHEN vote.content_type = \'milestone\' THEN milestone.grant ELSE null END) AS euros')
					])
					->orderBy($sort_key, $sort_direction)
					->offset($start)
					->limit($limit)
					->get();
			}
		}

		return [
			'success' => true,
			'votes' => $votes,
			'finished' => count($votes) < $limit ? true : false
		];
	}

	// Get Active Formal Votes
	public function getActiveFormalVotes(Request $request)
	{
		$user = Auth::user();
		$votes = [];
		$settings = Helper::getSettings();
		$minsFormal = $minsSimple = $minsMileStone = 0;
		if ($settings['time_unit_formal'] == 'min')
			$minsFormal = (int) $settings['time_formal'];
		else if ($settings['time_unit_formal'] == 'hour')
			$minsFormal = (int) $settings['time_formal'] * 60;
		else if ($settings['time_unit_formal'] == 'day')
			$minsFormal = (int) $settings['time_formal'] * 60 * 24;

		if ($settings['time_unit_simple'] == 'min')
			$minsSimple = (int) $settings['time_simple'];
		else if ($settings['time_unit_simple'] == 'hour')
			$minsSimple = (int) $settings['time_simple'] * 60;
		else if ($settings['time_unit_simple'] == 'day')
			$minsSimple = (int) $settings['time_simple'] * 60 * 24;

		if ($settings['time_unit_milestone'] == 'min')
			$minsMileStone = (int) $settings['time_milestone'];
		else if ($settings['time_unit_milestone'] == 'hour')
			$minsMileStone = (int) $settings['time_milestone'] * 60;
		else if ($settings['time_unit_milestone'] == 'day')
			$minsMileStone = (int) $settings['time_milestone'] * 60 * 24;

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'vote.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);
		$show_unvoted = $request->show_unvoted;
		$total_unvoted = 0;

		// Records
		if ($user) {
			if ($user->hasRole('member')) {
				$unvoted_informal =  Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
				->join('users', 'users.id', '=', 'proposal.user_id')
				->leftJoin('vote_result', function ($join) use ($user) {
					$join->on('vote_result.vote_id', '=', 'vote.id');
					$join->where('vote_result.user_id', $user->id);
				})
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->where('vote.type', 'informal')
					->where('vote.status', 'completed')
					->where('vote_result.type', null)
					->where('users.id', '!=', $user->id)
					->where(function ($query) use ($search) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
					})->pluck('vote.formal_vote_id');
				$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
					->join('users', 'users.id', '=', 'proposal.user_id')
					->leftJoin('vote_result', function ($join) use ($user) {
						$join->on('vote_result.vote_id', '=', 'vote.id');
						$join->where('vote_result.user_id', $user->id);
					})
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->join('vote as vote2', function ($join) {
						$join->on('vote.proposal_id', '=', 'vote2.proposal_id');
						$join->where('vote2.type', 'informal');
						$join->on('vote2.content_type', 'vote.content_type');
					})
					->where('vote.type', 'formal')
					->where('vote.status', 'active')
					->where(function ($query) use ($search, $show_unvoted, $user, $unvoted_informal) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
						if($show_unvoted) {
							$query->where('users.id', '!=', $user->id)
								->whereNotIn('vote.id', $unvoted_informal->toArray())
								->whereNotExists(function($query) use($user) {
								$query->selectRaw('vote_result2.user_id')
									->from('vote')
									->join('vote as vote2', function ($join) {
										$join->on('vote.proposal_id', '=', 'vote2.proposal_id');
										$join->where('vote2.type', 'informal');
										$join->on('vote2.content_type', 'vote.content_type');
									})
									->join('vote_result as vote_result2', 'vote_result2.vote_id', 'vote2.id')
									->where('vote_result.user_id', $user->id);
							});
						}
					})
					->select([
						'proposal.id as proposalId',
						'proposal.type as proposalType',
						'proposal.title',
						'proposal.include_membership',
						'users.first_name',
						'users.last_name',
						'users.id as user_id',
						'vote.*',
						'vote_result.type as vote_result_type',
						'vote_result.value as vote_result_value',
						'vote2.result_count as total_member',
						DB::raw('(CASE WHEN vote.content_type = \'grant\' OR proposal.type = \'admin-grant\' OR proposal.type = \'advance-payment\' THEN proposal.total_grant WHEN vote.content_type = \'milestone\' THEN milestone.grant ELSE null END) AS euros'),
						DB::raw("(CASE WHEN vote.content_type = 'grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsFormal MINUTE, current_timestamp())
							WHEN vote.content_type = 'milestone' THEN TIMEDIFF(vote.created_at + INTERVAL $minsMileStone MINUTE, current_timestamp())
							WHEN vote.content_type = 'simple' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							WHEN vote.content_type = 'admin-grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							WHEN vote.content_type = 'advance-payment' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							ELSE null END ) AS timeLeft")

					]);
					if($sort_key == 'vote_result_type' &&  $sort_direction == 'asc' ) {
						$votes = $votes->orderByRaw('-vote_result.type ASC');
					}
					$votes = $votes
					->orderBy($sort_key, $sort_direction)
					->groupBy('vote.id')
					->offset($start)
					->limit($limit)
					->get();
				foreach($votes as $vote) {
					$is_voted_informal = false;

                    $vote_informal = Vote::where('proposal_id', $vote->proposal_id)->where('type', 'informal')
					->where('result', 'success')
                    ->where('milestone_id', $vote->milestone_id)->where('content_type', $vote->content_type)->first();
					if($vote_informal) {
						$check_vote_informal = VoteResult::where('vote_id', $vote_informal->id)->where('user_id', $user->id)->count();
						$is_voted_informal = $check_vote_informal  > 0 ? true : false;
					}
					$vote->is_voted_informal = $is_voted_informal;
				}

				$total_unvoted =  Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
				->join('users', 'users.id', '=', 'proposal.user_id')
				->leftJoin('vote_result', function ($join) use ($user) {
					$join->on('vote_result.vote_id', '=', 'vote.id');
					$join->where('vote_result.user_id', $user->id);
				})
				->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
				->join('vote as vote2', function ($join) {
					$join->on('vote.proposal_id', '=', 'vote2.proposal_id');
					$join->where('vote2.type', 'informal');
					$join->on('vote2.content_type', 'vote.content_type');
				})
				->where('vote.type', 'formal')
				->where('vote.status', 'active')
				->where('users.id', '!=', $user->id)
				->whereNotIn('vote.id', $unvoted_informal->toArray())
				->whereNotExists(function($query) use($user) {
					$query->selectRaw('vote_result2.user_id')
						->from('vote')
						->join('vote as vote2', function ($join) {
							$join->on('vote.proposal_id', '=', 'vote2.proposal_id');
							$join->where('vote2.type', 'informal');
							$join->on('vote2.content_type', 'vote.content_type');
						})
						->join('vote_result as vote_result2', 'vote_result2.vote_id', 'vote2.id')
						->where('vote_result.user_id', $user->id);
				})
				->where(function ($query) use ($search, $show_unvoted, $user) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%')
							->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
							->orWhere('proposal.id', 'like', '%' . $search . '%');
					}
				})->distinct()->count('vote.id');
			} else {
				$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
					->join('users', 'users.id', '=', 'proposal.user_id')
					// ->join('vote as vote2', function ($join) {
					// 	$join->on('vote.proposal_id', '=', 'vote2.proposal_id');
					// 	$join->where('vote2.type', 'informal');
					// 	$join->on('vote2.content_type', 'vote.content_type');
					// })
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->where('vote.type', 'formal')
					->where('vote.status', 'active')
					->where(function ($query) use ($search) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
					})
					->select([
						'proposal.id as proposalId',
						'proposal.type as proposalType',
						'proposal.title',
						'proposal.include_membership',
						'users.first_name',
						'users.last_name',
						'users.id as user_id',
						'vote.*',
						// 'vote2.result_count as total_member',
						DB::raw('(CASE WHEN vote.content_type = \'grant\' OR proposal.type = \'admin-grant\' OR proposal.type = \'advance-payment\' THEN proposal.total_grant WHEN vote.content_type = \'milestone\' THEN milestone.grant ELSE null END) AS euros'),
						DB::raw("(CASE WHEN vote.content_type = 'grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsFormal MINUTE, current_timestamp())
							WHEN vote.content_type = 'milestone' THEN TIMEDIFF(vote.created_at + INTERVAL $minsMileStone MINUTE, current_timestamp())
							WHEN vote.content_type = 'simple' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							WHEN vote.content_type = 'admin-grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							WHEN vote.content_type = 'advance-payment' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							ELSE null END ) AS timeLeft")

					])
					->orderBy($sort_key, $sort_direction)
					->offset($start)
					->limit($limit)
					->get();
			}

			if ($votes) {
				foreach ($votes as $vote) {
					$informalVote = Vote::where('proposal_id', $vote->proposal_id)
						->where('type', 'informal')
						->where('formal_vote_id', $vote->id)
						->orderBy('id', 'desc')
						->first();
					if ($informalVote) {
						$vote->total_member = $informalVote->result_count;
						$results = VoteResult::select('user_id')
							->where('vote_id', $informalVote->id)
							->get();
						$ids = [];
						if ($results && count($results)) {
							foreach ($results as $result) {
								$ids[] = (int) $result->user_id;
							}
						}
						$vote->informal_result_users = $ids;
					}
				}
			}
		}

		return [
			'success' => true,
			'votes' => $votes,
			'total_unvoted' => $total_unvoted,
			'finished' => count($votes) < $limit ? true : false
		];
	}

	// Get Active Informal Votes
	public function getActiveInformalVotes(Request $request)
	{
		$user = Auth::user();
		$votes = [];
		$settings = Helper::getSettings();
		$minsInformal = $minsSimple = $minsMileStone = 0;
		if ($settings['time_unit_informal'] == 'min')
			$minsInformal = (int) $settings['time_informal'];
		else if ($settings['time_unit_informal'] == 'hour')
			$minsInformal = (int) $settings['time_informal'] * 60;
		else if ($settings['time_unit_informal'] == 'day')
			$minsInformal = (int) $settings['time_informal'] * 60 * 24;

		if ($settings['time_unit_simple'] == 'min')
			$minsSimple = (int) $settings['time_simple'];
		else if ($settings['time_unit_simple'] == 'hour')
			$minsSimple = (int) $settings['time_simple'] * 60;
		else if ($settings['time_unit_simple'] == 'day')
			$minsSimple = (int) $settings['time_simple'] * 60 * 24;

		if ($settings['time_unit_milestone'] == 'min')
			$minsMileStone = (int) $settings['time_milestone'];
		else if ($settings['time_unit_milestone'] == 'hour')
			$minsMileStone = (int) $settings['time_milestone'] * 60;
		else if ($settings['time_unit_milestone'] == 'day')
			$minsMileStone = (int) $settings['time_milestone'] * 60 * 24;
		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'vote.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);
		$show_unvoted = $request->show_unvoted;
		$total_unvoted = 0;
		// Records
		if ($user) {
			if ($user->hasRole('member')) {
				$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
					->join('users', 'users.id', '=', 'proposal.user_id')
					->leftJoin('vote_result', function ($join) use ($user) {
						$join->on('vote_result.vote_id', '=', 'vote.id');
						$join->where('vote_result.user_id', $user->id);
					})
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->where('vote.type', 'informal')
					->where('vote.status', 'active')
					->where(function ($query) use ($search, $show_unvoted, $user) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
						if($show_unvoted) {
							$query->where('vote_result.type', null)->where('users.id', '!=', $user->id);
						}
					})
					->select([
						'proposal.id as proposalId',
						'proposal.type as proposalType',
						'proposal.title',
						'proposal.include_membership',
						'proposal.total_grant',
						'users.first_name',
						'users.last_name',
						'users.id as user_id',
						'vote.*',
						'vote_result.type as vote_result_type',
						'vote_result.value as vote_result_value',
						DB::raw('(CASE WHEN vote.content_type = \'grant\' OR proposal.type = \'admin-grant\' THEN proposal.total_grant WHEN vote.content_type = \'milestone\' THEN milestone.grant ELSE null END) AS euros'),
						DB::raw("(CASE WHEN vote.content_type = 'grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsInformal MINUTE, current_timestamp())
							WHEN vote.content_type = 'milestone' THEN TIMEDIFF(vote.created_at + INTERVAL $minsMileStone MINUTE, current_timestamp())
							WHEN vote.content_type = 'simple' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							WHEN vote.content_type = 'admin-grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							ELSE null END ) AS timeLeft")
					]);
					if($sort_key == 'vote_result_type' &&  $sort_direction == 'asc' ) {
						$votes = $votes->orderByRaw('-vote_result.type ASC');
					}
					$votes = $votes
					->orderBy($sort_key, $sort_direction)
					->groupBy('vote.id')
					->offset($start)
					->limit($limit)
					->get();
					$total_unvoted =  Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
						->join('users', 'users.id', '=', 'proposal.user_id')
						->leftJoin('vote_result', function ($join) use ($user) {
							$join->on('vote_result.vote_id', '=', 'vote.id');
							$join->where('vote_result.user_id', $user->id);
						})
						->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
						->where('vote.type', 'informal')
						->where('vote.status', 'active')
						->where('vote_result.type', null)
						->where('users.id', '!=', $user->id)
						->where(function ($query) use ($search) {
							if ($search) {
								$query->where('proposal.title', 'like', '%' . $search . '%')
									->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
									->orWhere('proposal.id', 'like', '%' . $search . '%');
							}
						})->count();
			} else {
				$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
					->join('users', 'users.id', '=', 'proposal.user_id')
					->where('vote.type', 'informal')
					->where('vote.status', 'active')
					->leftJoin('milestone', 'vote.milestone_id', '=', 'milestone.id')
					->where(function ($query) use ($search) {
						if ($search) {
							$query->where('proposal.title', 'like', '%' . $search . '%')
								->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
								->orWhere('proposal.id', 'like', '%' . $search . '%');
						}
					})
					->select([
						'proposal.id as proposalId',
						'proposal.type as proposalType',
						'proposal.title',
						'proposal.include_membership',
						'proposal.total_grant',
						'users.first_name',
						'users.last_name',
						'users.id as user_id',
						'vote.*',
						DB::raw('(CASE WHEN vote.content_type = \'grant\' OR proposal.type = \'admin-grant\' THEN proposal.total_grant WHEN vote.content_type = \'milestone\' THEN milestone.grant ELSE null END) AS euros'),
						DB::raw("(CASE WHEN vote.content_type = 'grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsInformal MINUTE, current_timestamp())
							WHEN vote.content_type = 'milestone' THEN TIMEDIFF(vote.created_at + INTERVAL $minsMileStone MINUTE, current_timestamp())
							WHEN vote.content_type = 'simple' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							WHEN vote.content_type = 'admin-grant' THEN TIMEDIFF(vote.created_at + INTERVAL $minsSimple MINUTE, current_timestamp())
							ELSE null END ) AS timeLeft")
					])
					->orderBy($sort_key, $sort_direction)
					->offset($start)
					->limit($limit)
					->get();
			}
		}

		return [
			'success' => true,
			'votes' => $votes,
			'total_unvoted' => $total_unvoted,
			'finished' => count($votes) < $limit ? true : false
		];
	}

	public function getDeatilProposal2($proposalId)
	{
		$proposal = Proposal::where('id', $proposalId)
		->with([
			'user',
			'user.profile',
			'user.shuftipro',
			'bank',
			'crypto',
			'grants',
			'milestones',
			'citations',
			'citations.repProposal',
			'citations.repProposal.user',
			'citations.repProposal.user.profile',
			'members',
			'files',
			'votes',
			'onboarding'
		])
		->has('user')
		->has('user.profile')
		->first();
		if (!$proposal) {
			return [
				'success' => false,
				'message' => 'Proposal Not found'
			];
		}
		$proposal->makeVisible('user');
		$proposal->user->makeVisible(['profile']);
		foreach ($proposal->citations as $citation) {
			$citation->repProposal->makeVisible('user');
			$citation->repProposal->user->makeVisible('profile');
		}

		return [
			'success' => true,
			'proposal' => $proposal
		];
	}

	// Get Proposal Changes
	public function getPublicProposalChanges($proposalId)
	{
		$changes = [];

		$changes = ProposalChange::with(['user', 'user.profile'])
			->has('user')
			->has('user.profile')
			->where('proposal_id', $proposalId)
			->orderBy('created_at', 'desc')
			->get();

		return [
			'success' => true,
			'changes' => $changes
		];
	}

	public function exportProposal(Request $request)
	{
		$search = $request->search;
		$sort_key = $request->sort_key ?? 'proposal.id';
		$sort_direction = $request->sort_direction ?? 'desc';
		$proposals = Proposal::with(['votes', 'onboarding'])
			->join('users', 'users.id', '=', 'proposal.user_id')
			->join('profile', 'profile.user_id', '=', 'proposal.user_id')
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.member_reason', 'like', '%' . $search . '%')
						->orWhere('proposal.status', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%')
						->orWhere('proposal.short_description', 'like', '%' . $search . '%')
						->orWhere('proposal.type', 'like', '%' . $search . '%');
				}
			})
			->select([
				'proposal.*',
				'users.first_name',
				'users.last_name',
				'profile.forum_name',
				'profile.telegram'
			])
			->orderBy($sort_key, $sort_direction)
			->get();
			return Excel::download(new ProposalExport($proposals), 'proposal.csv');
	}

	public function resendKycKangaroo(Request $request)
	{
		$user = Auth::user();
		$user_id = $user->hasRole('admin') ? $request->user_id : $user->id;
		$shuftipro_temp = ShuftiproTemp::where('user_id', $user_id)->whereNotNull('invite_id')->first();
		if ($shuftipro_temp) {
			$now = Carbon::now();
			if ($now->diffInMinutes($shuftipro_temp->invited_at) <= 60) {
				return [
					'success' => false,
					'message' => 'You can only resend your link once every hour.',
				];
			}
			$user = User::find($user_id);
			$kyc_response = Helper::inviteKycKangaroo("$user->first_name $user->last_name", $user->email, $shuftipro_temp->invite_id);
			if (!$kyc_response || $kyc_response['success'] == false) {
				return [
					'success' => false,
					'message' => $kyc_response['message'] ?? '',
					'invite' => $kyc_response['invite'] ?? null,
				];
			}
			$shuftipro_temp->invited_at = now();
			$shuftipro_temp->save();
			return [
				'success' => true,
				'message' => $kyc_response['message'] ?? '',
			];
		} else {
			return ['success' => false];
		}
	}

	public function getTrackingProposal($proposalId)
	{
		$trackings = GrantTracking::where('proposal_id', $proposalId)->get();
		return [
			'success' => true,
			'trackings' => $trackings
		];
	}

	public function getInfoVoteProposal($proposalId, $voteId)
	{
		$settings = Helper::getSettings();
		$minsInformal = $minsSimple = $minsMileStone = $minsFormal = 0;

		if ($settings['time_unit_formal'] == 'min')
		$minsFormal = (int) $settings['time_formal'];
		else if ($settings['time_unit_formal'] == 'hour')
		$minsFormal = (int) $settings['time_formal'] * 60;
		else if ($settings['time_unit_formal'] == 'day')
		$minsFormal = (int) $settings['time_formal'] * 60 * 24;

		if ($settings['time_unit_informal'] == 'min')
		$minsInformal = (int) $settings['time_informal'];
		else if ($settings['time_unit_informal'] == 'hour')
		$minsInformal = (int) $settings['time_informal'] * 60;
		else if ($settings['time_unit_informal'] == 'day')
		$minsInformal = (int) $settings['time_informal'] * 60 * 24;

		if ($settings['time_unit_simple'] == 'min')
		$minsSimple = (int) $settings['time_simple'];
		else if ($settings['time_unit_simple'] == 'hour')
		$minsSimple = (int) $settings['time_simple'] * 60;
		else if ($settings['time_unit_simple'] == 'day')
		$minsSimple = (int) $settings['time_simple'] * 60 * 24;

		if ($settings['time_unit_milestone'] == 'min')
		$minsMileStone = (int) $settings['time_milestone'];
		else if ($settings['time_unit_milestone'] == 'hour')
		$minsMileStone = (int) $settings['time_milestone'] * 60;
		else if ($settings['time_unit_milestone'] == 'day')
		$minsMileStone = (int) $settings['time_milestone'] * 60 * 24;
		$proposal = Proposal::find($proposalId);
		if (!$proposal) {
			return [
				'success' => false,
				'trackings' => 'Proposal Not found'
			];
		}
		$vote = Vote::find($voteId);
		if (!$vote) {
			return [
				'success' => false,
				'trackings' => 'Vote Not found'
			];
		}
		$timeLeft = Carbon::parse($vote->created_at);
		if ($vote->content_type == 'grant') {
			if ($vote->type == 'informal') {
				$timeLeft = Carbon::parse($vote->created_at)->addMinute($minsInformal);
			} else {
				$timeLeft = Carbon::parse($vote->created_at)->addMinute($minsFormal);
			}
		} else if ($vote->content_type == 'milestone') {
			$timeLeft = Carbon::parse($vote->created_at)->addMinute($minsMileStone);
		} else if ($vote->content_type == 'simple') {
			$timeLeft = Carbon::parse($vote->created_at)->addMinute($minsSimple);
		} else if ($vote->content_type == 'admin-grant') {
			$timeLeft = Carbon::parse($vote->created_at)->addMinute($minsSimple);
		} else if ($vote->content_type == 'advance-payment') {
			$timeLeft = Carbon::parse($vote->created_at)->addMinute($minsSimple);
		}
		$vote->timeLeft = $timeLeft;
		$proposal->vote = $vote;
		$milestone = null;
		if ($vote->milestone_id) {
			$milestone = Milestone::find($vote->milestone_id);
			if ($milestone) {
				$milestonePosition = Helper::getPositionMilestone($milestone);
				$totalMilesones = Milestone::where('proposal_id', $proposalId)->count();
				$milestone->milestonePosition = "$totalMilesones / $milestonePosition";
			}
		}
		$proposal->milestone = $milestone;
		$proposal->voteResults  = VoteResult::join('profile', 'profile.user_id', '=', 'vote_result.user_id')
		->where('vote_id',  $voteId)
		->where('proposal_id', $proposal->id)
		->select([
			'vote_result.*',
			'profile.forum_name'
		])
			->orderBy('vote_result.created_at', 'asc')
			->get();
		$summary_preview = '';
		if($proposal->type == 'simple') {
			$summary_preview = $proposal->short_description;
		} else if($proposal->type == 'grant') {
			$summary_preview = $proposal->short_description;
		} else if($proposal->type == 'admin-grant') {
			$summary_preview = $proposal->things_delivered;
		} else if($proposal->type == 'advance-payment') {
			$summary_preview =  $proposal->amount_advance_detail;
		}
		$proposal->summary_preview = $summary_preview;
		return $proposal;
	}

	public function exportCSVProposal($proposalId, $voteId)
	{
		$proposal = $this->getInfoVoteProposal($proposalId, $voteId);
        return Excel::download(new VoteResultExport($proposal), "proposal_" . $proposalId . "_vote_results_.xlsx");
	}

	public function generateVoteProposalDetail($proposalId, $voteId)
	{
		$proposal = $this->getInfoVoteProposal($proposalId, $voteId);
		$pdf = App::make('dompdf.wrapper');
        $pdfFile = $pdf->loadView('pdf.vote_detail', compact('proposal'));
		return $pdf->download("vote_results_$voteId.pdf");
	}
}
