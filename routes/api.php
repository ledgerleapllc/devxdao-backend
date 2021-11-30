<?php

use App\Proposal;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

Route::get('/test-email', 'AdminController@testEmail');
Route::get('/test-stripe', 'UserController@testStripe');
Route::get('/test-job', 'UserController@testJob');
Route::get('/pre-register-user', 'SharedController@getPreRegisterUser');
Route::get('/shared/all-proposals-2', 'SharedController@getAllProposals2');
Route::get('/shared/all-proposals-2/{proposalId}', 'SharedController@getDeatilProposal2');
Route::get('/shared/public/proposals/{proposalId}/changes', 'SharedController@getPublicProposalChanges');
Route::get('/shared/public/global-settings', 'SharedController@getGlobalSettings');
Route::get('/shared/public/all-milestones', 'AdminController@getAllMilestone');
Route::get('/shared/public/all-milestones/{milestoneId}', 'AdminController@getMilestoneDetail');

// Webhook
Route::post('/hellosign', 'SharedController@hellosignHook');

Route::post('/csv', 'APIController@downloadCSV');
Route::post('/login', 'APIController@login')->name('login');
Route::post('/register', 'APIController@register');
Route::post('/register-admin', 'APIController@registerAdmin');
Route::post('/pre-register', 'APIController@registerPre');
Route::post('/start-guest', 'APIController@startGuest');
Route::post('/send-reset-email', 'APIController@sendResetEmail');
Route::post('/reset-password', 'APIController@resetPassword');
Route::post('/ops/login', 'OpsController@login')->name('ops-login');;
Route::post('/compliance-review/approve', 'AdminController@approveComplianceReview');
Route::post('/compliance-review/deny', 'AdminController@denyComplianceReview');
Route::post('/compliance/login', 'ComplianceController@login')->name('compliance-login');;

Route::get('/admin/milestone/export-csv', 'AdminController@exportMilestone');
Route::get('/admin/proposal/export-csv', 'SharedController@exportProposal');
Route::get('/admin/dos-fee/export-csv', 'AdminController@exportCSVDosFee');
Route::get('/admin/user/export-csv', 'AdminController@exportCSVUser');
Route::get('/admin/user/{userId}/reputation/export-csv', 'AdminController@exportCSVReputationByUser');
Route::get('/admin/survey-win/export-csv', 'AdminController@exportCSVtSurveyWin');
Route::get('/admin/survey-downvote/export-csv', 'AdminController@exportCSVSurveyDownvote');
Route::get('/survey-vote/{id}/export-csv', 'AdminController@exportCSVVoteSurvey');
Route::get('/admin/user/{userId}/proposal-mentor/export-csv', 'AdminController@exportCSVMentorProposal');
Route::get('/admin/active-grant/export-csv', 'AdminController@exxportCSVActiveGrants');
Route::get('/survey-downvote/{id}/export-csv', 'AdminController@exportCSVDownvoteSurvey');
Route::get('/shared/proposal/{proposalId}/vote/{voteId}/vote-result/export-csv', 'SharedController@exportCSVProposal');
Route::get('/shared/proposal/{proposalId}/vote/{voteId}/vote-result/export-pdf', 'SharedController@generateVoteProposalDetail');
Route::get('/admin/survey-rfp-vote/{id}/export-csv', 'AdminController@exportCSVVoteSurveyRfp');

Route::group(['prefix' => 'va'], function () {
	Route::get('/email/{email_address}', 'APIController@getVAmemberByEmail');
	Route::get('/all', 'APIController@getVAmembers');
});

Route::group(['prefix' => 'rfp'], function () {
	Route::post('/survey', 'APIController@createRfpSurvey');
	Route::post('/survey/{id}/bid', 'APIController@createSurveyBid');
	Route::get('/survey/{id}', 'APIController@getSurveyDetail');
});


Route::group(['middleware' => ['auth:api']], function () {
	// GET
	Route::get('/me', 'APIController@getMe');

	// POST
	Route::post('/verify-code', 'APIController@verifyCode');
	Route::post('/complete-step-review2', 'APIController@completeStepReview2');
	Route::post('/resend-code', 'APIController@resendCode');
});

