<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\App;
use PDF;

use App\User;
use App\PreRegister;
use App\Profile;
use App\PendingAction;
use App\Proposal;
use App\ProposalHistory;
use App\ProposalChange;
use App\ProposalChangeSupport;
use App\ProposalChangeComment;
use App\Bank;
use App\Crypto;
use App\Grant;
use App\Milestone;
use App\Team;
use App\Setting;
use App\Vote;
use App\VoteResult;
use App\OnBoarding;
use App\Reputation;
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerTriggerMember;
use App\EmailerAdmin;
use App\Exports\ActiveGrantExport;
use App\Exports\DosFeeExport;
use App\Exports\MilestoneExport;
use App\Exports\MyReputationExport;
use App\Exports\ProposalMentorExport;
use App\Exports\SurveyDownvoteExport;
use App\Exports\SurveyVoteExport;
use App\Exports\SurveyWinExport;
use App\Exports\UserExport;
use App\Exports\InvoiceExport;
use App\Exports\SurveyVoteRfpExport;
use App\Shuftipro;
use App\ShuftiproTemp;
use App\FinalGrant;
use App\Invoice;

use App\Http\Helper;
use App\IpHistory;
use App\Mail\AdminAlert;
use App\Mail\ComplianceReview;
use App\Mail\InviteAdminMail;
use App\Mail\UserAlert;
use App\Mail\ResetKYC;
use App\Mail\ResetPasswordLink;
use App\MilestoneLog;
use App\MilestoneReview;
use App\SignatureGrant;
use App\Survey;
use App\SurveyDownVoteRank;
use App\SurveyDownVoteResult;
use App\SurveyRank;
use App\SurveyResult;
use App\SurveyRfpBid;
use App\SurveyRfpResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use Spatie\Permission\Models\Permission;

class AdminController extends Controller
{
	// Test Email
	public function testEmail()
	{
		// Emailer Admin
		$emailerData = Helper::getEmailerData();
	}

