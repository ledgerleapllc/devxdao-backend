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

use App\User;
use App\Profile;
use App\PendingAction;
use App\PreRegister;
use App\Proposal;

use App\Http\Helper;
use App\IpHistory;
use App\Jobs\MemberAlert;
use Laravel\Passport\Token;
use Carbon\Carbon;

use App\Mail\Confirmation;
use App\Mail\PreRegisterMail;
use App\Mail\PreRegisterUser;
use App\Mail\AdminAlert;
use App\Mail\UserAlert;
use App\Mail\ResetPasswordLink;
use App\Mail\LoginTwoFA;
use App\Survey;
use App\SurveyRfpBid;

class APIController extends Controller
{
  public function getVAmemberByEmail($email_address, Request $request)
  {
    $auth = Helper::authorizeExternalAPI();
    if (!$auth) {
      return [
        'success' => false,
        'message' => 'Unauthorized'
      ];
    }
    if (
      !$email_address ||
      $email_address == '' ||
      !filter_var($email_address, FILTER_VALIDATE_EMAIL)
    ) {
      return [
        'success' => false,
        'message' => 'Invalid email address'
      ];
    }

    $user = DB::table('users')->join('profile', 'users.id', '=', 'profile.user_id')
    ->where('users.is_member', 1)->where('users.email', $email_address)
      ->select(['users.id as user_id', 'users.email', 'users.first_name', 'users.last_name', 'profile.forum_name'])
      ->first();
    if ($user) {
      return [
        'success' => true,
        'user' => $user
      ];
    } else {
      return [
        'success' => false,
        'message' => 'Record not found'
      ];
    }
  }

  public function getVAmembers(Request $request)
  {
    $auth = Helper::authorizeExternalAPI();
    if (!$auth) {
      return [
        'success' => false,
        'message' => 'Unauthorized'
      ];
    }

    $users = DB::table('users')->join('profile', 'users.id', '=', 'profile.user_id')
    ->where('users.is_member', 1)
    ->select(['users.id as user_id', 'users.email', 'users.first_name', 'users.last_name', 'profile.forum_name'])
    ->get();

    return [
      'success' => true,
      'users' => $users
    ];
  }