Route::group(['prefix' => 'shared', 'middleware' => ['auth:api']], function () {
	// POST
	Route::post('/proposal/upload', 'SharedController@uploadProposalFiles');
	Route::post('/informal-voting', 'SharedController@startInformalVoting');
	Route::post('/formal-voting', 'SharedController@startFormalVoting');
	Route::post('/restart-voting', 'SharedController@restartVoting');
	Route::post('/change-password', 'SharedController@changePassword');
	Route::post('/generate-2fa', 'SharedController@generate2FA');
	Route::post('/check-2fa', 'SharedController@check2FA');
	Route::post('/check-proposal', 'SharedController@checkProposal');
	Route::post('/check-login-2fa', 'SharedController@checkLogin2FA');
	Route::post('/enable-2fa-login', 'SharedController@enable2FALogin');
	Route::post('/disable-2fa-login', 'SharedController@disable2FALogin');
	Route::post('/resend-kyc-kangaroo', 'SharedController@resendKycKangaroo');

	// PUT
	Route::put('/proposal/{proposalId}', 'SharedController@updateProposal');
	Route::put('/simple-proposal/{proposalId}', 'SharedController@updateSimpleProposal');
	Route::put('/admin-grant-proposal/{proposalId}', 'SharedController@updateAdminGrantProposal');
	Route::put('/proposal/{proposalId}/withdraw', 'SharedController@withdrawProposal');
	Route::put('/proposal/{proposalId}/force-withdraw', 'SharedController@forceWithdrawProposal');
	Route::put('/profile', 'SharedController@updateProfile');
	Route::put('/profile-info', 'SharedController@updateProfileInfo');
	Route::put('/account-info', 'SharedController@updateAccountInfo');

	// GET
	Route::get('/completed-votes', 'SharedController@getCompletedVotes');
	Route::get('/active-informal-votes', 'SharedController@getActiveInformalVotes');
	Route::get('/active-formal-votes', 'SharedController@getActiveFormalVotes');
	Route::get('/global-settings', 'SharedController@getGlobalSettings');
	Route::get('/active-discussions', 'SharedController@getActiveDiscussions');
	Route::get('/completed-discussions', 'SharedController@getCompletedDiscussions');
	Route::get('/proposal/{proposalId}', 'SharedController@getSingleProposal');
	Route::get('/proposal/{proposalId}/edit', 'SharedController@getSingleProposalEdit');
	Route::get('/proposal/{proposalId}/changes', 'SharedController@getProposalChanges');
	Route::get('/proposal/{proposalId}/change/{proposalChangeId}', 'SharedController@getSingleProposalChange');
	Route::get('/proposal/{proposalId}/change/{proposalChangeId}/comments', 'SharedController@getProposalChangeComments');

	Route::get('/pending-proposals', 'SharedController@getPendingProposals');
	Route::get('/active-proposals', 'SharedController@getActiveProposals');
	Route::get('/all-proposals', 'SharedController@getAllProposals');
	Route::get('/completed-proposals', 'SharedController@getCompletedProposals');
	Route::get('/grants', 'SharedController@getGrants');
	Route::get('/proposal/{proposalId}/trackings', 'SharedController@getTrackingProposal');
});