	// Update Emailer Trigger Member
	public function updateEmailerTriggerMember($recordId, Request $request)
	{
		$user = Auth::user();
		if ($user && $user->hasRole('admin')) {
			$record = EmailerTriggerMember::find($recordId);

			if ($record) {
				$enabled = (int) $request->get('enabled');
				$content = $request->get('content');

				$record->enabled = $enabled;
				if ($content) $record->content = $content;

				$record->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Update Emailer Trigger Admin
	public function updateEmailerTriggerAdmin($recordId, Request $request)
	{
		$user = Auth::user();
		if ($user && $user->hasRole('admin')) {
			$record = EmailerTriggerAdmin::find($recordId);

			if ($record) {
				$enabled = (int) $request->get('enabled');
				$record->enabled = $enabled;
				$record->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Update Emailer Trigger User
	public function updateEmailerTriggerUser($recordId, Request $request)
	{
		$user = Auth::user();
		if ($user && $user->hasRole('admin')) {
			$record = EmailerTriggerUser::find($recordId);

			if ($record) {
				$enabled = (int) $request->get('enabled');
				$content = $request->get('content');

				$record->enabled = $enabled;
				if ($content) $record->content = $content;

				$record->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Delete Emailer Admin
	public function deleteEmailerAdmin($adminId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			EmailerAdmin::where('id', $adminId)->delete();
			return ['success' => true];
		}

		return ['success' => false];
	}

	// Add Emailer Admin
	public function addEmailerAdmin(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$email = $request->get('email');
			if (!$email) {
				return [
					'success' => false,
					'message' => 'Invalid email address'
				];
			}

			$record = EmailerAdmin::where('email', $email)->first();
			if ($record) {
				return [
					'success' => false,
					'message' => 'This emailer admin email address is already in use'
				];
			}

			$record = new EmailerAdmin;
			$record->email = $email;
			$record->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Add Reputation
	public function addReputation(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$reputation = (float) $request->get('reputation');

			// User Check
			$user = User::with('profile')->where('id', $userId)->first();
			if (!$user || !isset($user->profile)) {
				return [
					'success' => false,
					'message' => 'Invalid user'
				];
			}

			// Reputation Check
			if ($reputation <= 0) {
				return [
					'success' => false,
					'message' => 'Invalid reputation value'
				];
			}

			$user->profile->rep = (float) $user->profile->rep + $reputation;
			$user->profile->save();
			Helper::createRepHistory($user->id,  $reputation, $user->profile->rep, 'Gained', 'Admin Action', null. null, 'addReputation');

			// Create Reputation Tracking
			$record = new Reputation;
			$record->user_id = $user->id;
			$record->value = $reputation;
			$record->event = "Admin Action";
			$record->type = "Gained";
			$record->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Subtract Reputation
	public function subtractReputation(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$reputation = (float) $request->get('reputation');

			// User Check
			$user = User::with('profile')->where('id', $userId)->first();
			if (!$user || !isset($user->profile)) {
				return [
					'success' => false,
					'message' => 'Invalid user'
				];
			}

			// Reputation Check
			if ($reputation <= 0) {
				return [
					'success' => false,
					'message' => 'Invalid reputation value'
				];
			}

			if ((float) $user->profile->rep < $reputation) {
				return [
					'success' => false,
					'message' => "SUBTRACT amount cannot be higher than the current reputation value"
				];
			}

			$user->profile->rep = (float) $user->profile->rep - $reputation;
			$user->profile->save();
			Helper::createRepHistory($user->id,  -$reputation, $user->profile->rep, 'Lost', 'Admin Action', null, null, 'subtractReputation');

			// Create Reputation Tracking
			$record = new Reputation;
			$record->user_id = $user->id;
			$record->value = -$reputation;
			$record->event = "Admin Action";
			$record->type = "Lost";
			$record->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Change User Type
	public function changeUserType(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$userType = $request->get('userType');

			if ($userId && $userType) {
				$user = User::find($userId);
				if (!$user) {
					return [
						'success' => false,
						'message' => 'Invalid user'
					];
				}

				if ($userType == "member" || $userType == "voting associate")
					Helper::upgradeToVotingAssociate($user);
				else if ($userType == "participant" || $userType == "associate") {
					$user->is_member = 0;
					$user->is_participant = 1;
					$user->removeRole('member');
					$user->assignRole('participant');
					$user->save();
				}
				if ($user->hasRole('member')) {
					$user->check_first_compeleted_proposal = 0;
					$user->save();
					$proposals = Proposal::where('user_id', $user->id)->where('type_status', 'pending')->get();
					foreach ($proposals as $proposal) {
						Helper::completeProposal($proposal, false);
						$proposal->type_status = 'completed';
						$proposal->save();
					}
				}

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Approve KYC
	public function approveKYC($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$admin = $user;
			$user = User::with(['shuftipro', 'profile'])->where('id', $userId)->first();
			if ($user && $user->profile && $user->shuftipro) {
				$user->profile->step_kyc = 1;
				$user->profile->save();

				$user->shuftipro->status = 'approved';
				$user->shuftipro->reviewed = 1;
				$user->shuftipro->save();

				$user->shuftipro->manual_approved_at = $user->updated_at;
				$user->shuftipro->manual_reviewer = $admin->email;
				$user->shuftipro->save();

				// Emailer User
				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'AML Approve', $emailerData);

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Deny KYC
	public function denyKYC($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$admin = $user;
			$user = User::with(['shuftipro', 'profile'])->where('id', $userId)->first();
			if ($user && $user->profile && $user->shuftipro) {
				$user->profile->step_kyc = 0;
				$user->profile->save();

				$user->shuftipro->status = 'denied';
				$user->shuftipro->reviewed = 1;
				$user->shuftipro->save();

				$user->shuftipro->manual_approved_at = $user->updated_at;
				$user->shuftipro->manual_reviewer = $admin->email;
				$user->shuftipro->save();

				// Emailer User
				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'AML Deny', $emailerData);

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Reset KYC
	public function resetKYC($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$message = trim($request->get('message'));
			if (!$message) {
				return [
					'success' => false,
					'message' => 'Message is required'
				];
			}

			$user = User::with(['profile'])->where('id', $userId)->first();
			if ($user && $user->profile) {
				$user->profile->step_kyc = 0;
				$user->profile->save();

				Shuftipro::where('user_id', $user->id)->delete();
				ShuftiproTemp::where('user_id', $user->id)->delete();

				// Emailer User
				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'AML Reset', $emailerData);

				Mail::to($user)->send(new ResetKYC($message));

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Reset User Password
	public function resetUserPassword(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$userId = (int) $request->get('userId');
			$password = $request->get('password');

			if ($userId && $password) {
				$user = User::find($userId);
				if (!$user) {
					return [
						'success' => false,
						'message' => 'Invalid user'
					];
				}

				$user->password = Hash::make($password);
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Get Move-To-Formal
	public function getMoveToFormalVotes(Request $request)
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

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// We need to get successfully completed informal votes
		if ($user->hasRole('admin')) {
			$votes = Vote::join('proposal', 'proposal.id', '=', 'vote.proposal_id')
				->join('users', 'users.id', '=', 'proposal.user_id')
				->where('vote.status', 'completed')
				->where('vote.result', 'success')
				->where('vote.type', 'informal')
				->where('vote.content_type', '!=', 'grant')
				->where('vote.formal_vote_id', 0)
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%');
					}
				})
				->select([
					'proposal.id as proposalId',
					'proposal.type as proposalType',
					'proposal.title',
					'vote.*'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}

		return [
			'success' => true,
			'votes' => $votes,
			'finished' => count($votes) < $limit ? true : false
		];
	}

	// Get Pending Grant Onboardings
	public function getPendingGrantOnboardings(Request $request)
	{
		$user = Auth::user();
		$onboardings = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$hide_denined = $request->hide_denined;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'onboarding.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole('admin')) {
			$onboardings = OnBoarding::with([
				'user',
				'user.profile',
				'user.shuftipro',
				'user.shuftiproTemp',
				'proposal',
				'proposal.votes',
				'proposal.signatures'
			])
				->has('user')
				->has('proposal')
				->join('proposal', 'proposal.id', '=', 'onboarding.proposal_id')
				->join('users', 'users.id', '=', 'onboarding.user_id')
				->leftJoin('final_grant', 'onboarding.proposal_id', '=', 'final_grant.proposal_id')
				->leftJoin('shuftipro', 'shuftipro.user_id', '=', 'onboarding.user_id')
				->where('final_grant.id', null)
				->whereNotExists(function ($query) {
					$query->select('id')
						->from('vote')
						->whereColumn('onboarding.proposal_id', 'vote.proposal_id')
						->where('vote.type', 'formal');
				})
				->where('onboarding.status', 'pending')
				->where(function ($query) use ($search, $hide_denined) {
					if ($search) {
						$query->where('proposal.title', 'like', '%' . $search . '%');
					}
					if($hide_denined) {
						$query->where('shuftipro.status', '!=', 'denied')
						->where('onboarding.compliance_status', '!=', 'denied');
					}
				})
				->select([
					'onboarding.*',
					'proposal.title as proposal_title',
					'proposal.include_membership',
					'proposal.short_description',
					'proposal.form_submitted',
					'proposal.signature_request_id',
					'proposal.hellosign_form',
					'proposal.signed_count',
					'shuftipro.status as shuftipro_status',
					'shuftipro.reviewed as shuftipro_reviewed'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();

			if ($onboardings) {
				foreach ($onboardings as $onboarding) {
					$user = $onboarding->user;
					if (
						$user &&
						isset($user->shuftipro) &&
						isset($user->shuftipro->data)
					) {
						$user->shuftipro_data = json_decode($user->shuftipro->data);
					}
				}
			}
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

	// Get Emailer Data
	public function getEmailerData(Request $request)
	{
		$user = Auth::user();
		$data = [];

		if ($user && $user->hasRole('admin')) {
			$admins = EmailerAdmin::where('id', '>', 0)->orderBy('email', 'asc')->get();
			$triggerAdmin = EmailerTriggerAdmin::where('id', '>', 0)->orderBy('id', 'asc')->get();
			$triggerUser = EmailerTriggerUser::where('id', '>', 0)->orderBy('id', 'asc')->get();
			$triggerMember = EmailerTriggerMember::where('id', '>', 0)->orderBy('id', 'asc')->get();
			$data = [
				'admins' => $admins,
				'triggerAdmin' => $triggerAdmin,
				'triggerUser' => $triggerUser,
				'triggerMember' => $triggerMember,
				// 'data' => $request->headers->get('origin')
			];
		}

		return [
			'success' => true,
			'data' => $data
		];
	}

	// Start Formal Milestone Voting
	public function startFormalMilestoneVoting(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$proposalId = (int) $request->get('proposalId');
			$voteId = (int) $request->get('voteId');

			$proposal = Proposal::find($proposalId);
			$informalVote = Vote::find($voteId);
			$milestone = Milestone::find($informalVote->milestone_id);

			// Proposal Check
			if (!$proposal) {
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
			Helper::createGrantTracking($proposal->id, "Milestone $milestonePosition stared formal vote", 'milestone_' .$milestonePosition. '_started_formal_vote');
			// Emailer Admin
			$emailerData = Helper::getEmailerData();
			Helper::triggerAdminEmail('Vote Started', $emailerData, $proposal, $vote);

			// Emailer Member
			Helper::triggerMemberEmail('New Vote', $emailerData, $proposal, $vote);

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Start Formal Voting
	public function startFormalVoting(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$force = (int) $request->get('force');
			$proposalId = (int) $request->get('proposalId');
			$proposal = Proposal::find($proposalId);

			// Proposal Check
			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Informal Vote Check
			$informalVote = Vote::where('proposal_id', $proposalId)
				->where('type', 'informal')
				->where('content_type', '!=', 'milestone')
				->where('status', 'completed')
				->first();
			if (!$informalVote) {
				return [
					'success' => false,
					'message' => "Formal vote can't be started"
				];
			}

			// Onboarding Check
			$onboarding = null;
			if ($proposal->type == "grant") {
				$onboarding = OnBoarding::where('proposal_id', $proposalId)->first();
				if (!$onboarding || $onboarding->status != 'pending') {
					return [
						'success' => false,
						'message' => 'Invalid proposal'
					];
				}
			}

			/* 3 Requirements Check */
			// if (!$force && $proposal->type == "grant") {
			// 	if (!$proposal->form_submitted) {
			// 		return [
			// 			'success' => false,
			// 			'message' => "Proposal payment form should be submitted"
			// 		];
			// 	}

			// 	$op = User::with(['profile', 'shuftipro'])
			// 						->has('profile')
			// 						->has('shuftipro')
			// 						->where('id', $proposal->user_id)
			// 						->first();

			// 	if (!$op || $op->shuftipro->status != "approved") {
			// 		return [
			// 			'success' => false,
			// 			'message' => "OP should have KYC approved"
			// 		];
			// 	}

			// 	if (!$proposal->signature_request_id || !$proposal->hellosign_form) {
			// 		return [
			// 			'success' => false,
			// 			'message' => "Grant Agreement should be signed by all signers"
			// 		];
			// 	}
			// }
			/* 3 Requirements Check End */

			$vote = Helper::startFormalVote($informalVote);
			if (!$vote) {
				return [
					'success' => false,
					'message' => 'Formal vote has been already started'
				];
			}

			if ($onboarding) {
				$onboarding->status = "completed";
				$onboarding->compliance_status = "approved";
				$onboarding->compliance_reviewed_at = now();
				$onboarding->admin_email = $user->email;
				$onboarding->force_to_formal = 1;
				$onboarding->save();
				Helper::createGrantTracking($proposalId, "ETA compliance complete", 'eta_compliance_complete');
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

	// Update Global Settings
	public function updateGlobalSettings(Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			// Validator
			$validator = Validator::make($request->all(), [
				// 'coo_email' => 'required|email',
				'cfo_email' => 'required|email',
				'board_member_email' => 'required|email',
				'time_before_op_do' => 'required',
				'time_unit_before_op_do' => 'required',
				'can_op_start_informal' => 'required',
				'time_before_op_informal' => 'required',
				'time_unit_before_op_informal' => 'required',
				'time_before_op_informal_simple' => 'required',
				'time_unit_before_op_informal_simple' => 'required',
				'time_informal' => 'required',
				'time_unit_informal' => 'required',
				'time_formal' => 'required',
				'time_unit_formal' => 'required',
				'time_simple' => 'required',
				'time_unit_simple' => 'required',
				'time_milestone' => 'required',
				'time_unit_milestone' => 'required',
				'dos_fee_amount' => 'required',
				'btc_address' => 'required',
				'eth_address' => 'required',
				'rep_amount' => 'required',
				'minted_ratio' => 'required',
				'op_percentage' => 'required',
				'pass_rate' => 'required',
				'quorum_rate' => 'required',
				'pass_rate_simple' => 'required',
				'quorum_rate_simple' => 'required',
				'pass_rate_milestone' => 'required',
				'quorum_rate_milestone' => 'required',
				'need_to_approve' => 'required',
				'autostart_grant_formal_votes' => 'required',
				'autostart_simple_formal_votes' => 'required',
				'autostart_admin_grant_formal_votes' => 'required',
				'autostart_advance_payment_formal_votes' => 'required',
				'autoactivate_grants' => 'required',
				// 'president_email' => 'required',
				'gate_new_grant_votes' => 'required',
				'gate_new_milestone_votes' => 'required',
				'compliance_admin' => 'required',
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$items = [
				// 'coo_email' => $request->get('coo_email'),
				'cfo_email' => $request->get('cfo_email'),
				'board_member_email' => $request->get('board_member_email'),
				'time_before_op_do' => $request->get('time_before_op_do'),
				'time_unit_before_op_do' => $request->get('time_unit_before_op_do'),
				'can_op_start_informal' => $request->get('can_op_start_informal'),
				'time_before_op_informal' => $request->get('time_before_op_informal'),
				'time_unit_before_op_informal' => $request->get('time_unit_before_op_informal'),
				'time_before_op_informal_simple' => $request->get('time_before_op_informal_simple'),
				'time_unit_before_op_informal_simple' => $request->get('time_unit_before_op_informal_simple'),
				'time_informal' => $request->get('time_informal'),
				'time_unit_informal' => $request->get('time_unit_informal'),
				'time_formal' => $request->get('time_formal'),
				'time_unit_formal' => $request->get('time_unit_formal'),
				'time_simple' => $request->get('time_simple'),
				'time_unit_simple' => $request->get('time_unit_simple'),
				'time_milestone' => $request->get('time_milestone'),
				'time_unit_milestone' => $request->get('time_unit_milestone'),
				'dos_fee_amount' => $request->get('dos_fee_amount'),
				'btc_address' => $request->get('btc_address'),
				'eth_address' => $request->get('eth_address'),
				'rep_amount' => $request->get('rep_amount'),
				'minted_ratio' => $request->get('minted_ratio'),
				'op_percentage' => $request->get('op_percentage'),
				'pass_rate' => $request->get('pass_rate'),
				'quorum_rate' => $request->get('quorum_rate'),
				'pass_rate_simple' => $request->get('pass_rate_simple'),
				'quorum_rate_simple' => $request->get('quorum_rate_simple'),
				'pass_rate_milestone' => $request->get('pass_rate_milestone'),
				'quorum_rate_milestone' => $request->get('quorum_rate_milestone'),
				'need_to_approve' => $request->get('need_to_approve'),
				'autostart_grant_formal_votes' => $request->get('autostart_grant_formal_votes'),
				'autostart_simple_formal_votes' => $request->get('autostart_simple_formal_votes'),
				'autostart_admin_grant_formal_votes' => $request->get('autostart_admin_grant_formal_votes'),
				'autostart_advance_payment_formal_votes' => $request->get('autostart_advance_payment_formal_votes'),
				'autoactivate_grants' => $request->get('autoactivate_grants'),
				// 'president_email' => $request->get('president_email'),
				'gate_new_grant_votes' => $request->get('gate_new_grant_votes'),
				'gate_new_milestone_votes' => $request->get('gate_new_milestone_votes'),
				'compliance_admin' => $request->get('compliance_admin'),
			];

			foreach ($items as $name => $value) {
				$setting = Setting::where('name', $name)->first();
				if ($setting) {
					$setting->value = $value;
					$setting->save();
				} else {
					$setting = new Setting();
					$setting->value = $value;
					$setting->save();
				}
			}

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Approve Payment Proposal
	public function approveProposalPayment($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			// Proposal Check
			$proposal = Proposal::find($proposalId);

			if (!$proposal || $proposal->status != "payment" || !$proposal->dos_paid) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Update Proposal
			$proposal->status = "approved";
			$proposal->save();

			// Update Timestamp
			$proposal->approved_at = $proposal->updated_at;
			$proposal->save();

			// Increase Change Count
			$proposal->changes = (int) $proposal->changes + 1;
			$proposal->save();

			$emailerData = Helper::getEmailerData();

			// Emailer User
			$op = User::find($proposal->user_id);
			if ($op) Helper::triggerUserEmail($op, 'DOS Confirmation', $emailerData);

			// Emailer Member
			Helper::triggerMemberEmail('New Proposal Discussion', $emailerData, $proposal);
			Helper::createGrantTracking($proposalId, "Entered discussion phase", 'discussion_phase');
			return ['success' => true];
		}

		return ['success' => false];
	}

	// Deny Payment Proposal
	public function denyProposalPayment($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			// Proposal Check
			$proposal = Proposal::find($proposalId);
			if (!$proposal || $proposal->status != "payment" || !$proposal->dos_paid) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// This action is for crypto payments (not reputation). So we don't give rep back to OP

			$proposal->rep = 0;
			$proposal->dos_paid = 0;
			$proposal->dos_txid = null;
			$proposal->dos_eth_amount = null;
			$proposal->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Approve Proposal - Waiting for Payment
	public function approveProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$proposal = Proposal::find($proposalId);

			if ($proposal) {
				if ($proposal->type == 'admin-grant' || $proposal->type == 'advance-payment') {
                    $proposal->status = 'approved';

					$proposal->save();
					$proposal->approved_at = $proposal->updated_at;
					$proposal->save();

					Helper::createGrantTracking($proposalId, "Approved by admin", 'approved_by_admin');
					Helper::createGrantTracking($proposal->id, "Entered discussion phase", 'discussion_phase', $proposal->approved_at);
					if($proposal->type == 'admin-grant') {
						$proposalChange = new ProposalChange();
						$proposalChange->proposal_id = $proposal->id;
						$proposalChange->user_id = $proposal->user_id;
						$proposalChange->what_section = "general_discussion";
						$proposalChange->created_at = $proposal->approved_at;
						$proposalChange->updated_at = $proposal->approved_at;
						$proposalChange->save();
					}

				} else {
					$proposal->status = 'payment';
					$proposal->save();

					Helper::createGrantTracking($proposalId, "Approved by admin", 'approved_by_admin');
				}
				$op = User::find($proposal->user_id);

				// Emailer User
				if ($op) {
					$emailerData = Helper::getEmailerData();
					Helper::triggerUserEmail($op, 'Admin Approval', $emailerData, $proposal);

				}
			}
		}

		return ['success' => true];
	}

	// Deny Proposal
	public function denyProposal($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$reason = $request->get('reason');
			$proposal = Proposal::find($proposalId);

			if (!$reason) {
				return [
					'success' => false,
					'message' => 'Input deny reason'
				];
			}

			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Proposal does not exist'
				];
			}

			$proposal->status = 'denied';
			$proposal->deny_reason = $reason;
			$proposal->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Approve Participant Request from Pending Action
	public function approveParticipantRequest($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$participant = User::find($userId);

			if ($participant && $participant->hasRole('participant')) {
				$pendingAction = PendingAction::where('user_id', $userId)->first();

				if ($pendingAction && $pendingAction->status == 'new') {
					$pendingAction->status = 'pending_kyc';
					$pendingAction->save();
				}
			}
		}

		return ['success' => true];
	}

	// Deny Participant Request from Pending Action
	public function denyParticipantRequest($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$participant = User::find($userId);

			if ($participant && $participant->hasRole('participant')) {
				$pendingAction = PendingAction::where('user_id', $userId)->first();

				// Remove Pending Action
				if ($pendingAction && $pendingAction->status == 'new') {
					$pendingAction->delete();
				}
			}
		}

		return ['success' => true];
	}

	// Revoke Participant from Pending Action
	public function revokeParticipant($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$participant = User::find($userId);

			if ($participant && $participant->hasRole('participant')) {
				$pendingAction = PendingAction::where('user_id', $userId)->first();

				// Remove Pending Action
				if ($pendingAction && $pendingAction->status == 'pending_kyc') {
					$pendingAction->delete();
				}
			}
		}

		return ['success' => true];
	}

	// Activate Participant to Member
	public function activateParticipant($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::find($userId);

			if ($user) {
				$user->status = 'approved';
				$user->save();

				Helper::upgradeToVotingAssociate($user);

				// Remove Pending Action
				$pendingAction = PendingAction::where('user_id', $userId)->first();
				if ($pendingAction) $pendingAction->delete();
			}
		}

		return ['success' => true];
	}

	// Deny Participant
	public function denyParticipant($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::find($userId);

			if ($user) {
				$user->status = 'denied';
				$user->save();

				// Remove Pending Action
				$pendingAction = PendingAction::where('user_id', $userId)->first();
				if ($pendingAction) $pendingAction->delete();
			}
		}

		return ['success' => true];
	}

	// Activate Grant
	public function activateGrant($grantId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$finalGrant = FinalGrant::with(['proposal', 'user'])
				->has('proposal')
				->has('user')
				->where('id', $grantId)
				->first();

			if (!$finalGrant || $finalGrant->status != "pending") {
				return [
					'success' => false,
					'message' => 'Invalid grant'
				];
			}

			$file = $request->file('file');

			if ($file) {
				$path = $file->store('final_doc');
				$url = Storage::url($path);

				$finalGrant->proposal->final_document = $url;
				$finalGrant->proposal->save();

				$finalGrant->status = 'active';
				$finalGrant->save();

				Helper::createGrantLogging([
					'proposal_id' => $finalGrant->proposal->id,
					'final_grant_id' => $finalGrant->id,
					'user_id' => $user->id,
					'email' => $user->email,
					'role' => 'admin',
					'type' => 'completed',
				]);

				$userGrant = User::where('id', $finalGrant->user_id)->first();
				if ($userGrant) {
					$userGrant->check_active_grant = 1;
					$userGrant->save();
				}
				$user = $finalGrant->user;

				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'Grant Live', $emailerData);
				Helper::createGrantTracking($finalGrant->proposal_id, 'Grant activated by ETA', 'grant_activated');
				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// begin Grant
	public function beginGrant($grantId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$finalGrant = FinalGrant::with(['proposal', 'user'])
				->has('proposal')
				->has('user')
				->where('id', $grantId)
				->first();

			if (!$finalGrant || $finalGrant->status != "pending") {
				return [
					'success' => false,
					'message' => 'Invalid grant'
				];
			}
			$signatureGrantsSigned = SignatureGrant::where('proposal_id', $finalGrant->proposal_id)->where('signed', 1)->count();
			$signatureGrantsTotal = SignatureGrant::where('proposal_id', $finalGrant->proposal_id)->count();
			if ($signatureGrantsSigned != $signatureGrantsTotal) {
				return [
					'success' => false,
					'message' => 'Please wait for the full signature'
				];
			}
			$finalGrant->status = 'active';
			$finalGrant->save();
			$userGrant = User::where('id', $finalGrant->user_id)->first();
			if ($userGrant) {
				$userGrant->check_active_grant = 1;
				$userGrant->save();
			}
			$user = $finalGrant->user;

			$emailerData = Helper::getEmailerData();
			Helper::triggerUserEmail($user, 'Grant Live', $emailerData);
			Helper::createGrantTracking($finalGrant->proposal_id, 'Grant activated by ETA', 'grant_activated');

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Approve Pre Register - Invitation
	public function approvePreRegister($recordId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$record = PreRegister::find($recordId);
			if ($record) {
				$email = $record->email;

				// User Check
				$user = User::where('email', $email)->first();
				if ($user) {
					$record->status = "completed";
					$record->hash = null;
					$record->save();

					return [
						'success' => false,
						'message' => "User with the same email address already exists"
					];
				}

				// Send Invitation
				$hash = Str::random(11);
				$record->status = 'approved';
				$record->hash = $hash;
				$record->save();

				$url = $request->header('origin') . '/register/form?hash=' . $hash;

				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'Pre-Register Approve', $emailerData, null, null, null, ['url' => $url]);

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Deny Pre Register
	public function denyPreRegister($recordId)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$record = PreRegister::find($recordId);
			if ($record) {
				$email = $record->email;

				// User Check
				$user = User::where('email', $email)->first();
				if ($user) {
					$record->status = "completed";
					$record->hash = null;
					$record->save();

					return [
						'success' => false,
						'message' => "User with the same email address already exists"
					];
				}

				$record->status = 'denied';
				$record->hash = null;
				$record->save();

				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'Pre-Register Deny', $emailerData);

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Allow Access User
	public function allowAccessUser($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::find($userId);

			if ($user && !$user->hasRole('admin')) {
				$user->can_access = 1;
				$user->save();

				// Emailer User
				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'Access Granted', $emailerData);

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Deny Access User
	public function denyAccessUser($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::find($userId);

			if ($user && !$user->hasRole('admin') && !$user->can_access) {
				$emailerData = Helper::getEmailerData();
				Helper::triggerUserEmail($user, 'Deny Access', $emailerData);

				Profile::where('user_id', $user->id)->delete();
				$user->delete();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Ban User
	public function banUser($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::find($userId);

			if ($user && !$user->hasRole('admin')) {
				$user->banned = 1;
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Unban User
	public function unbanUser($userId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$user = User::find($userId);

			if ($user && !$user->hasRole('admin')) {
				$user->banned = 0;
				$user->save();

				return ['success' => true];
			}
		}

		return ['success' => false];
	}

	// Reputation By User
	public function getReputationByUser($userId, Request $request)
	{
		$user = Auth::user();
		$items = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'reputation.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$total_staked = 0;
		// Records
		if ($user && $user->hasRole('admin')) {
			$items = Reputation::leftJoin('proposal', 'proposal.id', '=', 'reputation.proposal_id')
				->leftJoin('users', 'users.id', '=', 'proposal.user_id')
				->where('reputation.user_id', $userId)
				->select([
					'reputation.*',
					'proposal.include_membership',
					'proposal.title as proposal_title',
					'users.first_name as op_first_name',
					'users.last_name as op_last_name'
				])
				->orderBy($sort_key, $sort_direction)
				->get();
			$total_staked = DB::table('reputation')
				->where('user_id', $userId)
				->where('type', 'Staked')
				->sum('staked');
		}

		return [
			'success' => true,
			'items' => $items,
			'total_staked' => $total_staked
		];
	}

	// Proposals By User
	public function getProposalsByUser($userId, Request $request)
	{
		$user = Auth::user();
		$proposals = [];

		// Variables
		$sort_key = $sort_direction = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal.id';
		if (!$sort_direction) $sort_direction = 'desc';

		// Records
		if ($user && $user->hasRole('admin')) {
			$proposals = Proposal::with(['votes', 'onboarding'])
				->where('user_id', $userId)
				->orderBy($sort_key, $sort_direction)
				->get();
		}

		$proposals->each(function ($proposal, $key) {
			$proposal->makeHidden([ 'onboarding' ]);
		});

		return [
			'success' => true,
			'proposals' => $proposals
		];
	}

	// Votes By User
	public function getVotesByUser($userId, Request $request)
	{
		$user = Auth::user();
		$items = [];

		// Variables
		$sort_key = $sort_direction = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'vote_result.id';
		if (!$sort_direction) $sort_direction = 'desc';

		// Records
		if ($user && $user->hasRole('admin')) {
			$items = VoteResult::with(['proposal', 'vote'])
				->has('proposal')
				->has('vote')
				->where('user_id', $userId)
				->orderBy($sort_key, $sort_direction)
				->get();
		}

		return [
			'success' => true,
			'items' => $items
		];
	}

	// Single Proposal By Id
	public function getProposalById($proposalId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
			$proposal = Proposal::where('id', $proposalId)
				->with(['bank', 'crypto', 'grants', 'citations', 'milestones', 'members', 'files', 'surveyRanks.survey'])
				->with(['surveyRanks' => function ($q) {
					$q->orderBy('rank', 'desc');
				}])
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
				// rank survey
				$survey = Survey::where('status', 'completed')->orderBy('created_at', 'desc')->first();
				$survey_rank = $survey ? SurveyRank::where('survey_id', $survey->id)->where('proposal_id', $proposalId)->first() : null;
				$proposal->survey_number_response = $survey ? $survey->number_response : null;
				$proposal->survey_winner = ($survey && $survey->proposal_win == $proposalId) ? true : false;
				$proposal->survey_rank = $survey_rank ? $survey_rank->rank : null;

				return [
					'success' => true,
					'proposal' => $proposal
				];
			}
		}

		return ['success' => false];
	}

	// Pending Actions DataTable
	public function getPendingActions(Request $request)
	{
		$user = Auth::user();
		$actions = [];
		$total = 0;

		if ($user && $user->hasRole('admin')) {
			$page_id = (int) $request->get('page_id');
			$page_length = (int) $request->get('page_length');
			$sort_key = $request->get('sort_key');
			$sort_direction = $request->get('sort_direction');

			if ($page_id < 1) $page_id = 1;
			$start = ($page_id - 1) * $page_length;

			$total = PendingAction::join('users', 'pending_actions.user_id', '=', 'users.id')
				->join('profile', 'users.id', '=', 'profile.user_id')
				->get()
				->count();

			$actions = PendingAction::join('users', 'pending_actions.user_id', '=', 'users.id')
				->join('profile', 'users.id', '=', 'profile.user_id')
				->select([
					'pending_actions.*',
					'users.email',
					'users.first_name',
					'users.last_name',
					'users.status as user_status',
					'profile.dob',
					'profile.address',
					'profile.city',
					'profile.zip',
					'profile.country_citizenship',
					'profile.country_residence',
					'profile.company',
					'profile.step_review',
					'profile.step_kyc'
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($page_length)
				->get()
				->toArray();
		}

		return [
			'success' => true,
			'actions' => $actions,
			'total' => $total
		];
	}

	// Single User
	public function getSingleUser($userId, Request $request)
	{
		$user = Auth::user();
		if ($user && $user->hasRole('admin')) {
			$user = User::with(['profile', 'shuftipro', 'shuftiproTemp'])
				->where('id', $userId)->first();

			if ($user && $user->profile) {
				$total_informal_votes = null;
				$total_voted = null;
				if ($user->is_member == 1) {
					$total_informal_votes = Vote::where('type', 'informal')->where('created_at', '>=', $user->member_at)
						->whereHas('proposal', function ($query) use ($userId) {
							$query->where('proposal.user_id', '!=', $userId);
						})->count();
					$total_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
						->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')->where('vote.created_at', '>=', $user->member_at)->count();
				}
				$user->total_informal_votes = $total_informal_votes;
				$user->total_voted = $total_voted;

				$user->makeVisible([
					"profile",
					"shuftipro",
					"shuftiproTemp",
					"last_login_ip_address",
				]);
				if ($user->shuftipro ?? false) {
					$user->shuftipro->makeVisible(['reference_id']);
				}
				if ($user->profile ?? false) {
					$user->profile->makeVisible([ 'rep', 'rep_pending','company',
					'dob',
					'country_citizenship',
					'country_residence',
					'address',
					'city',
					'zip']);
				}

				return [
					'success' => true,
					'user' => $user,
				];
			}
		}

		return ['success' => false];
	}

	// Pre Register Users
	public function getPreRegisterUsers(Request $request)
	{
		$user = Auth::user();
		$users = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'pre_register.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole('admin')) {
			$users = PreRegister::where(function ($query) use ($search) {
				if ($search) {
					$query->where('first_name', 'like', '%' . $search . '%')
						->orWhere('last_name', 'like', '%' . $search . '%')
						->orWhere('email', 'like', '%' . $search . '%');
				}
			})
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}

		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	// Pending Users DataTable
	public function getPendingUsers(Request $request)
	{
		$user = Auth::user();
		$users = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'users.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole('admin')) {
			$users = User::join('profile', 'users.id', '=', 'profile.user_id')
				->where('users.is_admin', 0)
				->where('users.is_guest', 0)
				->where('users.is_super_admin', 0)
				->where('can_access', 0)
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('users.email', 'like', '%' . $search . '%')
							->orWhere('users.first_name', 'like', '%' . $search . '%')
							->orWhere('users.last_name', 'like', '%' . $search . '%')
							->orWhere('profile.telegram', 'like', '%' . $search . '%');
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
					'profile.telegram',
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}

		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	// Users DataTable
	public function getUsers(Request $request)
	{
		$user = Auth::user();
		$users = [];

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$is_va = $request->is_va;
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'users.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = 30;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole('admin')) {
			$users = User::join('profile', 'users.id', '=', 'profile.user_id')
				->where('users.is_admin', 0)
				->where('users.is_guest', 0)
				->where('can_access', 1)
				->where(function ($query) use ($search) {
					if ($search) {
						$query->where('users.email', 'like', '%' . $search . '%')
							->orWhere('users.id', 'like', '%' . $search . '%')
							->orWhere('users.first_name', 'like', '%' . $search . '%')
							->orWhere('users.last_name', 'like', '%' . $search . '%')
							->orWhere('profile.forum_name', 'like', '%' . $search . '%');
					}
				})
				->where(function ($query) use ($is_va) {
					if ($is_va == 1) {
						$query->where('users.is_member', 1);
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
				])
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
		}
		foreach ($users as $user) {
			$total_informal_votes = null;
			$total_voted = null;
			if ($user->is_member == 1) {
				$member_at = Carbon::parse($user->member_at)->format('Y-m-d');
				if (!$start_date && !$end_date) {
					$total_informal_votes = Vote::where('type', 'informal')->where('created_at', '>=', $member_at)
						->whereHas('proposal', function ($query) use ($user) {
							$query->where('proposal.user_id', '!=', $user->id);
						})->count();
					$total_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
						->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')->where('vote.created_at', '>=', $member_at)->count();
				} else {
					$start_date = $member_at >= $request->start_date ?  $member_at : $request->start_date;
					$total_informal_votes = Vote::where('type', 'informal')
						->whereHas('proposal', function ($query) use ($user) {
							$query->where('proposal.user_id', '!=', $user->id);
						})
						->where(function ($query) use ($start_date, $end_date) {
							if ($start_date) {
								$query->whereDate('created_at', '>=', $start_date);
							}
							if ($end_date) {
								$query->whereDate('created_at', '<=', $end_date);
							}
						})->count();
					$total_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
						->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')
						->where(function ($query) use ($start_date, $end_date) {
							if ($start_date) {
								$query->whereDate('vote.created_at', '>=', $start_date);
							}
							if ($end_date) {
								$query->whereDate('vote.created_at', '<=', $end_date);
							}
						})->count();
				}
			}
			$user->total_informal_votes = $total_informal_votes;
			$user->total_voted = $total_voted;
			$total_staked = DB::table('reputation')
				->where('user_id', $user->id)
				->where('type', 'Staked')
				->sum('staked');
			$user->total_staked = $total_staked;
			$user->total_rep = abs($total_staked) + $user->rep;
		}

		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	// Force Approve Proposal Change
	public function forceApproveProposalChange($proposalChangeId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
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
					'message' => "You can't force approve this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't force approve this proposed change"
				];
			}

			// Record Proposal History
			$proposalId = (int) $proposal->id;
			$history = ProposalHistory::where('proposal_id', $proposalId)
				->where('proposal_change_id', $proposalChangeId)
				->first();

			if (!$history) $history = new ProposalHistory;

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

	// Force Deny Proposal Change
	public function forceDenyProposalChange($proposalChangeId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
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
					'message' => "You can't force deny this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't force deny this proposed change"
				];
			}

			$proposalChange->status = 'denied';
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// Force Withdraw Proposal Change
	public function forceWithdrawProposalChange($proposalChangeId, Request $request)
	{
		$user = Auth::user();

		if ($user && $user->hasRole('admin')) {
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
					'message' => "You can't force withdraw this proposed change"
				];
			}

			// Proposal Change Status is not pending
			if ($proposalChange->status != 'pending') {
				return [
					'success' => false,
					'message' => "You can't force withdraw this proposed change"
				];
			}

			$proposalChange->status = "withdrawn";
			$proposalChange->save();

			return ['success' => true];
		}

		return ['success' => false];
	}

	// resned Send grant Hellosign Request
	public static function resendHellosignGrant($grantId)
	{
		$admin = Auth::user();
		if ($admin) {
			$finalGrant = FinalGrant::with(['proposal', 'user'])
				->has('proposal')
				->has('user')
				->where('id', $grantId)
				->first();
			if (!$finalGrant || $finalGrant->status != "pending") {
				return [
					'success' => false,
					'message' => 'Invalid grant'
				];
			}

			$user = $finalGrant->user;
			$proposal = $finalGrant->proposal;
			$settings = Helper::getSettings();
			$signature_grant_request_id = $finalGrant->proposal->signature_grant_request_id;

			Helper::createGrantLogging([
				'proposal_id' => $finalGrant->proposal_id,
				'final_grant_id' => $finalGrant->id,
				'user_id' => $admin->id,
				'email' => $admin->email,
				'role' => 'admin',
				'type' => 'resent',
			]);

			if ($signature_grant_request_id) {
				try {
					$client = new \HelloSign\Client(config('services.hellosign.api_key'));
					$client->cancelSignatureRequest($signature_grant_request_id);

					// Log when request to Hellosign
					$signatures = SignatureGrant::where('proposal_id',  $finalGrant->proposal_id)->get();
					Helper::createHellosignLogging(
						$admin->id,
						'Cancel Signature Request',
						'cancel_signature_request',
						json_encode([
							'Signatures' => $signatures->only(['email', 'role', 'signed']),
						])
					);
				} catch (Exception $e) {
					Log::info($e->getMessage());
				}

			}

			SignatureGrant::where('proposal_id', $finalGrant->proposal_id)
				->update(['signed' => 0]);

			Helper::createGrantLogging([
				'proposal_id' => $finalGrant->proposal_id,
				'final_grant_id' => $finalGrant->id,
				'user_id' => null,
				'email' => null,
				'role' => 'system',
				'type' => 'cancelled_doc',
			]);

			Helper::sendGrantHellosign($user, $proposal, $settings);

			return ['success' => true];
		}
		return ['success' => false];
	}

	// remind Send grant Hellosign Request
	public static function remindHellosignGrant($grantId)
	{
		try {
			$admin = Auth::user();
			if ($admin) {
				$finalGrant = FinalGrant::with(['proposal', 'user'])
					->has('proposal')
					->has('user')
					->where('id', $grantId)
					->first();
				if (!$finalGrant || $finalGrant->status != "pending") {
					return [
						'success' => false,
						'message' => 'Invalid grant'
					];
				}
				$signature_grant_request_id = $finalGrant->proposal->signature_grant_request_id;
				if (!$signature_grant_request_id) {
					return [
						'success' => false,
						'message' => 'Invalid grant'
					];
				}
				$signatureGrants = SignatureGrant::where('proposal_id', $finalGrant->proposal_id)->where('signed', 0)->get();
				Helper::createGrantLogging([
					'proposal_id' => $finalGrant->proposal_id,
					'final_grant_id' => $finalGrant->id,
					'user_id' => $admin->id,
					'email' => $admin->email,
					'role' => 'admin',
					'type' => 'reminded',
				]);

				foreach ($signatureGrants as $value) {
					$client = new \HelloSign\Client(config('services.hellosign.api_key'));
					$client->requestEmailReminder($signature_grant_request_id, $value->email, $value->name);

					// Log when request to Hellosign
					$signatures = SignatureGrant::where('proposal_id',  $finalGrant->proposal_id)->get();
					Helper::createHellosignLogging(
						$admin->id,
						'Request Email Reminder',
						'request_email_reminder',
						json_encode([
							'Signatures' => $signatures->only(['email', 'role', 'signed']),
						])
					);
				}
				Helper::createGrantLogging([
					'proposal_id' => $finalGrant->proposal_id,
					'final_grant_id' => $finalGrant->id,
					'user_id' => null,
					'email' => null,
					'role' => 'system',
					'type' => 'reminded_doc',
				]);

				return ['success' => true];
			}
			return ['success' => false];
		} catch (\Exception $ex) {
			Log::info($ex->getMessage());
			return [
				'success' => false,
				'message' => $ex->getMessage()
			];
		}
	}

	public function updateKYCinfo(Request $request, $userId)
	{
		$admin = Auth::user();
		if ($admin && $admin->hasRole('admin')) {
			$profile = Profile::where('user_id', $userId)->first();
			if ($profile) {
				if ($request->address) {
					$profile->address = $request->address;
				}
				if ($request->city) {
					$profile->city = $request->city;
				}
				if ($request->zip) {
					$profile->zip = $request->zip;
				}
				$profile->save();
				return ['success' => true];
			}
			return ['success' => false];
		}
		return ['success' => false];
	}

	public function getUrlFileHellosignGrant($grantId)
	{
		$admin = Auth::user();
		if ($admin) {
			$finalGrant = FinalGrant::where('id', $grantId)->first();
			if (!$finalGrant) {
				return ['success' => false];
			}

			$proposal = Proposal::where('id', $finalGrant->proposal_id)->first();

			if (!$proposal || !$proposal->signature_grant_request_id) {
				return ['success' => false];
			}
			$signature_grant_request_id = $proposal->signature_grant_request_id;
			$client = new \HelloSign\Client(config('services.hellosign.api_key'));
			$respone = $client->getFiles($signature_grant_request_id, null, \HelloSign\SignatureRequest::FILE_TYPE_PDF);
			$respone = $respone->toArray();
			return [
				'success' => true,
				'file_url' => $respone['file_url'] ?? '',
			];
		}
		return ['success' => false];
	}
	public function getListUserNotVote($id, Request $request)
	{
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		$vote = Vote::where('id', $id)->first();
		if ($vote) {
			$type = $vote->type;
			if ($type == 'informal') {
				return $this->getUserNotVoteInformal($vote, $start, $limit);
			} else {
				return $this->getUserNotVoteFormal($vote, $start, $limit);
			}
		} else {
			return [
				'success' => false,
				'message' => 'Vote not exist'
			];
		}
	}

	private function getUserNotVoteInformal($vote, $start, $limit)
	{
		$users = User::where('is_member', 1)
			->where('banned', 0)
			->where('can_access', 1)
			->whereNotIn('id', function ($query) use ($vote) {
				$query->select('user_id')
					->from(with(new VoteResult())->getTable())
					->where('vote_id', $vote->id);
			})->orderBy('id', 'asc')
			->with('profile')
			->offset($start)
			->limit($limit)
			->get();
		$users->each(function ($user, $key) {
			$user->makeVisible([
				"profile",
			]);
		});
		return [
			'success' => true,
			'data' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	private function getUserNotVoteFormal($vote, $start, $limit)
	{
		$informal = Vote::where('content_type', $vote->content_type)
			->where('type', 'informal')
			->where('proposal_id', $vote->proposal_id)->first();
		$users = User::where('is_member', 1)
			->where('banned', 0)
			->where('can_access', 1)
			->whereIn('id', function ($query) use ($informal) {
				$query->select('user_id')
					->from(with(new VoteResult())->getTable())
					->where('vote_id', $informal->id);
			})
			->whereNotIn('id', function ($query) use ($vote) {
				$query->select('user_id')
					->from(with(new VoteResult())->getTable())
					->where('vote_id', $vote->id);
			})->orderBy('id', 'asc')
			->with('profile')
			->offset($start)
			->limit($limit)
			->get();
		$users->each(function ($user, $key) {
			$user->makeVisible([
				"profile",
			]);
		});
		return [
			'success' => true,
			'data' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	public function getMetrics()
	{
		$totalGrant = FinalGrant::join('proposal', 'final_grant.proposal_id', '=', 'proposal.id')
			->where('final_grant.status', '!=', 'pending')
			->sum('proposal.total_grant');
		$data['totalGrant'] = $totalGrant;
		return [
			'success' => true,
			'data' => $data
		];
	}

	public function getListMilestoneReview(Request $request)
	{
		$user = Auth::user();

		// Variables
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'milestone_review.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		$milestoneReviews = MilestoneReview::with(['milestones'])
			->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
			->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
			->join('users', 'proposal.user_id', '=', 'users.id')
			->whereIn('milestone_review.status', ['pending', 'active'])
			->select([
				'milestone_review.milestone_id',
				'milestone_review.proposal_id',
				'milestone_review.status as milestone_review_status',
				'milestone.*',
				'proposal.title as proposal_title',
				'users.id as user_id',
				'users.email'
			])
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();

		return [
			'success' => true,
			'milestoneReviews' => $milestoneReviews,
			'finished' => count($milestoneReviews) < $limit ? true : false
		];
	}

	public function getMilestoneDetailReview($milestoneId)
	{
		$milestoneReview = MilestoneReview::with(['milestones'])
			->join('milestone', 'milestone.id', '=', 'milestone_review.milestone_id')
			->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
			->join('users', 'proposal.user_id', '=', 'users.id')
			->select([
				'milestone_review.milestone_id',
				'milestone_review.proposal_id',
				'milestone_review.status as milestone_review_status',
				'milestone.*',
				'proposal.title as proposal_title',
				'proposal.status as proposal_status',
				'users.id as user_id',
				'users.email'
			])->where('milestone_review.milestone_id', $milestoneId)->first();
		if ($milestoneReview) {
			return [
				'success' => true,
				'milestoneReview' => $milestoneReview
			];
		}
		return [
			'success' => false,
			'message' => 'This milestone can not review'
		];
	}
	public function approveMilestone(Request $request, $milestoneId)
	{
		$validator = Validator::make($request->all(), [
			'notes' => 'required',
			'file' => 'nullable|file',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Provide all the necessary information'
			];
		}
		$user = Auth::user();
		if ($user->hasRole('admin')) {
			$milestoneReview = MilestoneReview::where('milestone_id', $milestoneId)->where('status', 'pending')->first();
			if (!$milestoneReview) {
				return [
					'success' => false,
					'message' => 'milestone not exist'
				];
			}
			$proposalId = $milestoneReview->proposal_id;
			$finalGrant = FinalGrant::where('proposal_id', $proposalId)->first();
			$milestone = Milestone::find($milestoneId);
			$milestone->notes = $request->notes;
			$file = $request->file('file');
			if ($file) {
				$path = $file->store('milestone');
				$url = Storage::url($path);
				$milestone->support_file = $url;
			}
			$milestone->save();

			$milestoneReview->status = 'approved';
			$milestoneReview->reviewer = $user->id;
			$milestoneReview->reviewed_at = now();
			$milestoneReview->save();
			Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'Admin', 'Admin approved the work.');
			$vote = Vote::where('proposal_id', $proposalId)
				->where(
					'type',
					'informal'
				)
				->where('content_type', 'milestone')
				->where('milestone_id', $milestoneId)
				->first();

			if (!$vote) {
				$milestonePosition = Helper::getPositionMilestone($milestone);
				Helper::createMilestoneLog($milestoneId, null, null, 'System', 'Vote started');
				Helper::createGrantTracking($milestone->proposal_id, "Milestone $milestonePosition started informal vote", 'milestone_' . $milestonePosition. '_started_informal_vote');
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
		return ['success' => false];
	}

	public function denyMilestone(Request $request, $milestoneId)
	{
		$user = Auth::user();
		$validator = Validator::make($request->all(), [
			'message' => 'required',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Provide all the necessary information'
			];
		}
		if ($user->hasRole('admin')) {
			$milestoneReview = MilestoneReview::where('milestone_id', $milestoneId)->where('status', 'pending')->first();

			if (!$milestoneReview) {
				return [
					'success' => false,
					'message' => 'milestone not exist'
				];
			}
			$message = $request->message;
			$milestoneReview->delete();
			Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'Admin', 'Admin denied the work.');
			$emailerData = Helper::getEmailerData();
			$milestone = Milestone::where('id', $milestoneId)->first();
			$proposal = Proposal::where('id', $milestoneReview->proposal_id)->first();
			$user = User::where('id', $proposal->user_id)->first();
			Helper::triggerUserEmail($user, 'Milestone Deny', $emailerData, $proposal, null, $user, [], $milestone, $message);

			return ['success' => true];
		}
		return ['success' => false];
	}

	public function getProposalHasMilestone()
	{
		$proposalIds = Milestone::distinct('proposal_id')->pluck('proposal_id');
		return [
			'success' => true,
			'proposalIds' => $proposalIds
		];
	}

	public function getOPHasMilestone()
	{
		$emails = Milestone::join('proposal', 'proposal.id', '=', 'milestone.proposal_id')
			->join('users', 'users.id', '=', 'proposal.user_id')
			->distinct('users.email')
			->pluck('users.email');
		return [
			'success' => true,
			'emails' => $emails
		];
	}

	public function getAllMilestone(Request $request)
	{
		// Variables
		$email = $request->email;
		$proposalId = $request->proposalId;
		$hidePaid = $request->hidePaid;
		$startDate = $request->startDate;
		$endDate = $request->endDate;
		$hideCompletedGrants  = $request->hideCompletedGrants;
		$search = $request->search;
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'milestone.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);
		$totalPaid = Helper::queryGetMilestone($email, $proposalId, $hideCompletedGrants, $startDate, $endDate, $search)->where('milestone.paid', '=', 1)->sum('milestone.grant');
		$query = Helper::queryGetMilestone($email, $proposalId, $hideCompletedGrants, $startDate, $endDate, $search);
		if (Str::contains($request->path(), 'public')) {
			$query->has('votes');
		};
		if ($hidePaid == 1) {
			$query->where('milestone.paid', '=', 0);
			$totalPaid = 0;
		}
		$totalGrant = $query->sum('milestone.grant');
		$milestones = $query->select([
			'milestone.*',
			'proposal.title as proposal_title',
			'users.id as user_id',
			'users.email',
			DB::raw("(CASE WHEN milestone.submitted_time IS NULL THEN 'Not Submitted'
				ELSE milestone_review.status END) AS milestone_review_status")
		])
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();
		return [
			'success' => true,
			'milestones' => $milestones,
			'totalGrant' => $totalGrant,
			'totalPaid' => $totalPaid,
			'totalUnpaid' => $totalGrant - $totalPaid,
			'finished' => count($milestones) < $limit ? true : false
		];
	}

	public function updatePaidMilestone(Request $request, $milestoneId)
	{
		$paid = isset($request->paid) ? $request->paid : null;
		$user = Auth::user();
		if ($user->hasRole('admin')) {
			$milestone = Milestone::where('id', $milestoneId)->first();
			if (!$milestone || !isset($paid)) {
				return [
					'success' => false,
					'message' => 'Milestone not exist',
				];
			}
			if ($milestone->paid == $paid) {
				return [
					'success' => true
				];
			}
			if ($paid == 1) {
				Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'Admin', 'Admin marked milestone as paid.');
				$milestone->paid_time = now();
			} else {
				Helper::createMilestoneLog($milestoneId, $user->email, $user->id, 'Admin', 'Admin marked milestone as unpaid');
				$milestone->paid_time = null;
			}
			$milestone->paid = $paid;
			$milestone->save();
			return [
				'success' => true
			];
		}
		return [
			'success' => false
		];
	}

	public function getMilestoneDetail($milestoneId)
	{
		$milestone = Milestone::with(['milestones', 'milestoneCheckList'])
			->join('proposal', 'milestone.proposal_id', '=', 'proposal.id')
			->join('users', 'proposal.user_id', '=', 'users.id')
			->leftJoin('milestone_review', 'milestone.id', '=', 'milestone_review.milestone_id')
			->leftJoin('ops_users as u1', 'u1.id', '=', 'milestone_review.assigner_id')
			->select([
				'milestone.*',
				'proposal.title as proposal_title',
				'proposal.status as proposal_status',
				'users.id as user_id',
				'users.email',
				'milestone_review.reviewed_at',
				'milestone_review.assigner_id',
				'milestone_review.assigned_at',
				'milestone_review.status as milestone_review_status',
				'u1.email as admin_reviewer_email',
			])->where('milestone.id', $milestoneId)->first();
		if ($milestone) {
			$milestone->support_file_url = $milestone->support_file ? asset($milestone->support_file) : null;
			return [
				'success' => true,
				'milestone' => $milestone
			];
		}
		return [
			'success' => false,
			'message' => 'This milestone does not exist'
		];
	}

	public function getListMilestoneLog(Request $request, $milestoneId)
	{
		$user = Auth::user();

		// Variables
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'milestone_log.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		$milestoneLogs = MilestoneLog::where('milestone_log.milestone_id', $milestoneId)
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();

		return [
			'success' => true,
			'milestoneLogs' => $milestoneLogs,
		];
	}
	public function exportMilestone(Request $request)
	{
		// Variables
		$email = $request->email;
		$proposalId = $request->proposalId;
		$hidePaid = $request->hidePaid;
		$startDate = $request->startDate;
		$endDate = $request->endDate;
		$hideCompletedGrants  = $request->hideCompletedGrants;
		$search = $request->search;
		$query = Helper::queryGetMilestone($email, $proposalId, $hideCompletedGrants, $startDate, $endDate, $search);
		if ($hidePaid == 1) {
			$query->where('milestone.paid', '=', 0);
		}
		$milestones = $query->select([
			'milestone.*',
			'proposal.title as proposal_title',
			'users.id as user_id',
			'users.email',
			DB::raw("(CASE WHEN milestone.submitted_time IS NULL THEN 'Not Submitted'
				ELSE milestone_review.status END) AS milestone_review_status")
		])
			->orderBy('milestone.id', 'desc')
			->get();
		return Excel::download(new MilestoneExport($milestones), 'milestone.csv');
	}

	public function getListAdmin(Request $request)
	{
		// Users DataTable
		$user = Auth::user();
		$users = [];

		// Variables
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'users.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = $request->limit ?? 15;
		$start = $limit * ($page_id - 1);

		// Records
		if ($user && $user->hasRole('admin')) {
			$users = User::with(['profile', 'permissions'])
				->where('users.is_admin', 1)
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();
			return [
				'success' => true,
				'users' => $users,
				'finished' => count($users) < $limit ? true : false
			];
		} else {
			return [
				'success' => false,
				'users' => $users,
			];
		}
	}

	public function inviteAdmin(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'email' => 'required|email',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Invalid format Email'
			];
		}

		$isExist = User::where(['email' => $request->email])->count() > 0;
		if ($isExist) {
			return [
				'success' => false,
				'message' => 'This email has already been exist'
			];
		}
		$code = Str::random(6);
		$url = $request->header('origin') ?? $request->root();
		$inviteUrl = $url . '/register-admin?code=' . $code . '&email=' . urlencode($request->email);

		$user = new User;
		$user->first_name = 'Faker';
		$user->last_name = 'Faker';
		$user->email =  $request->email;
		$user->password = '';
		$user->confirmation_code = $code;
		$user->email_verified = 0;
		$user->is_admin = 1;
		$user->admin_status = 'invited';
		$user->save();

		if (!$user->hasRole('admin'))
			$user->assignRole('admin');

		$profile = Profile::where('user_id', $user->id)->first();
		if (!$profile) {
			$profile = new Profile;
			$profile->user_id = $user->id;
			$profile->company = '';
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

		$data = [
			['name' => 'users', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'new_proposal', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'move_to_formal', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'grants', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'milestones', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'global_settings', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'emailer', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
			['name' => 'accounting', 'guard_name' => 'api', 'is_permission' => 0, 'user_id' => $user->id],
		];

		Permission::insert($data);
		Mail::to($request->email)->send(new InviteAdminMail($inviteUrl));

		return [
			'success' => true,
		];
	}

	public function resendLink(Request $request, $id)
	{
		$user = User::find($id);
		if ($user && $user->is_admin == 1 & $user->is_super_admin != 1) {
			$code = Str::random(6);
			$url = $request->header('origin') ?? $request->root();
			$inviteUrl = $url . '/register-admin?code=' . $code . '&email=' . urlencode($user->email);
			$user->confirmation_code = $code;
			$user->save();
			Mail::to($user->email)->send(new InviteAdminMail($inviteUrl));
			return [
				'success' => true
			];
		} else {
			return [
				'success' => false,
				'message' => 'No admin to be send invite link'
			];
		}
	}

	public function revokeAdmin(Request $request, $id)
	{
		$user = User::find($id);
		if ($user && $user->is_admin == 1 & $user->is_super_admin != 1) {
			$user->banned = 1;
			$user->admin_status = 'revoked';
			$user->save();
			return [
				'success' => true
			];
		} else {
			return [
				'success' => false,
				'message' => 'No admin to revoke'
			];
		}
	}

	public function undoRevokeAdmin(Request $request, $id)
	{
		$user = User::find($id);
		if ($user && $user->is_admin == 1 & $user->is_super_admin != 1) {
			$user->banned = 0;
			if ($user->password) {
				$user->admin_status = 'active';
			} else {
				$user->admin_status = 'invited';
			}
			$user->save();
			return [
				'success' => true
			];
		} else {
			return [
				'success' => false,
				'message' => 'No admin to un-revoke'
			];
		}
	}

	public function getIpHistories(Request $request, $id)
	{
		$user = User::find($id);
		if (!$user) {
			return [
				'success' => false,
				'message' => 'Not found user'
			];
		}
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

		// Records
		$ips = IpHistory::where('user_id', $user->id)
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();
		return [
			'success' => true,
			'ip-histories' => $ips,
			'finished' => count($ips) < $limit ? true : false
		];
	}

	public function addminResetPassword(Request $request, $id)
	{
		$user = User::find($id);
		if ($user && $user->is_admin == 1 & $user->is_super_admin != 1) {
			// Clear Tokens
			DB::table('password_resets')
				->where('email', $user->email)
				->delete();

			// Generate New One
			$token = Str::random(60);
			DB::table('password_resets')->insert([
				'email' =>  $user->email,
				'token' => Hash::make($token),
				'created_at' => Carbon::now()
			]);

			$resetUrl = $request->header('origin') . '/password/reset/' . $token . '?email=' . urlencode($user->email);

			Mail::to($user)->send(new ResetPasswordLink($resetUrl));

			return ['success' => true];
		} else {
			return [
				'success' => false,
				'message' => 'No admin to reset password'
			];
		}
	}

	public function changeAdminPermissions(Request $request, $id)
	{
		$validator = Validator::make($request->all(), [
			'users' => 'nullable|in:0,1',
			'new_proposal' => 'nullable|in:0,1',
			'move_to_formal' => 'nullable|in:0,1',
			'grants' => 'nullable|in:0,1',
			'milestones' => 'nullable|in:0,1',
			'global_settings' => 'nullable|in:0,1',
			'emailer' => 'nullable|in:0,1',
			'accounting' => 'nullable|in:0,1',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Value only contain 0 or 1'
			];
		}
		$user = User::find($id);
		if (!$user) {
			return [
				'success' => false,
				'message' => 'No admin to reset password'
			];
		}
		if (isset($request->users)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'users')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->users;
				$permisstion->save();
			}
		}

		if (isset($request->new_proposal)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'new_proposal')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->new_proposal;
				$permisstion->save();
			}
		}

		if (isset($request->move_to_formal)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'move_to_formal')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->move_to_formal;
				$permisstion->save();
			}
		}

		if (isset($request->grants)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'grants')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->grants;
				$permisstion->save();
			}
		}
		if (isset($request->milestones)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'milestones')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->milestones;
				$permisstion->save();
			}
		}
		if (isset($request->global_settings)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'global_settings')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->global_settings;
				$permisstion->save();
			}
		}
		if (isset($request->emailer)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'emailer')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->emailer;
				$permisstion->save();
			}
		}
		if (isset($request->accounting)) {
			$permisstion = Permission::where('user_id', $id)->where('name', 'accounting')->first();
			if ($permisstion) {
				$permisstion->is_permission = $request->accounting;
				$permisstion->save();
			}
		}

		return ['success' => true];
	}

	public function getDosFee(Request $request)
	{
		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal.approved_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = $request->limit ?? 15;
		$start = $limit * ($page_id - 1);

		// Records
		$proposals = Proposal::join('users', 'users.id', '=', 'proposal.user_id')
			->where('proposal.dos_paid', 1)
			->where(function ($query) {
				$query->where('proposal.dos_eth_amount', '>', 0)
					->orWhere('proposal.dos_cc_amount', '>', 0);
			})
			->where(function ($query) use ($search, $start_date, $end_date) {
				if ($search) {
					$query->where('users.email', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%')
						->orWhere('proposal.dos_txid', 'like', '%' . $search . '%')
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN 'eth' WHEN proposal.dos_cc_amount > 0 THEN 'cc' ELSE '' END) like '%$search%' ")
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN proposal.dos_eth_amount WHEN proposal.dos_cc_amount > 0 THEN proposal.dos_cc_amount ELSE 0 END) like '%$search%' ");
				}
				if ($start_date) {
					$query->whereDate('proposal.approved_at', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('proposal.approved_at', '<=', $end_date);
				}
			})
			->select([
				'users.email',
				'proposal.*',
				DB::raw("(CASE WHEN proposal.dos_eth_amount > 0 THEN 'eth' WHEN proposal.dos_cc_amount > 0 THEN 'cc' ELSE '' END) as type_dos"),
				DB::raw("(CASE WHEN proposal.dos_eth_amount > 0 THEN proposal.dos_eth_amount WHEN proposal.dos_cc_amount > 0 THEN proposal.dos_cc_amount ELSE 0 END) as amount_dos")
			])
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();

		$totalETH = Proposal::join('users', 'users.id', '=', 'proposal.user_id')
			->where('proposal.dos_paid', 1)
			->where('proposal.dos_eth_amount', '>', 0)
			->where(function ($query) use ($search, $start_date, $end_date) {
				if ($search) {
					$query->where('users.email', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%')
						->orWhere('proposal.dos_txid', 'like', '%' . $search . '%')
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN 'eth' WHEN proposal.dos_cc_amount > 0 THEN 'cc' ELSE '' END) like '%$search%' ")
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN proposal.dos_eth_amount WHEN proposal.dos_cc_amount > 0 THEN proposal.dos_cc_amount ELSE 0 END) like '%$search%' ");
				}
				if ($start_date) {
					$query->whereDate('proposal.approved_at', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('proposal.approved_at', '<=', $end_date);
				}
			})->sum('proposal.dos_amount');

		$totalCC = Proposal::join('users', 'users.id', '=', 'proposal.user_id')
			->where('proposal.dos_paid', 1)
			->where('proposal.dos_cc_amount', '>', 0)
			->where(function ($query) use ($search, $start_date, $end_date) {
				if ($search) {
					$query->where('users.email', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%')
						->orWhere('proposal.dos_txid', 'like', '%' . $search . '%')
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN 'eth' WHEN proposal.dos_cc_amount > 0 THEN 'cc' ELSE '' END) like '%$search%' ")
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN proposal.dos_eth_amount WHEN proposal.dos_cc_amount > 0 THEN proposal.dos_cc_amount ELSE 0 END) like '%$search%' ");
				}
				if ($start_date) {
					$query->whereDate('proposal.approved_at', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('proposal.approved_at', '<=', $end_date);
				}
			})->sum('proposal.dos_amount');

		$proposals->each(function($proposal) {
			if ($proposal->dos_txid == config('services.crypto.eth.secret_code')) {
				$proposal->bypass = true;
			} else {
				$proposal->bypass = false;
			}
		});

		return [
			'success' => true,
			'totalETH' => $totalETH,
			'totalCC' => $totalCC,
			'proposals' => $proposals,
			'finished' => count($proposals) < $limit ? true : false
		];
	}

	public function exportCSVDosFee(Request $request)
	{
		// Variables
		$sort_key = $sort_direction = $search = '';
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal.approved_at';
		if (!$sort_direction) $sort_direction = 'desc';

		// Records
		$proposals = Proposal::join('users', 'users.id', '=', 'proposal.user_id')
			->where('proposal.dos_paid', 1)
			->where(function ($query) {
				$query->where('proposal.dos_eth_amount', '>', 0)
					->orWhere('proposal.dos_cc_amount', '>', 0);
			})
			->where(function ($query) use ($search, $start_date, $end_date) {
				if ($search) {
					$query->where('users.email', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%')
						->orWhere('proposal.dos_txid', 'like', '%' . $search . '%')
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN 'eth' WHEN proposal.dos_cc_amount > 0 THEN 'cc' ELSE '' END) like '%$search%' ")
						->orWhereRaw("(CASE WHEN proposal.dos_eth_amount > 0 THEN proposal.dos_eth_amount WHEN proposal.dos_cc_amount > 0 THEN proposal.dos_cc_amount ELSE 0 END) like '%$search%' ");
				}
				if ($start_date) {
					$query->whereDate('proposal.approved_at', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('proposal.approved_at', '<=', $end_date);
				}
			})
			->select([
				'users.email',
				'proposal.*',
				DB::raw("(CASE WHEN proposal.dos_eth_amount > 0 THEN 'eth' WHEN proposal.dos_cc_amount > 0 THEN 'cc' ELSE '' END) as type_dos"),
				DB::raw("(CASE WHEN proposal.dos_eth_amount > 0 THEN proposal.dos_eth_amount WHEN proposal.dos_cc_amount > 0 THEN proposal.dos_cc_amount ELSE 0 END) as amount_dos")
			])
			->orderBy($sort_key, $sort_direction)
			->get();
		return Excel::download(new DosFeeExport($proposals), 'dos_fee.csv');
	}

	public function exportCSVUser(Request $request)
	{
		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$is_va = $request->is_va;
		$end_date = $request->end_date;
		$start_date = $request->start_date;

		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'users.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = 30;
		$start = $limit * ($page_id - 1);

		// Records
		$users = User::join('profile', 'users.id', '=', 'profile.user_id')
			->where('users.is_admin', 0)
			->where('users.is_guest', 0)
			->where('can_access', 1)
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('users.email', 'like', '%' . $search . '%')
						->orWhere('users.first_name', 'like', '%' . $search . '%')
						->orWhere('users.last_name', 'like', '%' . $search . '%')
						->orWhere('profile.forum_name', 'like', '%' . $search . '%');
				}
			})
			->where(function ($query) use ($is_va) {
				if ($is_va == 1) {
					$query->where('users.is_member', 1);
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
			])
			->orderBy($sort_key, $sort_direction)
			->get();
		foreach ($users as $user) {
			$total_informal_votes = null;
			$total_voted = null;
			if ($user->is_member == 1) {
				$member_at = Carbon::parse($user->member_at)->format('Y-m-d');
				if (!$start_date && !$end_date) {
					$total_informal_votes = Vote::where('type', 'informal')->where('created_at', '>=', $member_at)
						->whereHas('proposal', function ($query) use ($user) {
							$query->where('proposal.user_id', '!=', $user->id);
						})->count();
					$total_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
						->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')->where('vote.created_at', '>=', $member_at)->count();
				} else {
					$start_date =  $member_at >= $request->start_date ?  $member_at : $request->start_date;
					$total_informal_votes = Vote::where('type', 'informal')
						->whereHas('proposal', function ($query) use ($user) {
							$query->where('proposal.user_id', '!=', $user->id);
						})
						->where(function ($query) use ($start_date, $end_date) {
							if ($start_date) {
								$query->whereDate('created_at', '>=', $start_date);
							}
							if ($end_date) {
								$query->whereDate('created_at', '<=', $end_date);
							}
						})->count();
					$total_voted = VoteResult::join('vote', 'vote.id', '=', 'vote_result.vote_id')
						->where('vote_result.user_id', $user->id)->where('vote.type', 'informal')
						->where(function ($query) use ($start_date, $end_date) {
							if ($start_date) {
								$query->whereDate('vote.created_at', '>=', $start_date);
							}
							if ($end_date) {
								$query->whereDate('vote.created_at', '<=', $end_date);
							}
						})->count();
				}
			}
			$user->total_informal_votes = $total_informal_votes;
			$user->total_voted = $total_voted;
			$total_staked = DB::table('reputation')
				->where('user_id', $user->id)
				->where('type', 'Staked')
				->sum('staked');
			$user->total_staked = $total_staked;
			$user->total_rep = abs($total_staked) + $user->rep;
		}
		return Excel::download(new UserExport($users), 'user.csv');
	}

	public function submitSurvey(Request $request)
	{
		$user = Auth::user();
		if ($user->hasRole('admin')) {
			$survey = Survey::where('status', 'active')->where('type', 'grant')->first();
			if ($survey) {
				return [
					'success' => false,
					'message' => 'Another active survey is running'
				];
			}
			$validator = Validator::make($request->all(), [
				'number_response' => 'required|numeric|min:1|max:10',
				'time' => 'required|numeric|min:1|max:100',
				'time_unit' => 'required|in:minutes,hours,days',
				'downvote' => 'required|in:0,1',
			]);
			if ($validator->fails()) {
				return [
					'success' => false,
					'message' => 'Provide all the necessary information'
				];
			}

			$countDiscussions = Proposal::where('proposal.status', 'approved')
			->doesntHave('votes')
			->where(function ($query) {
				$survey_rank_ids = SurveyRank::where('is_winner', 1)->pluck('proposal_id');
				$survey_downvote_rank_ids = SurveyDownVoteRank::where('is_winner', 1)->pluck('proposal_id');
				$query->whereNotIn('proposal.id', $survey_rank_ids->toArray())
					->whereNotIn('proposal.id', $survey_downvote_rank_ids->toArray());
			})->count();
			$number_response = $request->downvote ? $request->number_response * 2 : $request->number_response;
			if($number_response > $countDiscussions) {
				$slot = $request->downvote ? floor($countDiscussions / 2) : $countDiscussions;
				return [
					'success' => false,
					'message' => "There are not enough proposals in the pipeline. Select a number of slots no more than $slot."
				];
			}
			$time = $request->time;
			$timeUnit = $request->time_unit;
			$mins = 0;
			if ($timeUnit == 'minutes') {
				$mins = $time;
			} else if ($timeUnit == 'hours') {
				$mins = $time * 60;
			} else if ($timeUnit == 'days') {
				$mins = $time * 60 * 24;
			}
			$timeEnd = Carbon::now('UTC')->addMinutes($mins);
			$survey = new Survey();
			$survey->number_response = $request->number_response;
			$survey->downvote = $request->downvote;
			$survey->time = $time;
			$survey->time_unit = $timeUnit;
			$survey->end_time = $timeEnd;
			$survey->status = 'active';
			$survey->type = 'grant';
			$survey->save();
			$emailerData = Helper::getEmailerData();
			Helper::triggerMemberEmail('New Survey', $emailerData);
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	public function cancelSurvey($id)
	{
		$user = Auth::user();
		if ($user->hasRole('admin')) {
			$survey = Survey::where('status', 'active')->where('id', $id)->first();
			if (!$survey) {
				return [
					'success' => false,
					'message' => 'Not found survey'
				];
			}
			$survey->status = 'cancel';
			$survey->save();
			return ['success' => true];
		} else {
			return ['success' => false];
		}
	}

	public function getSurvey(Request $request)
	{
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
		$total_member = Helper::getTotalMembers();
		// Records
		$surveys = Survey::where('status', $status)
			->where('type', 'grant')
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->get();
		foreach ($surveys as $survey) {
			$survey->total_member = $total_member;
		}
		return [
			'success' => true,
			'surveys' => $surveys,
			'finished' => count($surveys) < $limit ? true : false
		];
	}

	public function getDetailSurvey($id)
	{
		$survey = Survey::where('id', $id)->with(['surveyRanks' => function ($q) {
			$q->orderBy('rank', 'desc');
		}])->with(['surveyRanks.proposal'])
		->with(['surveyDownvoteRanks' => function ($q) {
			$q->orderBy('rank', 'desc');
		}])->with(['surveyDownvoteRanks.proposal'])
		->with(['surveyRfpRanks' => function ($q) {
			$q->orderBy('rank', 'desc');
		}])->with(['surveyRfpRanks.surveyRfpBid','surveyRfpBids'])
		->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		$survey->total_member = Helper::getTotalMembers();
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

	public function getDisscustionVote($id, Request $request)
	{
		$survey = Survey::where('id', $id)->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		$user = Auth::user();
		$proposals = [];

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

		// Record
		$proposals = Proposal::where('proposal.status', 'approved')
			->doesntHave('votes')
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%');
				}
			})
			->get();
		$results = SurveyResult::where('survey_id', $id)->get();
		foreach ($proposals as $proposal) {
			for ($i = 1; $i <= $survey->number_response; $i++) {
				$key = $i . '_place';
				$proposal->$key = null;
			}
			$total_vote = count($results->where("proposal_id", $proposal->id));
			$proposal->total_vote = $total_vote;
			if ($total_vote) {
				for ($i = 1; $i <= $survey->number_response; $i++) {
					$key = $i . '_place';
					$proposal->$key = count($results->where("proposal_id", $proposal->id)->where('place_choice', $i));
				}
			}
		}
		if ($sort_direction == 'asc') {
			$sorted = $proposals->sortBy($sort_key)->values();
		} else {
			$sorted = $proposals->sortByDesc($sort_key)->values();
		}
		$response = $sorted->slice($start, $limit)->values();
		return [
			'success' => true,
			'proposals' => $response,
			'finished' => count($response) < $limit ? true : false
		];
	}

	public function getVoteSurvey($id, Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$propopsl_id = $request->proposal_id;
		$place_choice = $request->place_choice;
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'survey_result.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = 30;
		$start = $limit * ($page_id - 1);

		// Records
		$users = SurveyResult::where('survey_result.survey_id', $id)
			->where('survey_result.proposal_id', $propopsl_id);
		if ($place_choice) {
			$users->where('survey_result.place_choice', $place_choice);
		}
		$users = $users->join('users', 'survey_result.user_id', '=', 'users.id')
			->orderBy($sort_key, $sort_direction)
			->offset($start)
			->limit($limit)
			->select([
				'users.email',
				'survey_result.*'
			])
			->get();
		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	public function getVoteSurveyByUser($id, $userId, Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'survey_result.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = 30;
		$start = $limit * ($page_id - 1);

		// Records
		$proposalsVote = SurveyResult::where('survey_result.survey_id', $id)
			->where('survey_result.user_id', $userId)
			->join('proposal', 'survey_result.proposal_id', '=', 'proposal.id')
			->orderBy($sort_key, $sort_direction)
			->select([
				'proposal.title',
				'survey_result.*'
			])
			->get();
		$proposalsDownvote = SurveyDownVoteResult::where('survey_downvote_result.survey_id', $id)
			->where('survey_downvote_result.user_id', $userId)
			->join('proposal', 'survey_downvote_result.proposal_id', '=', 'proposal.id')
			->orderBy('survey_downvote_result.created_at', $sort_direction)
			->select([
				'proposal.title',
				'survey_downvote_result.*'
			])
			->get();
		return [
			'success' => true,
			'voted' => $proposalsVote,
			'downvoted' => $proposalsDownvote,
		];
	}

	public function getListUserVoteSurvey($id, Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$page_id  = $limit = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'survey_result.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		$limit = (int) $limit;
		if ($page_id <= 0) $page_id = 1;
		if ($limit <= 0) $limit = 10;
		$start = $limit * ($page_id - 1);
		// Records
		$users_vote = SurveyResult::where('survey_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$users_downvote =  SurveyDownVoteResult::where('survey_downvote_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$user_ids = array_merge($users_vote, $users_downvote);
		$users = User::whereIn('id', $user_ids)->select(['id', 'email'])
			->offset($start)->limit($limit)
			->get();

		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	public function getNotSubmittedSurvey($id, Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$page_id  = $limit = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;
		if ($limit <= 0) $limit = 10;

		$start = $limit * ($page_id - 1);
		$users_vote = SurveyResult::where('survey_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$users_downvote =  SurveyDownVoteResult::where('survey_downvote_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$user_ids = array_merge($users_vote, $users_downvote);
		$users = User::where('is_member', 1)
			->where('banned', 0)
			->where('can_access', 1)
			->whereNotIn('id', $user_ids)
			->offset($start)
			->limit($limit)
			->get();
		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	public function sendReminderSurvey($id)
	{
		$survey = Survey::where('id', $id)->where('status', 'active')->where('type', 'grant')->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'The survey not exist'
			];
		}
		$users_vote = SurveyResult::where('survey_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$users_downvote =  SurveyDownVoteResult::where('survey_downvote_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$user_ids = array_merge($users_vote, $users_downvote);
		$users = User::where('is_member', 1)
			->where('banned', 0)
			->where('can_access', 1)
			->whereNotIn('id', $user_ids)
			->get();
		foreach ($users as $user) {
			Mail::to($user)->send(new UserAlert('A new DEVxDAO survey needs your responses', 'Please log in to your portal to complete your survey. This is mandatory!'));
		}
		return [
			'success' => true
		];
	}

	public function resendComplianceEmail(Request $request)
	{
		$user = Auth::user();
		// Validator
		$validator = Validator::make($request->all(), [
			'proposalId' => 'required',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Provide all the necessary information'
			];
		}
		$proposalId = $request->proposalId;
		$proposal = Proposal::find($proposalId);
		$onboarding  = OnBoarding::where('proposal_id', $proposalId)->first();
		if (!$onboarding || !$proposal) {
			return [
				'success' => false,
				'message' => 'Proposal does not exist'
			];
		}
		$settings = Helper::getSettings();
		$token = Str::random(50);
		if ($settings['compliance_admin']) {
			$title = "Proposal $proposal->id needs a compliance review";
			$public_url = config('app.fe_url') . "/public-proposals/$proposal->id";
			$approve_url = config('app.fe_url') . "/compliance-approve-grant/$proposal->id?token=$token";
			$deny_url = config('app.fe_url') . "/compliance-deny-grant/$proposal->id?token=$token";
			Mail::to($settings['compliance_admin'])->send(new ComplianceReview($title, $proposal, $public_url, $approve_url, $deny_url));
		}
		$onboarding->compliance_token = $token;
		$onboarding->admin_email = $settings['compliance_admin'];
		$onboarding->save();
		return [
			'success' => true,
		];
	}

	public function approveComplianceReview(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'proposalId' => 'required',
			'token' => 'required',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Provide all the necessary information'
			];
		}
		$settings = Helper::getSettings();
		$proposalId = $request->proposalId;
		$proposal = Proposal::find($proposalId);
		$onboarding  = OnBoarding::where('proposal_id', $proposalId)->where('compliance_token', $request->token)->first();
		if (!$onboarding || !$proposal) {
			return [
				'success' => false,
				'message' => 'Proposal does not exist'
			];
		}
		if (in_array($onboarding->compliance_status, ['denied', 'approved'])) {
			return [
				'success' => false,
				'message' => "Can not perform this action. Proposal has been $onboarding->compliance_status",
				"data" => [
					'proposal' => $proposal,
					'onboarding' => $onboarding,
					'compliance_admin' => $settings['compliance_admin'],
				]
			];
		}
		$onboarding->compliance_status = 'approved';
		$onboarding->compliance_reviewed_at = now();
		$onboarding->save();
		Helper::createGrantTracking($proposalId, "ETA compliance complete", 'eta_compliance_complete');
		$shuftipro = Shuftipro::where('user_id', $onboarding->user_id)->where('status', 'approved')->first();
		if($shuftipro) {
			$onboarding->status = 'completed';
			$onboarding->save();
			$vote = Vote::find($onboarding->vote_id);
			$op = User::find($onboarding->user_id);
			$emailerData = Helper::getEmailerData();
			if ($vote && $op && $proposal) {
				Helper::triggerUserEmail($op, 'Passed Informal Grant Vote', $emailerData, $proposal, $vote);
			}
			Helper::startFormalVote($vote);
		}
		return [
			'success' => true,
			'proposal' => $proposal,
			'onboarding' => $onboarding,
			'compliance_admin' => $settings['compliance_admin'],
		];
	}

	public function denyComplianceReview(Request $request)
	{
		$validator = Validator::make($request->all(), [
			'proposalId' => 'required',
			'token' => 'required',
			'reason' => 'required',
		]);
		if ($validator->fails()) {
			return [
				'success' => false,
				'message' => 'Provide all the necessary information'
			];
		}
		$settings = Helper::getSettings();
		$proposalId = $request->proposalId;
		$proposal = Proposal::find($proposalId);
		$onboarding  = OnBoarding::where('proposal_id', $proposalId)->where('compliance_token', $request->token)->first();
		if (!$onboarding || !$proposal) {
			return [
				'success' => false,
				'message' => 'Proposal does not exist'
			];
		}
		if (in_array($onboarding->compliance_status, ['denied', 'approved'])) {
			return [
				'success' => false,
				'message' => "Can not perform this action. Proposal has been $onboarding->compliance_status",
				'data' => [
					'proposal' => $proposal,
					'onboarding' => $onboarding,
					'compliance_admin' => $settings['compliance_admin'],
				]
			];
		}
		$onboarding->compliance_status = 'denied';
		$onboarding->compliance_reviewed_at = now();
		$onboarding->deny_reason = $request->reason;
		$onboarding->save();
		return [
			'success' => true,
			'proposal' => $proposal,
			'onboarding' => $onboarding,
			'compliance_admin' => $settings['compliance_admin'],
		];
	}

	public function getSurveyWin(Request $request)
	{
		$page_id = 0;
		$sort_key = $sort_direction  = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);
		if (!$sort_key) $sort_key = 'rank';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;
		$limit = isset($data['limit']) ? $data['limit'] : 20;
		$start = $limit * ($page_id - 1);
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$current_status = $request->current_status;
		$spot_rank = $request->spot_rank;

		$proposals = SurveyRank::where('is_winner', 1)
			->join('survey', 'survey.id', '=', 'survey_rank.survey_id')
			->join('proposal', 'proposal.id', '=', 'survey_rank.proposal_id')
			->select([
				'proposal.*',
				'survey.end_time',
				'survey_rank.rank',
				'survey.id as survey_id',
			])
			->where(function ($query) use ($start_date, $end_date) {
				if ($start_date) {
					$query->whereDate('survey.end_time', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('survey.end_time', '<=', $end_date);
				}
			})->orderBy('survey_rank.rank', 'asc')->get();
		foreach ($proposals as $proposal) {
			$status =  Helper::getStatusProposal($proposal);
			$key_status = str_replace(' ', '_', strtolower($status));
			$proposal->end_time = Carbon::parse($proposal->end_time);
			$proposal->current_status = $key_status;
			$proposal->status = [
				'label' => $status,
				'key' =>  str_replace(' ', '_', strtolower($status)),
			];
		}

		if ($current_status) {
			$proposals = $proposals->where('current_status', $current_status);
		}
		if ($spot_rank) {
			$proposals = $proposals->where('rank', $spot_rank);
		}
		if ($sort_direction == 'asc') {
			$proposals = $proposals->sortBy($sort_key)->values();
		} else {
			$proposals = $proposals->sortByDesc($sort_key)->values();
		}
		$response = $proposals->slice($start, $limit)->values();
		return [
			'success' => true,
			'proposals' => $response,
			'finished' => count($response) < $limit ? true : false
		];
	}

	// Reputation By User
	public function exportCSVReputationByUser($userId, Request $request)
	{

		// Variables
		$sort_key = $sort_direction = $search = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'reputation.id';
		if (!$sort_direction) $sort_direction = 'desc';
		// Records
		$items = Reputation::leftJoin('proposal', 'proposal.id', '=', 'reputation.proposal_id')
			->leftJoin('users', 'users.id', '=', 'proposal.user_id')
			->where('reputation.user_id', $userId)
			->select([
				'reputation.*',
				'proposal.include_membership',
				'proposal.title as proposal_title',
				'users.first_name as op_first_name',
				'users.last_name as op_last_name'
			])
			->orderBy($sort_key, $sort_direction)
			->get();

		return Excel::download(new MyReputationExport($items), "user_" . $userId . "_reputation.csv");
	}

	public function exportCSVtSurveyWin(Request $request)
	{
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$sort_key = $request->sort_key ?? 'rank';
		$sort_direction = $request->sort_direction ?? 'asc';
		$current_status = $request->current_status;
		$spot_rank = $request->spot_rank;
		$proposals = SurveyRank::where('is_winner', 1)
			->join('survey', 'survey.id', '=', 'survey_rank.survey_id')
			->join('proposal', 'proposal.id', '=', 'survey_rank.proposal_id')
			->select([
				'proposal.*',
				'survey.end_time',
				'survey_rank.rank',
				'survey.id as survey_id',
			])
			->where(function ($query) use ($start_date, $end_date) {
				if ($start_date) {
					$query->whereDate('survey.end_time', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('survey.end_time', '<=', $end_date);
				}
			})->orderBy('survey_rank.rank', 'asc')->get();
		foreach ($proposals as $proposal) {
			$status =  Helper::getStatusProposal($proposal);
			$key_status = str_replace(' ', '_', strtolower($status));
			$proposal->end_time = Carbon::parse($proposal->end_time);
			$proposal->current_status = $key_status;
			$proposal->status = [
				'label' => $status,
				'key' =>  str_replace(' ', '_', strtolower($status)),
			];
		}

		if ($current_status) {
			$proposals = $proposals->where('current_status', $current_status);
		}
		if ($spot_rank) {
			$proposals = $proposals->where('rank', $spot_rank);
		}

		if ($sort_direction == 'asc') {
			$proposals = $proposals->sortBy($sort_key)->values();
		} else {
			$proposals = $proposals->sortByDesc($sort_key)->values();
		}
		return Excel::download(new SurveyWinExport($proposals), 'survey_win.csv');
	}

	public function exportCSVVoteSurvey($id, Request $request)
	{
		$survey = Survey::where('id', $id)->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		// Variables
		$sort_key = $sort_direction = $search = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'desc';
		$proposals = Proposal::where('proposal.status', 'approved')
			->doesntHave('votes')
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%');
				}
			})
			->get();
		$results = SurveyResult::where('survey_id', $id)->get();
		foreach ($proposals as $proposal) {
			for ($i = 1; $i <= $survey->number_response; $i++) {
				$key =  'place_' . $i;
				$proposal->$key = null;
			}
			$total_vote = count($results->where("proposal_id", $proposal->id));
			$proposal->total_vote = $total_vote;
			if ($total_vote) {
				for ($i = 1; $i <= $survey->number_response; $i++) {
					$key =  'place_' . $i;
					$proposal->$key = count($results->where("proposal_id", $proposal->id)->where('place_choice', $i));
				}
			}
		}
		if ($sort_direction == 'asc') {
			$sorted = $proposals->sortBy($sort_key)->values();
		} else {
			$sorted = $proposals->sortByDesc($sort_key)->values();
		}
		return Excel::download(new SurveyVoteExport($sorted, $survey), "survey_" . $survey->id . "_vote.csv");
	}

	public function getMentorProposal($userId, Request $request)
	{
		$user = Auth::user();
		$userInfo = User::where('id', $userId)->with(['profile'])->first();
		if (!$userInfo) {
			return [
				'success' => false,
				'message' => 'User not found',
			];
		}
		$forum_name = $userInfo->profile->forum_name ?? '';
		// Variables
		$sort_key = $sort_direction = '';
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
		$proposals = Proposal::where(function ($query) use ($userInfo, $forum_name) {
			$query->where('proposal.name_mentor', $userInfo->email)
				->orWhere('proposal.name_mentor', $forum_name);
		})
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

	public function exportCSVMentorProposal($userId, Request $request)
	{
		$userInfo = User::where('id', $userId)->with(['profile'])->first();
		if (!$userInfo) {
			return [
				'success' => false,
				'message' => 'User not found',
			];
		}
		$forum_name = $userInfo->profile->forum_name ?? '';
		// Variables
		$sort_key = $sort_direction = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'proposal.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$proposals = Proposal::where(function ($query) use ($userInfo, $forum_name) {
			$query->where('proposal.name_mentor', $userInfo->email)
				->orWhere('proposal.name_mentor', $forum_name);
		})
			->orderBy($sort_key, $sort_direction)
			->get();
		return Excel::download(new ProposalMentorExport($proposals), "user_" . $userInfo->id . "_proposal_mentor.csv");
	}

	// Get Grants
	public function exxportCSVActiveGrants(Request $request)
	{
		// Variables
		$sort_key = $sort_direction = $search = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'final_grant.id';
		if (!$sort_direction) $sort_direction = 'desc';
		$proposals = FinalGrant::join('proposal', 'final_grant.proposal_id', '=', 'proposal.id')
		->join('users', 'final_grant.user_id', '=', 'users.id')
		->where('final_grant.status', '!=', 'pending')
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
		->select([
			'final_grant.*',
			'proposal.title',
			'users.email'
		])
		->get();
		return Excel::download(new ActiveGrantExport($proposals), 'active_grant.csv');
	}

	public function updateShuftiproId($userId, Request $request)
	{
		if(!$request->reference_id) {
			return [
				'success' => false,
				'message' => 'Invalid reference_id'
			];
		}

		if($request->shufti_pass != config('services.shuftipro.pass')) {
			return [
				'success' => false,
				'message' => 'Shufti password is not correct'
			];
		}

		$admin = Auth::user();
		$user = User::find($userId);
		if ($user) {
			$record = Shuftipro::where('user_id', $userId)->first();
			if (!$record) {
				$record = new Shuftipro;
				$record->user_id = $userId;
				$record->reference_id = $request->reference_id;
				$record->is_successful = 1;
				$record->data = '{}';
				$record->document_proof = '';
				$record->address_proof = '';
				$record->document_result = 1;
				$record->address_result = 1;
				$record->background_checks_result = 1;
				$record->save();
			}
			$record->reference_id = $request->reference_id;
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

    public function sendKycKangaroo(Request $request)
	{
        $user = User::find($request->user_id);
        if(!$user) {
            return [
                'success' => false,
                'message' => 'Not found user'
			];
        }
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
					'message' => $kyc_response['message'],
					'invite' => $kyc_response['invite'] ?? null,
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

	public function getSurveyDownvote(Request $request)
	{
		$page_id = 0;
		$sort_key = $sort_direction  = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);
		if (!$sort_key) $sort_key = 'rank';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;
		$limit = isset($data['limit']) ? $data['limit'] : 20;
		$start = $limit * ($page_id - 1);
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$current_status = $request->current_status;
		$spot_rank = $request->spot_rank;

		$proposals = SurveyDownVoteRank::where('is_winner', 1)
			->join('survey', 'survey.id', '=', 'survey_downvote_rank.survey_id')
			->join('proposal', 'proposal.id', '=', 'survey_downvote_rank.proposal_id')
			->select([
				'proposal.*',
				'survey.end_time',
				'survey.id as survey_id',
				'survey_downvote_rank.rank',
				'survey_downvote_rank.is_approved',
				'survey_downvote_rank.downvote_approved_at',
			])
			->where(function ($query) use ($start_date, $end_date) {
				if ($start_date) {
					$query->whereDate('survey.end_time', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('survey.end_time', '<=', $end_date);
				}
			})->orderBy('survey_downvote_rank.total_point', 'desc')->get();
		foreach ($proposals as $proposal) {
			$status =  Helper::getStatusProposal($proposal);
			$key_status = str_replace(' ', '_', strtolower($status));
			$proposal->end_time = Carbon::parse($proposal->end_time);
			$proposal->current_status = $key_status;
			$proposal->status = [
				'label' => $status,
				'key' =>  str_replace(' ', '_', strtolower($status)),
			];
		}

		if ($current_status) {
			$proposals = $proposals->where('current_status', $current_status);
		}
		if ($spot_rank) {
			$proposals = $proposals->where('rank', $spot_rank);
		}

		if ($sort_direction == 'asc') {
			$proposals = $proposals->sortBy($sort_key)->values();
		} else {
			$proposals = $proposals->sortByDesc($sort_key)->values();
		}
		$response = $proposals->slice($start, $limit)->values();
		return [
			'success' => true,
			'proposals' => $response,
			'finished' => count($response) < $limit ? true : false
		];
	}

	public function approveDowvote(Request $request)
	{
		$proposalId = $request->proposalId;
		$proposal = Proposal::find($proposalId);
		if(!$proposal) {
			return [
                'success' => false,
                'message' => 'Proposal not found'
			];
		}
		$surveyRankDownvote = SurveyDownVoteRank::where('proposal_id', $proposalId)->where('is_winner', 1)->first();
		if(!$surveyRankDownvote) {
			return [
                'success' => false,
                'message' => 'Proposal not found in rank'
			];
		}
		$surveyRankDownvote->is_approved = 1;
		$surveyRankDownvote->downvote_approved_at = now();
		$surveyRankDownvote->save();
		$user = User::find($proposal->user_id);
		if($user) {
			$title = "Your proposal $proposal->id has been downvoted";
			$content = "Your proposal $proposal->id regarding $proposal->title has been downvoted by the DxD voting associate group. The discussion for the proposal has been ended and the proposal cannot move forward.";
			Mail::to($user->email)->send(new UserAlert($title, $content));
		}
		return  [
			'success' => true,
		];
	}

	public function getDisscustionDownvote($id, Request $request)
	{
		$survey = Survey::where('id', $id)->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		$user = Auth::user();
		$proposals = [];

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

		// Record
		$proposals = Proposal::where('proposal.status', 'approved')
			->doesntHave('votes')
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%');
				}
			})
			->get();
		$results = SurveyDownVoteResult::where('survey_id', $id)->get();
		foreach ($proposals as $proposal) {
			for ($i = 1; $i <= $survey->number_response; $i++) {
				$key = $i . '_place';
				$proposal->$key = null;
			}
			$total_vote = count($results->where("proposal_id", $proposal->id));
			$proposal->total_vote = $total_vote;
			if ($total_vote) {
				for ($i = 1; $i <= $survey->number_response; $i++) {
					$key = $i . '_place';
					$proposal->$key = count($results->where("proposal_id", $proposal->id)->where('place_choice', $i));
				}
			}
		}
		if ($sort_direction == 'asc') {
			$sorted = $proposals->sortBy($sort_key)->values();
		} else {
			$sorted = $proposals->sortByDesc($sort_key)->values();
		}
		$response = $sorted->slice($start, $limit)->values();
		return [
			'success' => true,
			'proposals' => $response,
			'finished' => count($response) < $limit ? true : false
		];
	}

	public function exportCSVDownvoteSurvey($id, Request $request)
	{
		$survey = Survey::where('id', $id)->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}
		// Variables
		$sort_key = $sort_direction = $search = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'desc';
		$proposals = Proposal::where('proposal.status', 'approved')
			->doesntHave('votes')
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('proposal.title', 'like', '%' . $search . '%')
						->orWhere('proposal.id', 'like', '%' . $search . '%');
				}
			})
			->get();
		$results = SurveyDownVoteResult::where('survey_id', $id)->get();
		foreach ($proposals as $proposal) {
			for ($i = 1; $i <= $survey->number_response; $i++) {
				$key =  'place_' . $i;
				$proposal->$key = null;
			}
			$total_vote = count($results->where("proposal_id", $proposal->id));
			$proposal->total_vote = $total_vote;
			if ($total_vote) {
				for ($i = 1; $i <= $survey->number_response; $i++) {
					$key =  'place_' . $i;
					$proposal->$key = count($results->where("proposal_id", $proposal->id)->where('place_choice', $i));
				}
			}
		}
		if ($sort_direction == 'asc') {
			$sorted = $proposals->sortBy($sort_key)->values();
		} else {
			$sorted = $proposals->sortByDesc($sort_key)->values();
		}
		return Excel::download(new SurveyVoteExport($sorted, $survey), "survey_downvote_" . $survey->id . "_vote.csv");
	}

	public function exportCSVSurveyDownvote(Request $request)
	{
		$start_date = $request->start_date;
		$end_date = $request->end_date;
		$sort_key = $request->sort_key ?? 'rank';
		$sort_direction = $request->sort_direction ?? 'asc';
		$current_status = $request->current_status;
		$spot_rank = $request->spot_rank;
		$proposals = SurveyDownVoteRank::where('is_winner', 1)
			->join('survey', 'survey.id', '=', 'survey_downvote_rank.survey_id')
			->join('proposal', 'proposal.id', '=', 'survey_downvote_rank.proposal_id')
			->select([
				'proposal.*',
				'survey.end_time',
				'survey.id as survey_id',
				'survey_downvote_rank.rank',
				'survey_downvote_rank.is_approved',
				'survey_downvote_rank.downvote_approved_at',
			])
			->where(function ($query) use ($start_date, $end_date) {
				if ($start_date) {
					$query->whereDate('survey.end_time', '>=', $start_date);
				}
				if ($end_date) {
					$query->whereDate('survey.end_time', '<=', $end_date);
				}
			})->orderBy('survey_downvote_rank.total_point', 'desc')->get();
		foreach ($proposals as $proposal) {
			$status =  Helper::getStatusProposal($proposal);
			$key_status = str_replace(' ', '_', strtolower($status));
			$proposal->end_time = Carbon::parse($proposal->end_time);
			$proposal->current_status = $key_status;
			$proposal->status = [
				'label' => $status,
				'key' =>  str_replace(' ', '_', strtolower($status)),
			];
		}

		if ($current_status) {
			$proposals = $proposals->where('current_status', $current_status);
		}
		if ($spot_rank) {
			$proposals = $proposals->where('rank', $spot_rank);
		}

		if ($sort_direction == 'asc') {
			$proposals = $proposals->sortBy($sort_key)->values();
		} else {
			$proposals = $proposals->sortByDesc($sort_key)->values();
		}
		return Excel::download(new SurveyDownvoteExport($proposals), 'survey_downvote.csv');
	}

	public function getVote($id)
	{
		$admin = Auth::user();
		if ($admin) {
			$vote = Vote::with([
					'proposal',
					'proposal.bank',
				   	'proposal.crypto',
				   	'proposal.grants',
				   	'proposal.citations',
				   	'proposal.citations.repProposal',
				   	'proposal.citations.repProposal.user',
				   	'proposal.citations.repProposal.user.profile',
				   	'proposal.milestones',
				   	'proposal.members',
				   	'proposal.files',
				])
				->where('id', $id)
				->first();

			return [
				'success' => true,
				'vote' => $vote,
			];
		}
		return ['success' => false];
	}

	public function getVoteResult($id, Request $request)
	{
		$admin = Auth::user();
		if ($admin) {
			$voteResults = [];

			// Variables
			$sort_key = $sort_direction = '';
			$page_id = 0;
			$data = $request->all();
			if ($data && is_array($data)) extract($data);

			if (!$sort_key) $sort_key = 'vote_result.updated_at';
			if (!$sort_direction) $sort_direction = 'desc';
			$page_id = (int) $page_id;
			if ($page_id <= 0) $page_id = 1;

			$limit = isset($data['limit']) ? $data['limit'] : 10;
			$start = $limit * ($page_id - 1);

			$voteResults = VoteResult::with(['user', 'user.profile'])
				->where('vote_id', $id)
				->orderBy($sort_key, $sort_direction)
				->offset($start)
				->limit($limit)
				->get();

			return [
				'success' => true,
				'vote_results' => $voteResults,
				'finished' => count($voteResults) < $limit ? true : false
			];
		}
		return ['success' => false];
	}

	public function getProposalPdfUrl($proposalId)
    {
		$admin = Auth::user();
		if ($admin && $admin->hasRole('admin')) {
			$proposal = Proposal::where('id', $proposalId)
				->with([
					'user',
					'user.profile',
					'user.shuftipro',
					'grants',
					'milestones',
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

			if (!$proposal) {
				return [
					'success' => false,
					'message' => 'Invalid proposal'
				];
			}

			// Sponsor
			$proposal->sponsor = Helper::getSponsor($proposal);

			// Loser
			$proposal->loser = $proposal->surveyDownVoteRanks->first(function ($value, $key) {
				return $value->is_winner && $value->is_approved;
			});
			// Winner
			$proposal->winner = $proposal->surveyRanks->first(function ($value, $key) {
				return $value->is_winner;
			});

			$pdf = PDF::loadView('proposal_pdf', compact('proposal'));
			$fullpath = 'pdf/proposal/proposal_' . $proposal->id . '.pdf';
			Storage::disk('local')->put($fullpath, $pdf->output());
			$url = Storage::disk('local')->url($fullpath);
			$proposal->pdf = $url;
			Proposal::where('id', $proposal->id)->update(['pdf' => $url]);

			return [
				'success' => true,
				'pdf_link_url' => $url
			];
		}
		return ['success' => false];
    }

	public function verifyMasterPassword(Request $request)
	{
		$admin = Auth::user();
		if ($admin && $admin->hasRole('admin')) {
			return [
				'success' => (config('auth.master_password') ?? false)
					? $request->password == config('auth.master_password')
					: false,
			];
		}
		return ['success' => false];
	}

	public function getSurveyRfp(Request $request)
	{
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
		$total_member = Helper::getTotalMembers();
		// Records
		$surveys = Survey::where('status', $status)
			->where('type', 'rfp')
			->with(['surveyRfpBids'])
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
	public function getListUserVoteSurveyRfp($id, Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$page_id  = $limit = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'survey_result.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		$limit = (int) $limit;
		if ($page_id <= 0) $page_id = 1;
		if ($limit <= 0) $limit = 10;
		$start = $limit * ($page_id - 1);
		// Records
		$user_ids = SurveyRfpResult::where('survey_rfp_result.survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$users = User::whereIn('id', $user_ids)->select(['id', 'email'])
			->offset($start)->limit($limit)
			->get();

		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	public function getVoteSurveyrfpByUser($id, $userId, Request $request)
	{
		$admin = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'survey_rfp_result.created_at';
		if (!$sort_direction) $sort_direction = 'desc';
		// Records
		$results = SurveyRfpResult::where('survey_rfp_result.survey_id', $id)
			->where('survey_rfp_result.user_id', $userId)
			->join('survey_rfp_bid', function ($join) {
				$join->on('survey_rfp_bid.survey_id', '=', 'survey_rfp_result.survey_id');
				$join->on('survey_rfp_bid.bid', '=', 'survey_rfp_result.bid');
			})
			->orderBy($sort_key, $sort_direction)
			->select([
				'survey_rfp_bid.*',
				'survey_rfp_result.*'
			])
			->get();
		return [
			'success' => true,
			'voted' => $results,
		];
	}

	public function getVoteBidSurveyRfp($id, Request $request)
	{
		$survey = Survey::where('id', $id)->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'asc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;

		$limit = isset($data['limit']) ? $data['limit'] : 10;
		$start = $limit * ($page_id - 1);

		// Record
		$bids = SurveyRfpBid::where('survey_rfp_bid.survey_id', $id)
			->where(function ($query) use ($search) {
				if ($search) {
					$query->where('survey_rfp_bid.bid', 'like', '%' . $search . '%')
						->orWhere('survey_rfp_bid.forum', 'like', '%' . $search . '%')
						->orWhere('survey_rfp_bid.amount_of_bid', 'like', '%' . $search . '%');
				}
			})
			->get();
		$results = SurveyRfpResult::where('survey_id', $id)->get();
		foreach ($bids as $bid) {
			for ($i = 1; $i <= $survey->number_response; $i++) {
				$key = $i . '_place';
				$bid->$key = null;
			}
			$total_vote = count($results->where("bid", $bid->bid));
			$bid->total_vote = $total_vote;
			if ($total_vote) {
				for ($i = 1; $i <= $survey->number_response; $i++) {
					$key = $i . '_place';
					$bid->$key = count($results->where("bid", $bid->bid)->where('place_choice', $i));
				}
			}
		}
		if ($sort_direction == 'asc') {
			$sorted = $bids->sortBy($sort_key)->values();
		} else {
			$sorted = $bids->sortByDesc($sort_key)->values();
		}
		$response = $sorted->slice($start, $limit)->values();
		return [
			'success' => true,
			'bids' => $response,
			'finished' => count($response) < $limit ? true : false
		];
	}

	public function exportCSVVoteSurveyRfp($id, Request $request)
	{
		$survey = Survey::where('id', $id)->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'Not found survey'
			];
		}

		// Variables
		$sort_key = $sort_direction = $search = '';
		$page_id = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'asc';
		// Record
		$bids = SurveyRfpBid::where('survey_rfp_bid.survey_id', $id)
		->where(function ($query) use ($search) {
			if ($search) {
				$query->where('survey_rfp_bid.bid', 'like', '%' . $search . '%')
					->orWhere('survey_rfp_bid.forum', 'like', '%' . $search . '%')
					->orWhere('survey_rfp_bid.amount_of_bid', 'like', '%' . $search . '%');
			}
		})
		->orderBy($sort_key, $sort_direction)
		->get();
		$results = SurveyRfpResult::where('survey_id', $id)->get();
		foreach ($bids as $bid) {
			for ($i = 1; $i <= $survey->number_response; $i++) {
				$key = $i . '_place';
				$bid->$key = null;
			}
			$total_vote = count($results->where("bid", $bid->bid));
			$bid->total_vote = $total_vote;
			if ($total_vote) {
				for ($i = 1; $i <= $survey->number_response; $i++) {
					$key = $i . '_place';
					$bid->$key = count($results->where("bid", $bid->bid)->where('place_choice', $i));
				}
			}
		}
		return Excel::download(new SurveyVoteRfpExport($bids, $survey), "survey_rfp_" . $survey->id . "_vote.csv");
	}

	public function getNotSubmittedSurveyRfp($id, Request $request)
	{
		$user = Auth::user();
		// Variables
		$sort_key = $sort_direction = '';
		$page_id  = $limit = 0;
		$data = $request->all();
		if ($data && is_array($data)) extract($data);

		if (!$sort_key) $sort_key = 'id';
		if (!$sort_direction) $sort_direction = 'desc';
		$page_id = (int) $page_id;
		if ($page_id <= 0) $page_id = 1;
		if ($limit <= 0) $limit = 10;

		$start = $limit * ($page_id - 1);
		$user_ids = SurveyRfpResult::where('survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$users = User::where('is_member', 1)
			->where('banned', 0)
			->where('can_access', 1)
			->whereNotIn('id', $user_ids)
			->offset($start)
			->limit($limit)
			->get();
		return [
			'success' => true,
			'users' => $users,
			'finished' => count($users) < $limit ? true : false
		];
	}

	public function sendReminderSurveyRfp($id)
	{
		$survey = Survey::where('id', $id)->where('status', 'active')->where('type', 'rfp')->first();
		if (!$survey) {
			return [
				'success' => false,
				'message' => 'The survey not exist'
			];
		}
		$user_ids = SurveyRfpResult::where('survey_id', $id)->distinct('user_id')->pluck('user_id')->toArray();
		$users = User::where('is_member', 1)
			->where('banned', 0)
			->where('can_access', 1)
			->whereNotIn('id', $user_ids)
			->get();
		foreach ($users as $user) {
			Mail::to($user)->send(new UserAlert('A new DEVxDAO survey needs your responses', 'Please log in to your portal to complete your survey. This is mandatory!'));
		}
		return [
			'success' => true
		];
	}
}