  public function resetPassword(Request $request) {
    // Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required|email',
      'password' => 'required',
      'token' => 'required'
    ]);
    if ($validator->fails()) return ['success' => false];

    $email = $request->get('email');
    $password = $request->get('password');
    $token = $request->get('token');

    // Token Check
    $temp = DB::table('password_resets')
              ->where('email', $email)
              ->first();
    if (!$temp) return ['success' => false];
    if (!Hash::check($token, $temp->token)) return ['success' => false];

    // User Check
    $user = User::where('email', $email)->first();
    if (!$user) {
      return [
        'success' => false,
        'message' => 'Invalid user'
      ];
    }

    $user->password = Hash::make($password);
    $user->save();

    // Clear Tokens
    DB::table('password_resets')
        ->where('email', $email)
        ->delete();

    return ['success' => true];
  }

  public function sendResetEmail(Request $request) {
    // This API always returns true

    // Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required|email'
    ]);
    if ($validator->fails()) return ['success' => true];

    $email = $request->get('email');

    $user = User::where('email', $email)->first();
    if (!$user) return ["success" => true];

    // Clear Tokens
    DB::table('password_resets')
        ->where('email', $email)
        ->delete();

    // Generate New One
    $token = Str::random(60);
    DB::table('password_resets')->insert([
      'email' => $email,
      'token' => Hash::make($token),
      'created_at' => Carbon::now()
    ]);

    $resetUrl = $request->header('origin') . '/password/reset/' . $token . '?email=' . urlencode($email);

    Mail::to($user)->send(new ResetPasswordLink($resetUrl));

    return ['success' => true];
  }

  public function downloadCSV(Request $request) {
    $filename = 'export_' . date('Y-m-d') . '_' . date('H:i:s') . '.csv';
    header('Content-Type: application/csv');
    header('Content-Disposition: attachment; filename="'.$filename.'";');

    $output = fopen('php://output', 'w');
    $action = $request->get('action');

    switch ($action) {
      case "pre-register":
        fputcsv($output, [
          'First Name',
          'Last Name',
          'Email',
          'Becoming a member',
          'Obtaining a grant',
          'If you are requesting to become a member, please state your qualifications',
          'If you are requesting a grant please state what technologyg you will be building, or what service you will be providing, in exchange for granted funds',
          'Registration Date'
        ]);

        $items = PreRegister::get();
        if ($items) {
          foreach ($items as $item) {
            fputcsv($output, [
              $item->first_name,
              $item->last_name,
              $item->email,
              (int) $item->become_member ? "Yes" : "No",
              (int) $item->obtain_grant ? "Yes" : "No",
              $item->qualifications ? $item->qualifications : "",
              $item->technology ? $item->technology : "",
              $item->created_at
            ]);
          }
        }
      break;
    }
  }

	public function login(Request $request) {
		// Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required',
      'password' => 'required'
    ]);
    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Login info is not correct'
      ];
    }

    $email = $request->get('email');
    $password = $request->get('password');

    $user = User::with(['profile', 'shuftipro', 'shuftiproTemp', 'permissions'])
                ->has('profile')
                ->where('email', $email)
                ->first();
    if (!$user) {
      return [
        'success' => false,
        'message' => 'Email does not exist'
      ];
    }

    if (
    	(
    		$user->hasRole('admin') ||
        $user->hasRole('super-admin') ||
    		$user->hasRole('member') ||
        $user->hasRole('participant') ||
        $user->hasRole('guest')
    	) &&
    	$user->profile
    ) {
      if (!Hash::check($password, $user->password)) {
        return [
          'success' => false,
          'message' => 'Password is not correct'
        ];
      }

      if ($user->status == 'denied' || $user->banned == 1) {
        return [
          'success' => false,
          'message' => 'You are banned. Please contact us for further details.'
        ];
      }

      // Generate Token and Return
      // Token::where([
      //   'user_id' => $user->id,
      //   'name' => 'User Access Token'
      // ])->delete();

      $user->last_login_ip_address = request()->ip();
      $user->last_login_at = now();
      $user->save();
      $ipHistory = new IpHistory();
      $ipHistory->user_id = $user->id;
      $ipHistory->ip_address = request()->ip();
      $ipHistory->save();

      $tokenResult = $user->createToken('User Access Token');

      $user->accessTokenAPI = $tokenResult->accessToken;

      // Two FA Setting Check & Code Generate
      if ($user->profile->twoFA_login) {
        $code = Helper::generateTwoFACode();
        $user->profile->twoFA_login_code = $code;
        $user->profile->twoFA_login_time = (int) time();
        $user->profile->twoFA_login_active = 1;
        $user->profile->save();

        Mail::to($user)->send(new LoginTwoFA($code));
      }

      // Total Members
      $user->totalMembers = Helper::getTotalMembers();

      // Membership Proposal
      $user->membership = Helper::getMembershipProposal($user);
      //check active survey
      $user->has_survey = Helper::checkActiveSurvey($user);

      $user->makeVisible([
        "profile",
        "accessTokenAPI",
        "shuftipro",
        "shuftiproTemp",
        'last_login_ip_address'
      ]);
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
        'user' => $user
      ];
    } else {
      return [
        'success' => false,
        'message' => 'Role is not valid'
      ];
    }

    return [
      'success' => false,
      'message' => 'Login info is not correct'
    ];
	}

  // User Pre Registration
  public function registerPre(Request $request) {
    // Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required|email',
      'first_name' => 'required',
      'last_name' => 'required'
    ]);

    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Provide all the necessary information'
      ];
    }

    $first_name = $request->get('first_name');
    $last_name = $request->get('last_name');
    $email = $request->get('email');
    $become_member = (int) $request->get('become_member');
    $obtain_grant = (int) $request->get('obtain_grant');
    $qualifications = $request->get('qualifications');
    $technology = $request->get('technology');

    $item = PreRegister::where('email', $email)->first();
    if ($item) {
      return [
        'success' => false,
        'message' => 'This email is already registered'
      ];
    }

    $item = new PreRegister;
    $item->first_name = $first_name;
    $item->last_name = $last_name;
    $item->email = $email;
    $item->become_member = $become_member;
    $item->obtain_grant = $obtain_grant;
    if ($qualifications)
      $item->qualifications = $qualifications;
    if ($technology)
      $item->technology = $technology;
    $item->save();

    $interest = '';
    if ($become_member) $interest = 'Becoming a member';
    if ($obtain_grant) {
      if ($interest) $interest .= ', Obtaining a grant';
      else $interest = 'Obtaining a grant';
    }

    // Mail to Admin
    Mail::to(['wulf@wulfkaal.com', 'timothy.messer@emergingte.ch', 'wulf.kaal@emergingte.ch', 'hayley.howe@emergingte.ch'])->send(new PreRegisterMail($first_name, $last_name, $email, $interest, $qualifications, $technology));

    // Mail to User
    Mail::to($email)->send(new PreRegisterUser());

    return ['success' => true];
  }

  // User Registration
  public function register(Request $request) {
    // Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required|email',
      'password' => 'required|min:7',
      'first_name' => 'required',
      'last_name' => 'required',
      'forum_name' => 'required'
    ]);

    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Provide all the necessary information'
      ];
    }

    // Get Settings
    $settings = Helper::getSettings();

    // Variables
    $first_name = $request->get('first_name');
    $last_name = $request->get('last_name');
    $email = $request->get('email');
    $password = $request->get('password');
    $forum_name = $request->get('forum_name');
    $company = $request->get('company');
    $guest_key = $request->get('guest_key');
    $telegram = $request->get('telegram');

    $code = Str::random(6);

    if (
      !$first_name ||
      !$last_name ||
      !$email ||
      !$password ||
      !$forum_name
    ) {
      return [
        'success' => false,
        'message' => 'Provide all the necessary information'
      ];
    }

    $user = User::where('email', $email)->first();
    if ($user) {
      return [
        'success' => false,
        'message' => 'The email is already in use'
      ];
    }

    $profile = Profile::where('forum_name', $forum_name)->first();
    if ($profile) {
      return [
        'success' => false,
        'message' => 'The forum name is already in use'
      ];
    }

    // Remove Guest
    if ($guest_key) {
      $guestUser = User::where('guest_key', $guest_key)->first();
      if ($guestUser) {
        Profile::where('user_id', $guestUser->id)->delete();
        $guestUser->delete();
      }
    }

    $user = new User;
    $user->first_name = $first_name;
    $user->last_name = $last_name;
    $user->email = $email;
    $user->password = Hash::make($password);
    $user->email_verified = 0;
    $user->confirmation_code = $code;
    $user->is_participant = 1;

    if (
      $settings &&
      isset($settings['need_to_approve']) &&
      $settings['need_to_approve'] == 'no'
    )
      $user->can_access = 1;

    $user->save();

    $profile = Profile::where('user_id', $user->id)->first();
    if (!$profile) $profile = new Profile;

    $profile->user_id = $user->id;
    if ($company) $profile->company = $company;
    $profile->forum_name = $forum_name;
    $profile->dob = null;
    $profile->country_citizenship = "";
    $profile->country_residence = "";
    $profile->address = "";
    $profile->city = "";
    $profile->zip = "";
    $profile->telegram = $telegram;
    $profile->save();

    $user->assignRole('participant');

    // Generate token and return
    Token::where([
      'user_id' => $user->id,
      'name' => 'User Access Token'
    ])->delete();

    $user->last_login_ip_address = request()->ip();
    $user->last_login_at = now();
    $user->save();
    $ipHistory = new IpHistory();
    $ipHistory->user_id = $user->id;
    $ipHistory->ip_address = request()->ip();
    $ipHistory->save();

    $tokenResult = $user->createToken('User Access Token');

    $user->accessTokenAPI = $tokenResult->accessToken;
    $user->profile = $profile;

    // Check Pre Register
    $preRegister = PreRegister::where('email', $email)->first();
    if ($preRegister) {
      $preRegister->status = 'completed';
      $preRegister->hash = null;
      $preRegister->save();
    }

    // Total Members
    $user->totalMembers = Helper::getTotalMembers();

    // Membership Proposal
    $user->membership = Helper::getMembershipProposal($user);

    $emailerData = Helper::getEmailerData();

    // Emailer Admin
    Helper::triggerAdminEmail('New User', $emailerData);

    // Emailer User
    Helper::triggerUserEmail($user, 'New User', $emailerData);

    // Confirm Code
    Mail::to($user)->send(new Confirmation($code));

    return [
      'success' => true,
      'user' => $user
    ];
  }

  // Start Guest
  public function startGuest(Request $request) {
    $guest_key = $request->get('guest_key');

    if ($guest_key) {
      // Guest User Check
      $user = User::where('guest_key', $guest_key)->first();
      if (!$user) {
        $user = new User;
        $user->first_name = "Guest";
        $user->last_name = "User";
        $user->email = $guest_key . "@guest.com";
        $user->password = "";
        $user->confirmation_code = "";
        $user->is_guest = 1;
        $user->guest_key = $guest_key;
        $user->save();
        $user->assignRole('guest');
      }

      // Guest User Profile Check
      $profile = Profile::where('user_id', $user->id)->first();
      if (!$profile) {
        $profile = new Profile;
        $profile->user_id = $user->id;
        $profile->country_citizenship = "";
        $profile->country_residence = "";
        $profile->address = "";
        $profile->city = "";
        $profile->zip = "";
        $profile->save();
      }

      // Generate token and return
      Token::where([
        'user_id' => $user->id,
        'name' => 'User Access Token'
      ])->delete();
      $tokenResult = $user->createToken('User Access Token');

      $user->accessTokenAPI = $tokenResult->accessToken;
      $user->profile = $profile;

      return [
        'success' => true,
        'user' => $user
      ];
    }

    return ['success' => false];
  }

  // Resend Code
  public function resendCode(Request $request) {
    $user = Auth::user();

    if ($user) {
      $code = Str::random(6);

      $user->confirmation_code = $code;
      $user->save();

      // Mail
      Mail::to($user)->send(new Confirmation($code));

      return ['success' => true];
    }

    return ['success' => false];
  }

	// Get Me
	public function getMe(Request $request) {
		$user = Auth::user();

    if ($user) {
      $userId = (int) $user->id;
      $user = User::with(['profile', 'shuftipro', 'shuftiproTemp', 'permissions'])
                  ->where('id', $userId)
                  ->first();

      // Total Members
      $user->totalMembers = Helper::getTotalMembers();

      // Membership Proposal
      $user->membership = Helper::getMembershipProposal($user);

      // check grant active
      $user->grant_active = Helper::checkPendingFinalGrant($user);
      $user->grant_proposal = Helper::checkGrantProposal($user);

      //check active survey
      $user->has_survey = Helper::checkActiveSurvey($user);

      $user->makeVisible([
        "profile",
        "shuftipro",
        "shuftiproTemp",
        'last_login_ip_address',
      ]);
      if ($user->profile ?? false) {
        $user->profile->makeVisible([ 'rep', 'rep_pending','company',
          'dob',
          'country_citizenship',
          'country_residence',
          'address',
          'city',
          'zip'
        ]);
      }

      return [
        'success' => true,
        'me' => $user
      ];
    }

    return ['success' => false];
	}

	// Verify Code
	public function verifyCode(Request $request) {
		$code = $request->get('code');
		$user = Auth::user();

		if ($user && $user->confirmation_code == $code) {
      $user->email_verified = true;
      $user->email_verified_at = Date::now();
      $user->save();
      return ['success' => true];
    } else {
      return ['success' => false, 'message' => 'Confirmation code is invalid'];
    }
	}

  // Complete Review Step 2
  public function completeStepReview2(Request $request) {
    $user = Auth::user();

    if ($user) {
      $signature_request_id = $request->get('signature_request_id');
      $profile = Profile::where('user_id', $user->id)->first();

      if ($profile) {
        $profile->step_review = 1;
        $profile->signature_request_id = $signature_request_id;
        $profile->save();
      }
    }

    return ['success' => true];
  }

  public function registerAdmin(Request $request)
  {
    // Validator
    $validator = Validator::make($request->all(), [
      'email' => 'required|email',
      'password' => 'required|min:7',
      'first_name' => 'required',
      'last_name' => 'required',
      'code' => 'required'
    ]);

    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Provide all the necessary information'
      ];
    }
    $user = User::with(['profile', 'permissions'])->where('email', $request->email)->where('admin_status', 'invited')->where('confirmation_code', $request->code)->first();
    if (!$user) {
      return [
        'success' => false,
        'message' => 'There is no admin user with this email'
      ];
    }
    $user->first_name = $request->first_name;
    $user->last_name = $request->last_name;
    $user->password = bcrypt($request->password);
    $user->admin_status = 'active';
    $user->status = 'approved';
    $user->last_login_at = now();
    $user->email_verified_at = now();
    $user->can_access = 1;
    $user->email_verified = 1;
    $user->last_login_ip_address = request()->ip();
    $user->save();
    $ipHistory = new IpHistory();
    $ipHistory->user_id = $user->id;
    $ipHistory->ip_address = request()->ip();
    $ipHistory->save();
    // Generate token and return
    Token::where([
      'user_id' => $user->id,
      'name' => 'User Access Token'
    ])->delete();
    $tokenResult = $user->createToken('User Access Token');

    $user->accessTokenAPI = $tokenResult->accessToken;

    return [
      'success' => true,
      'user' => $user
    ];
  }

  public function createRfpSurvey(Request $request)
  {
    $auth = Helper::authorizeExternalAPI();
    if (!$auth) {
      return [
        'success' => false,
        'message' => 'Unauthorized'
      ];
    }
    $validator = Validator::make($request->all(), [
      'job_title' => 'required',
      'job_description' => 'required',
      'total_price' => 'required',
      'job_start_date' => 'required|date_format:Y-m-d H:i:s',
      'job_end_date' => 'required|date_format:Y-m-d H:i:s',
      'survey_hours' => 'required',
      "bids"    => "required|array|min:1",
      'bids.*.name' => 'required',
      'bids.*.forum' => 'required',
      'bids.*.email' => 'required|email',
      'bids.*.delivery_date' => 'required|date_format:Y-m-d H:i:s',
      'bids.*.amount_of_bid' => 'required',
      'bids.*.additional_note' => 'nullable',
    ]);
    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => 'Failed to launch survey. Please make sure you are passing all required data.',
        'errors' => $validator->errors()
      ];
    }
    $timeEnd = Carbon::now('UTC')->addHours($request->survey_hours);
    $survey = new Survey();
    $survey->number_response = 0;
    $survey->downvote = 0;
    $survey->time = $request->survey_hours;
    $survey->time_unit = 'hours';
    $survey->job_title = $request->job_title;
    $survey->job_description = $request->job_description;
    $survey->total_price = $request->total_price;
    $survey->end_time = $timeEnd;
    $survey->job_start_date = $request->job_start_date;
    $survey->job_end_date = $request->job_end_date;
    $survey->status = 'active';
    $survey->type = 'rfp';
    $survey->save();
    foreach($request->bids as $bid) {
        $number_response = SurveyRfpBid::where('survey_id', $survey->id)->count();
        $surveyRfpBid = new SurveyRfpBid();
        $surveyRfpBid->survey_id = $survey->id;
        $surveyRfpBid->bid =  $number_response + 1;
        $surveyRfpBid->name = $bid['name'];
        $surveyRfpBid->forum = $bid['forum'];
        $surveyRfpBid->email = $bid['email'];
        $surveyRfpBid->delivery_date = $bid['delivery_date'];
        $surveyRfpBid->amount_of_bid = $bid['amount_of_bid'];
        $surveyRfpBid->additional_note = $bid['additional_note'];
        $surveyRfpBid->save();
        $survey->number_response = $number_response + 1;
        $survey->save();
    }

    $members = User::where('is_member', 1)->where('banned', 0)->get();
    if ($members) {
      $body = "Survey RFP$survey->id has begun. Please log in to your portal and go to the surveys tab. You must rank all bids to determine which bid will win.";
      foreach ($members as $member) {
        MemberAlert::dispatch($member, 'A new survey has started for an RFP', $body);
      }
    }
    return [
      'success' => true,
      'message' => "Success. RFP Survey RFP$survey->id has been started. This survey will run for $request->survey_hours hours.",
      'survey' => $survey,
    ];
  }

  public function createSurveyBid($id, Request $request)
  {
    $auth = Helper::authorizeExternalAPI();
    if (!$auth) {
      return [
        'success' => false,
        'message' => 'Unauthorized'
      ];
    }
    $survey = Survey::where('type', 'rfp')->where('status', 'pending')->where('id', $id)->first();
    if (!$survey) {
      return [
        'success' => false,
        'message' => 'Not found survey'
      ];
    }
    $validator = Validator::make($request->all(), [
      'name' => 'required',
      'forum' => 'required',
      'email' => 'required|email',
      'delivery_date' => 'required',
      'amount_of_bid' => 'required',
      'additional_note' => 'nullable',
    ]);
    if ($validator->fails()) {
      return [
        'success' => false,
        'message' => '"Failed to launch survey. Please make sure you are passing all required data.'
      ];
    }
    $number_response = SurveyRfpBid::where('survey_id', $id)->count();
    $surveyRfpBid = new SurveyRfpBid();
    $surveyRfpBid->survey_id = $id;
    $surveyRfpBid->bid =  $number_response + 1;
    $surveyRfpBid->name = $request->name;
    $surveyRfpBid->forum = $request->forum;
    $surveyRfpBid->email = $request->email;
    $surveyRfpBid->delivery_date = $request->delivery_date;
    $surveyRfpBid->amount_of_bid = $request->amount_of_bid;
    $surveyRfpBid->additional_note = $request->additional_note;
    $surveyRfpBid->save();
    $survey->number_response = $number_response + 1;
    $survey->save();
    return [
      'success' => true,
    ];
  }

  public function getSurveyDetail($id) {
      $survey = Survey::with(['surveyRfpBids'])->where('id', $id)->where('type', 'rfp')->first();
      if(!$survey) {
        return [
            'success' => false,
            'survey' => 'Survey not found'
          ];
      }
      return [
        'success' => true,
        'survey' => $survey
      ];
  }
}