// User Functions
Route::group(['prefix' => 'user', 'middleware' => ['auth:api']], function () {
	// POST
	Route::post('/force-approve-kyc', 'UserController@forceApproveKYC');
	Route::post('/force-deny-kyc', 'UserController@forceDenyKYC');
	Route::post('/milestone', 'UserController@submitMilestone');
	Route::post('/proposal', 'UserController@submitProposal');
	Route::post('/simple-proposal', 'UserController@submitSimpleProposal');
	Route::post('/admin-grant-proposal', 'UserController@submitAdminGrantProposal');
	Route::post('/advance-payment-proposal', 'UserController@submitAdvancePaymentProposal');
	Route::post('/proposal-change', 'UserController@submitProposalChange');
	Route::post('/proposal-change-comment', 'UserController@submitProposalChangeComment');
	Route::post('/vote', 'UserController@submitVote');
	Route::post('/shuftipro-temp', 'UserController@saveShuftiproTemp');
	Route::post('/hellosign-request', 'UserController@sendHellosignRequest');
	Route::post('/help', 'UserController@requestHelp');
	Route::post('/sponsor-code', 'UserController@createSponsorCode');
	Route::post('/check-sponsor-code', 'UserController@checkSponsorCode');
	Route::post('/associate-agreement', 'UserController@associateAgreement');
	Route::post('/press-dismiss', 'UserController@pressDismiss');
	Route::post('/check-active-grant', 'UserController@checkActiveGrant');
	Route::post('/proposal/{proposalId}/formal-milestone-voting', 'UserController@startFormalMilestoneVoting');
	Route::post('/check-first-completed-proposal', 'UserController@checkFirstCompletedProposal');
	Route::post('/proposal-draft', 'UserController@submitDraftProposal');
	Route::post('/proposal-draft/upload', 'UserController@uploadFiletDraftProposal');
	Route::post('/survey/down-vote/{id}', 'UserController@submitDownVoteSurvey');
	Route::post('/survey/{id}', 'UserController@submitSurvey');
	Route::post('/survey-rfp/{id}', 'UserController@submitSurveyRfp');
	Route::post('/send-kyc-kangaroo', 'UserController@sendKycKangaroo');
	Route::post('/check-mentor', 'UserController@checkMentor');
	Route::post('/reputation-daily-csv', 'UserController@settingDailyCSVReputation');
	Route::post('/check-send-kyc', 'UserController@checkSendKyc');

	// DELETE
	Route::delete('/sponsor-code/{codeId}', 'UserController@revokeSponsorCode');
	Route::delete('/proposal-draft/{id}', 'UserController@deleteDraftProposal');

	// PUT
	Route::put('/payment-proposal/{proposalId}', 'UserController@updatePaymentProposal');
	Route::put('/payment-proposal/{proposalId}/payment-intent', 'UserController@createPaymentIntent');
	Route::put('/payment-proposal/{proposalId}/stake-reputation', 'UserController@stakeReputation');
	Route::put('/payment-proposal/{proposalId}/stake-cc', 'UserController@stakeCC');
	Route::put('/proposal-change/{proposalChangeId}/support-up', 'UserController@supportUpProposalChange');
	Route::put('/proposal-change/{proposalChangeId}/support-down', 'UserController@supportDownProposalChange');
	Route::put('/proposal-change/{proposalChangeId}/approve', 'UserController@approveProposalChange');
	Route::put('/proposal-change/{proposalChangeId}/deny', 'UserController@denyProposalChange');
	Route::put('/proposal-change/{proposalChangeId}/withdraw', 'UserController@withdrawProposalChange');
	Route::put('/shuftipro-temp', 'UserController@updateShuftiproTemp');
	Route::put('/proposal/{proposalId}/payment-form', 'UserController@updatePaymentForm');
	Route::put('/show-unvoted-informal', 'UserController@checkShowUnvotedInformal');
	Route::put('/show-unvoted-formal', 'UserController@checkShowUnvotedFormal');


	// GET
	Route::get('/reputation-track', 'UserController@getReputationTrack');
	Route::get('/reputation-track/export-csv', 'UserController@exportCSVReputationTrack');
	Route::get('/active-proposals', 'UserController@getActiveProposals');
	Route::get('/onboardings', 'UserController@getOnboardings');
	Route::get('/my-pending-proposals', 'UserController@getMyPendingProposals');
	Route::get('/my-active-proposals', 'UserController@getMyActiveProposals');
	Route::get('/my-payment-proposals', 'UserController@getMyPaymentProposals');
	Route::get('/active-proposal/{proposalId}', 'UserController@getActiveProposalById'); // Merged
	Route::get('/my-denied-proposal/{proposalId}', 'UserController@getMyDeniedProposalById');
	Route::get('/sponsor-codes', 'UserController@getSponsorCodes');
	Route::get('/proposal-draft', 'UserController@getDraftProposal');
	Route::get('/proposal-draft/{id}', 'UserController@getDraftProposalDetail');
	Route::get('/current-survey', 'UserController@getCurentSurvey');
	Route::get('/list-va', 'UserController@getListUserVA');
	Route::get('/proposal/{proposalId}/milestone-not-submit', 'UserController@getMilestoneNotSubmit');
	Route::get('/proposal/request-payment', 'UserController@getProposalRequestPayment');
	Route::get('/shuftipro-status', 'UserController@getShuftiproStatus');
	Route::get('/survey', 'UserController@getSurveys');
	Route::get('/survey/{id}', 'UserController@getSurveyDetail');
});

// Admin Functions
Route::group(['prefix' => 'admin', 'middleware' => ['auth:api']], function () {
	// GET
	Route::get('/emailer-data', 'AdminController@getEmailerData');
	Route::get('/pending-users', 'AdminController@getPendingUsers');
	Route::get('/pre-register-users', 'AdminController@getPreRegisterUsers');
	Route::get('/users', 'AdminController@getUsers');
	Route::get('/user/{userId}', 'AdminController@getSingleUser');
	Route::get('/user/{userId}/proposals', 'AdminController@getProposalsByUser');
	Route::get('/user/{userId}/votes', 'AdminController@getVotesByUser');
	Route::get('/user/{userId}/reputation', 'AdminController@getReputationByUser');
	Route::get('/pending-actions', 'AdminController@getPendingActions');
	Route::get('/proposal/{proposalId}', 'AdminController@getProposalById'); // Merged
	Route::get('/pending-grant-onboardings', 'AdminController@getPendingGrantOnboardings');
	Route::get('/move-to-formal-votes', 'AdminController@getMoveToFormalVotes');
	Route::get('/grant/{grantId}/file-url', 'AdminController@getUrlFileHellosignGrant');
	Route::get('/vote/{id}/user-not-vote', 'AdminController@getListUserNotVote');
	Route::get('/metrics ', 'AdminController@getMetrics');
	Route::get('/milestone-reviews', 'AdminController@getListMilestoneReview');
	Route::get('/milestone-reviews/{milestoneId}', 'AdminController@getMilestoneDetailReview');
	Route::get('/milestone-proposal', 'AdminController@getProposalHasMilestone');
	Route::get('/milestone-user', 'AdminController@getOPHasMilestone');
	Route::get('/milestone-all', 'AdminController@getAllMilestone');
	Route::get('/milestone/{milestoneId}', 'AdminController@getMilestoneDetail');
	Route::get('/milestone/{milestoneId}/log', 'AdminController@getListMilestoneLog');
	Route::get('/dos-fee', 'AdminController@getDosFee');
	Route::get('/survey', 'AdminController@getSurvey');
	Route::get('/survey-rfp', 'AdminController@getSurveyRfp');
	Route::get('/survey/win', 'AdminController@getSurveyWin');
	Route::get('/survey/downvote', 'AdminController@getSurveyDownvote');
	Route::get('/survey/{id}', 'AdminController@getDetailSurvey');
	Route::get('/survey/{id}/discussions', 'AdminController@getDisscustionVote');
	Route::get('/survey/{id}/downvote/discussions', 'AdminController@getDisscustionDownvote');
	Route::get('/survey/{id}/vote', 'AdminController@getVoteSurvey');
	Route::get('/survey/{id}/user-vote/{userId}', 'AdminController@getVoteSurveyByUser');
	Route::get('/survey/{id}/user-vote', 'AdminController@getListUserVoteSurvey');
	Route::get('/survey/{id}/user-not-submit', 'AdminController@getNotSubmittedSurvey');
	Route::get('/user/{userId}/proposal-mentor', 'AdminController@getMentorProposal');

	Route::get('/survey-rfp/{id}/user-vote/{userId}', 'AdminController@getVoteSurveyrfpByUser');
	Route::get('/survey-rfp/{id}/user-vote', 'AdminController@getListUserVoteSurveyRfp');
	Route::get('/survey-rfp/{id}/result', 'AdminController@getVoteBidSurveyRfp');
	Route::get('/survey-rfp/{id}/user-not-submit', 'AdminController@getNotSubmittedSurveyRfp');


	// POST
	Route::post('/formal-voting', 'AdminController@startFormalVoting');
	Route::post('/formal-milestone-voting', 'AdminController@startFormalMilestoneVoting');
	Route::post('/reset-user-password', 'AdminController@resetUserPassword');
	Route::post('/change-user-type', 'AdminController@changeUserType');
	Route::post('/add-reputation', 'AdminController@addReputation');
	Route::post('/subtract-reputation', 'AdminController@subtractReputation');
	Route::post('/add-emailer-admin', 'AdminController@addEmailerAdmin');
	Route::post('/grant/{grantId}/activate', 'AdminController@activateGrant');
	Route::post('/grant/{grantId}/begin', 'AdminController@beginGrant');
	Route::post('/grant/{grantId}/resend', 'AdminController@resendHellosignGrant');
	Route::post('/grant/{grantId}/remind', 'AdminController@remindHellosignGrant');
	Route::post('/milestone-reviews/{milestoneId}/approve', 'AdminController@approveMilestone');
	Route::post('/milestone-reviews/{milestoneId}/deny', 'AdminController@denyMilestone');
	Route::post('/survey', 'AdminController@submitSurvey');
	Route::post('/survey/{id}/cancel', 'AdminController@cancelSurvey');
	Route::post('/survey/{id}/send-reminder', 'AdminController@sendReminderSurvey');
	Route::post('/resend-compliance-review', 'AdminController@resendComplianceEmail');
	Route::post('user/{userId}/shuftipro-id', 'AdminController@updateShuftiproId');
	Route::post('/send-kyc-kangaroo', 'AdminController@sendKycKangaroo');
	Route::post('/survey/approve-downvote', 'AdminController@approveDowvote');
	Route::post('/verify/master-password', 'AdminController@verifyMasterPassword');
	Route::post('/survey-rfp/{id}/send-reminder', 'AdminController@sendReminderSurveyRfp');

	// DELETE
	Route::delete('/emailer-admin/{adminId}', 'AdminController@deleteEmailerAdmin');

	// PUT
	Route::put('/emailer-trigger-admin/{recordId}', 'AdminController@updateEmailerTriggerAdmin');
	Route::put('/emailer-trigger-user/{recordId}', 'AdminController@updateEmailerTriggerUser');
	Route::put('/emailer-trigger-member/{recordId}', 'AdminController@updateEmailerTriggerMember');

	Route::put('/global-settings', 'AdminController@updateGlobalSettings');

	Route::put('/participant/{userId}/approve-request', 'AdminController@approveParticipantRequest');
	Route::put('/participant/{userId}/deny-request', 'AdminController@denyParticipantRequest');
	Route::put('/participant/{userId}/revoke', 'AdminController@revokeParticipant');
	Route::put('/participant/{userId}/activate', 'AdminController@activateParticipant');
	Route::put('/participant/{userId}/deny', 'AdminController@denyParticipant');

	Route::put('/pre-register/{recordId}/approve', 'AdminController@approvePreRegister');
	Route::put('/pre-register/{recordId}/deny', 'AdminController@denyPreRegister');

	Route::put('/user/{userId}/allow-access', 'AdminController@allowAccessUser');
	Route::put('/user/{userId}/deny-access', 'AdminController@denyAccessUser');

	Route::put('/user/{userId}/ban', 'AdminController@banUser');
	Route::put('/user/{userId}/unban', 'AdminController@unbanUser');
	Route::put('/user/{userId}/approve-kyc', 'AdminController@approveKYC');
	Route::put('/user/{userId}/deny-kyc', 'AdminController@denyKYC');
	Route::put('/user/{userId}/reset-kyc', 'AdminController@resetKYC');

	Route::put('/proposal/{proposalId}/approve', 'AdminController@approveProposal');
	Route::put('/proposal/{proposalId}/deny', 'AdminController@denyProposal');
	Route::put('/proposal/{proposalId}/approve-payment', 'AdminController@approveProposalPayment');
	Route::put('/proposal/{proposalId}/deny-payment', 'AdminController@denyProposalPayment');
	Route::get('/proposal/{proposalId}/file-url', 'AdminController@getProposalPdfUrl');
	Route::put('/proposal-change/{proposalChangeId}/force-approve', 'AdminController@forceApproveProposalChange');
	Route::put('/proposal-change/{proposalChangeId}/force-deny', 'AdminController@forceDenyProposalChange');
	Route::put('/proposal-change/{proposalChangeId}/force-withdraw', 'AdminController@forceWithdrawProposalChange');
	Route::put('/user/{userId}/kyc-info', 'AdminController@updateKYCinfo');

	Route::put('/milestone/{milestoneId}/paid', 'AdminController@updatePaidMilestone');

	Route::prefix('/teams')->group(function () {
		Route::get('/', 'AdminController@getListAdmin');
		Route::post('/invite', 'AdminController@inviteAdmin');
		Route::post('/{id}/re-invite', 'AdminController@resendLink');
		Route::put('/{id}/change-permissions', 'AdminController@changeAdminPermissions');
		Route::post('/{id}/reset-password', 'AdminController@addminResetPassword');
		Route::post('/{id}/revoke', 'AdminController@revokeAdmin');
		Route::get('/{id}/ip-histories', 'AdminController@getIpHistories');
		Route::post('/{id}/undo-revoke', 'AdminController@undoRevokeAdmin');
	});
});

Route::group(['prefix' => 'ops', 'middleware' => ['auth:ops_api']], function () {
	Route::post('/logout', 'OpsController@logout');
	Route::get('/me', 'OpsController@getMeOps');

	Route::prefix('/admin')->group(function () {
		// POST
		Route::post('/users/create-pa-user', 'OpsController@createPAUser');
		Route::post('/users/{id}/revoke', 'OpsController@revokeUser');
		Route::post('/users/{id}/undo-revoke', 'OpsController@undoRevokeUser');
		Route::post('/users/{id}/reset-password', 'OpsController@resetPassword');
		Route::post('/milestone/{milestoneReviewId}/assign', 'OpsController@MilestoneAssign');
		Route::post('/milestone/{milestoneReviewId}/unassign', 'OpsController@milestoneUnassign');

		// GET
		Route::get('/users', 'OpsController@getListUser');
		Route::get('/users/{id}/ip-histories', 'OpsController@getIpHistories');
		Route::get('/milestone-job', 'OpsController@getListMilestoneJob');
		Route::get('/users-pa', 'OpsController@getListUserPA');
		Route::get('/milestone/{milestoneReviewId}', 'OpsController@getMilestoneDetail');
		Route::get('/milestone-assigned', 'OpsController@getListMilestoneAssigned');
	});

	Route::prefix('/user')->group(function () {
		// POST
		Route::post('/milestone/{milestoneReviewId}/submit-review', 'OpsController@submitReviewMilestone');
		Route::post('/milestone/{milestoneReviewId}/note', 'OpsController@updateNodeMilestoneReview');

		// GET
		Route::get('/all', 'OpsController@getUsers');
		Route::get('/milestone-job', 'OpsController@myAssignJobMilestone');
		Route::get('/milestone/{milestoneReviewId}', 'OpsController@getMilestoneDetailAssign');
	});

	Route::prefix('/shared')->group(function () {
		// PUT
		Route::put('/change-password', 'OpsController@changePassword');

		//post
		Route::post('/check-current-password', 'OpsController@checkCurrentPassword');
	});
});

Route::group(['prefix' => 'compliance', 'middleware' => ['auth:compliance_api']], function () {
	Route::post('/logout', 'ComplianceController@logout');
	Route::get('/me', 'ComplianceController@getMe');

	Route::prefix('/admin')->group(function () {
		// POST
		Route::post('/users/create-cm-user', 'ComplianceController@createPAUser');
		Route::post('/users/{id}/revoke', 'ComplianceController@revokeUser');
		Route::post('/users/{id}/undo-revoke', 'ComplianceController@undoRevokeUser');
		Route::post('/users/{id}/reset-password', 'ComplianceController@resetPassword');
		Route::post('/users/{id}/compliance-status', 'ComplianceController@updateComplianceStatus');
		Route::post('/users/{id}/paid-status', 'ComplianceController@updatePaidStatus');
		Route::post('/users/{id}/address-status', 'ComplianceController@updateAddressStatus');

		// GET
		Route::get('/users', 'ComplianceController@getListUser');
		Route::get('/users/{id}/ip-histories', 'ComplianceController@getIpHistories');
	});

	Route::prefix('shared')->group(function () {
		// GET
		Route::get('/pending-grant-onboardings', 'ComplianceController@getPendingGrantOnboardings');
		Route::get('/grants', 'ComplianceController@getGrants');
		Route::get('/grant/{grantId}/file-url', 'AdminController@getUrlFileHellosignGrant');
		Route::get('/compliance-proposal', 'ComplianceController@getComplianceProposal');
		Route::get('/milestone-user', 'AdminController@getOPHasMilestone');
		Route::get('/milestone-proposal', 'AdminController@getProposalHasMilestone');
		Route::get('/milestone-all', 'AdminController@getAllMilestone');
		Route::get('/dos-fee', 'AdminController@getDosFee');
		Route::get('/vote/{id}', 'AdminController@getVote');
		Route::get('/vote/{id}/vote-result', 'AdminController@getVoteResult');
		Route::get('/vote/{id}/user-not-vote', 'AdminController@getListUserNotVote');
		Route::get('/invoice-all', 'ComplianceController@getAllInvoices');
		Route::get('/invoice/{invoiceId}/file-url', 'ComplianceController@getInvoicePdfUrl');
		Route::get('/global-settings', 'SharedController@getGlobalSettings');
		Route::get('/proposal/{proposalId}', 'SharedController@getSingleProposal');
		Route::get('/proposal/{proposalId}/compliance-review', 'ComplianceController@getComplianceReview');
		Route::get('/payment-address/user-va', 'ComplianceController@listUserVaPayment');
		Route::get('/payment-address/pending', 'ComplianceController@getPendingAddressPayment');
		Route::get('/payment-address/current', 'ComplianceController@getCurrentAddressPayment');
		Route::get('/metrics ', 'AdminController@getMetrics');

		// POST
		Route::post('/compliance-review/approve', 'ComplianceController@approveComplianceReview');
		Route::post('/compliance-review/deny', 'ComplianceController@denyComplianceReview');
		Route::post('/grant/{grantId}/resend', 'AdminController@resendHellosignGrant');
		Route::post('/grant/{grantId}/remind', 'AdminController@remindHellosignGrant');

		Route::post('/payment-address', 'ComplianceController@createAddressPayment');
		Route::post('/payment-address-change/{id}/confirm-update', 'ComplianceController@confirmUpdateAddressPayment');
		Route::post('/payment-address-change/{id}/void', 'ComplianceController@voidAddressPayment');

		// PUT
		Route::put('/change-password', 'ComplianceController@updatePassword');
		Route::put('/invoice/{id}/paid', 'ComplianceController@updateInvoicePaid');
	});
});

Route::group(['prefix' => 'compliance'], function () {
	Route::get('/shared/milestone/export-csv', 'AdminController@exportMilestone');
	Route::get('/shared/dos-fee/export-csv', 'AdminController@exportCSVDosFee');
	Route::get('/shared/invoice-all/export-csv', 'ComplianceController@exportCSVInvoices');

	Route::post('/login-user', 'ComplianceController@loginWithUserVa');
});

Route::group(['prefix' => 'compliance', 'middleware' => ['auth:api']], function () {
	Route::post('user/payment-address/change', 'ComplianceController@changePaymentAddress');
	Route::get('user/payment-address/current', 'ComplianceController@getCurrentAddressPaymentUser');
});
