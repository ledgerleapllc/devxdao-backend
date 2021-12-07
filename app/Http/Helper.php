<?php

namespace App\Http;

use Illuminate\Support\Facades\Mail;

use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerTriggerMember;
use App\EmailerAdmin;
use App\Proposal;
use App\Setting;
use App\User;
use App\Profile;
use App\Vote;
use App\VoteResult;
use App\Reputation;
use App\OnBoarding;
use App\Citation;
use App\FinalGrant;
use App\GrantLog;
use App\GrantTracking;
use App\SponsorCode;
use App\Signature;
use App\HellosignLog;
use App\Invoice;

use App\Mail\AdminAlert;
use App\Mail\UserAlert;

use App\Jobs\MemberAlert;
use App\Mail\ComplianceReview;
use App\Milestone;
use App\MilestoneLog;
use App\MilestoneSubmitHistory;
use App\RepHistory;
use App\Shuftipro;
use App\ShuftiproTemp;
use App\SignatureGrant;
use App\Survey;
use App\SurveyResult;
use Carbon\Carbon;
use Exception;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;

class Helper
{
  public static function authorizeExternalAPI() {
    $headers = getallheaders();
    $auth_token_header = (
      $headers['Authorization'] ??
      $headers['authorization'] ??
      ''
    );
    $auth_token = explode(' ', $auth_token_header);
    $auth_token_t = $auth_token[0];
    $auth_token = $auth_token[1] ?? '';

    if(
      $auth_token_t != 'Token' &&
      $auth_token_t != 'token'
    ) {
      return false;
    }

    if(hash_equals($auth_token, config('services.external_api.token'))) {
      return true;
    }

    return false;
  }

  // Upgrade to Voting Associate
  public static function upgradeToVotingAssociate($user)
  {
    $count = User::where('is_member', 1)->get()->count();

    $user->is_member = 1;
    $user->assignRole('member');
    $user->save();

    $user->member_no = $count + 1;
    $user->member_at = $user->updated_at;
    $user->save();
  }

  // Generate Random Two FA Code
  public static function generateTwoFACode()
  {
    $randlist = ['1', '2', '3', '4', '5', '6', '7', '8', '9', 'A', 'C', 'E', 'F', 'G', 'H', 'K', 'N', 'P', 'Q', 'R', 'T', 'W', 'X', 'Z'];
    $code1 = $randlist[rand(0, 23)];
    $code2 = $randlist[rand(0, 23)];
    $code3 = $randlist[rand(0, 23)];
    $code4 = $randlist[rand(0, 23)];
    $code5 = $randlist[rand(0, 23)];
    $code6 = $randlist[rand(0, 23)];
    $code = $code1 . $code2 . $code3 . $code4 . $code5 . $code6;
    return $code;
  }

  // Generate Random String
  public static function generateRandomString($length_of_string)
  {
    // String of all alphanumeric character
    $str_result = '123456789abcdefghijkmnopqrstuvwxyzABCDEFGHJKLMNPQRSTUVWXYZ';

    // Shufle the $str_result and returns substring
    return substr(str_shuffle($str_result), 0, $length_of_string);
  }

  // Complete Proposal
  public static function completeProposal($proposal, $check_first_proposal = true)
  {
    $proposal->status = 'completed';
    $proposal->save();
    Helper::createGrantTracking($proposal->id, 'Grant 100% complete', 'grant_completed');
    $user = User::find($proposal->user_id);

    if (
      $user && $user->hasRole('member')
    ) {
      $items = Reputation::where('proposal_id', $proposal->id)->where('type', 'Minted Pending')->get();
    } else {
      $items = Reputation::where('proposal_id', $proposal->id)->where('type', 'Minted Pending')->where('user_id', '!=', $proposal->user_id)->get();
      $proposal->type_status = 'pending';
      $proposal->save();
      if ($check_first_proposal) {
        $count = Proposal::where('user_id', $user->id)->where('status', 'completed')->count();
        if ($count == 1) {
          $user->check_first_compeleted_proposal = 1;
          $user->save();
        }
      }
    }

    if ($items) {
      foreach ($items as $item) {
        $user = User::with('profile')
          ->has('profile')
          ->where('id', $item->user_id)
          ->first();

        $value = (float) $item->pending;
        if ($value > 0) {
          $user->profile->rep_pending = (float) $user->profile->rep_pending - $value;
          if ((float) $user->profile->rep_pending < 0)
            $user->profile->rep_pending = 0;
          // $user->profile->rep = (float) $user->profile->rep + $value;
          // $user->profile->save();
          Helper::updateRepProfile($user->id, $value);
          Helper::createRepHistory($user->id, $value, $user->profile->rep, 'Minted', $item->event, $proposal->id, null , 'completeProposal');

        }

        $item->type = 'Minted';
        $item->value = (float) $item->pending;
        $item->pending = 0;
        $item->save();
      }
    }
  }

  // Start Final Grant
  public static function startFinalGrant($proposal)
  {
    $finalGrant = FinalGrant::where('proposal_id', $proposal->id)->first();
    if (!$finalGrant) {
      $finalGrant = new FinalGrant;
      $finalGrant->proposal_id = $proposal->id;
      $finalGrant->user_id = $proposal->user_id;
      $finalGrant->status = 'pending';
      $finalGrant->milestones_complete = 0;
      $finalGrant->milestones_total = count($proposal->milestones);
      $finalGrant->save();
    }
    return $finalGrant;
  }

  // Get Sponsor
  public static function getSponsor($proposal)
  {
    $sponsor_code_id = (int) $proposal->sponsor_code_id;
    if ($sponsor_code_id) {
      $codeObject = SponsorCode::find($sponsor_code_id);
      if ($codeObject) {
        $user_id = (int) $codeObject->user_id;
        $sponsor = User::with('profile')
          ->has('profile')
          ->where('id', $user_id)
          ->first();

        return $sponsor;
      }
    }

    return null;
  }

  // Run Winner Flow
  public static function runWinnerFlow($proposal, $vote, $settings)
  {
    $op = User::with('profile')->where('id', $proposal->user_id)->first();

    // $sponsor = self::getSponsor($proposal);
    $sponsor = null;

    $for_value = (float) $vote->for_value;
    $against_value = (float) $vote->against_value;

    // Get For Voters
    $itemsFor = VoteResult::where('proposal_id', $vote->proposal_id)
      ->where('vote_id', $vote->id)
      ->where('type', 'for')
      ->get();

    // Get Against Voters
    $itemsAgainst = VoteResult::where('proposal_id', $vote->proposal_id)
      ->where('vote_id', $vote->id)
      ->where('type', 'against')
      ->get();

    // Get Winning Side Voters
    $items = $itemsFor;

    // Minted Pending Rep
    $total_minted_pending = (float) $proposal->total_grant * (float) $settings['minted_ratio'];
    $total_minted_pending = round($total_minted_pending, 2);

    $op_minted_pending = $total_minted_pending * (float)((float) $settings['op_percentage'] / 100);
    $op_minted_pending = round($op_minted_pending, 2);

    $minted_pending = $total_minted_pending - $op_minted_pending;
    $minted_pending = round($minted_pending, 2);
    if ($minted_pending < 0) $minted_pending = 0;

    $op_rate = (float) $proposal->rep / ($for_value + (float) $proposal->rep);
    $op_extra = (float) $against_value * $op_rate;
    $op_extra = round($op_extra);

    // Split Algorithm - Grant Has Minted Pending
    foreach ($items as $item) {
      $value = (float) $item->value;
      $rate = (float) $value / ($for_value + (float) $proposal->rep);

      $extra = (float) $against_value * $rate;
      $extra = round($extra, 2);

      $extra_minted = (float) $minted_pending * $rate;
      $extra_minted = round($extra_minted, 2);

      $rep = $value + $extra;
      $rep = (float) round($rep, 2);

      $voter = User::with('profile')->where('id', $item->user_id)->first();
      if ($voter && isset($voter->profile)) {
        // $voter->profile->rep = (float) $voter->profile->rep + $rep;
        Helper::updateRepProfile($voter->id, $rep);
        if (
          $proposal->type == "grant" &&
          $vote->content_type != "milestone"
        ) {
          $voter->profile->rep_pending = (float) $voter->profile->rep_pending + $extra_minted;
        }
        $voter->profile->save();
        Helper::createRepHistory($item->user_id, $rep,  $voter->profile->rep,'Gained', 'Proposal Vote Result', $proposal->id, $vote->id, 'runWinnerFlow');

        // Stake Returned
        if ($value != 0) {
          Reputation::where('user_id', $voter->id)
            ->where('proposal_id', $vote->proposal_id)
            ->where('type', 'Staked')
            //->where('event', 'Proposal Vote')
            ->where('vote_id', $vote->id)
            ->delete();
        }

        // Gained
        if ($extra != 0) {
          $reputation = new Reputation;
          $reputation->user_id = $voter->id;
          $reputation->proposal_id = $vote->proposal_id;
          $reputation->vote_id = $vote->id;
          $reputation->value = $extra;
          $reputation->event = "Proposal Vote Result";
          $reputation->type = "Gained";
          $reputation->save();
        }

        // Minted Pending
        if (
          $extra_minted != 0 &&
          $proposal->type == "grant" &&
          $vote->content_type != "milestone"
        ) {
          $reputation = new Reputation;
          $reputation->user_id = $voter->id;
          $reputation->proposal_id = $vote->proposal_id;
          $reputation->vote_id = $vote->id;
          $reputation->pending = $extra_minted;
          $reputation->event = "Proposal Vote Result";
          $reputation->type = "Minted Pending";
          $reputation->save();
        }
      }
    }

    // Stake Lost
    foreach ($itemsAgainst as $item) {
      $voter = User::with('profile')->where('id', $item->user_id)->first();

      if ($voter && isset($voter->profile)) {
        $reputation = Reputation::where('user_id', $voter->id)
          ->where('proposal_id', $vote->proposal_id)
          ->where('type', 'Staked')
          ->where('event', 'Proposal Vote')
          ->where('vote_id', $vote->id)
          ->first();
        if ($reputation) {
          $value = (float) $reputation->staked;

          if ($value != 0) {
            $reputationNew = new Reputation;
            $reputationNew->user_id = $voter->id;
            $reputationNew->proposal_id = $vote->proposal_id;
            $reputationNew->vote_id = $vote->id;
            $reputationNew->value = $value;
            $reputationNew->type = "Stake Lost";
            $reputationNew->event = "Proposal Vote Result";
            $reputationNew->save();
          }

          $reputation->delete();
        }
      }
    }

    // OP
    if ($op && isset($op->profile)) {
      $citations = Citation::with([
        'repProposal',
        'repProposal.user',
        'repProposal.user.profile'
      ])
        ->has('repProposal')
        ->has('repProposal.user')
        ->has('repProposal.user.profile')
        ->where('proposal_id', $proposal->id)
        ->get();

      $percentage = 100;
      if (
        $citations &&
        $proposal->type == "grant" &&
        $vote->content_type != "milestone"
      ) {
        foreach ($citations as $citation) {
          $p = (int) $citation->percentage;
          $percentage -= $p;

          $pending = (float)($op_minted_pending * $p / 100);
          $pending = round($pending, 2);

          if ($pending > 0) {
            $current = (float) $citation->repProposal->user->profile->rep_pending;

            $citation->repProposal->user->profile->rep_pending = $pending + $current;
            $citation->repProposal->user->profile->save();

            $reputation = new Reputation;
            $reputation->user_id = $citation->repProposal->user->id;
            $reputation->proposal_id = $vote->proposal_id;
            $reputation->vote_id = $vote->id;
            $reputation->pending = $pending;
            $reputation->event = "Proposal Vote Result - Citation";
            $reputation->type = "Minted Pending";
            $reputation->save();
          }
        }
      }
      if ($percentage < 0) $percentage = 0;

      $op_minted_pending = (float)($op_minted_pending * $percentage / 100);
      $op_minted_pending = round($op_minted_pending, 2);

      // $op->profile->rep =
      //   (float) $op->profile->rep +
      //   (float) $op_extra +
      //   (float) $proposal->rep;
      Helper::updateRepProfile($op->id, (float) $op_extra + (float) $proposal->rep);
      if ($proposal->type == "grant" && $vote->content_type != "milestone") {
        if ($sponsor) {
          $sponsor->profile->rep_pending = (float) $sponsor->profile->rep_pending + $op_minted_pending;
          $sponsor->profile->save();
        } else {
          $op->profile->rep_pending = (float) $op->profile->rep_pending + $op_minted_pending;
        }
      }

      $op->profile->save();
      Helper::createRepHistory($op->id, (float) $op_extra + (float) $proposal->rep, $op->profile->rep,'Gained', 'Proposal Vote Result', $proposal->id, $vote->id, 'runWinnerFlow2');

      // Stake Returned
      if ((float) $proposal->rep != 0) {
        Reputation::where('user_id', $op->id)
          ->where('proposal_id', $vote->proposal_id)
          ->where('type', 'Staked')
          ->delete();
      }

      // Gained
      if ($op_extra != 0) {
        $reputation = new Reputation;
        $reputation->user_id = $op->id;
        $reputation->proposal_id = $vote->proposal_id;
        $reputation->vote_id = $vote->id;
        $reputation->value = $op_extra;
        $reputation->event = "Proposal Vote Result - OP";
        $reputation->type = "Gained";
        $reputation->save();
      }

      // Minted Pending
      if (
        $op_minted_pending != 0 &&
        $proposal->type == "grant" &&
        $vote->content_type != "milestone"
      ) {
        if ($sponsor) {
          $reputation = new Reputation;
          $reputation->user_id = $sponsor->id;
          $reputation->proposal_id = $vote->proposal_id;
          $reputation->vote_id = $vote->id;
          $reputation->pending = $op_minted_pending;
          $reputation->event = "Proposal Vote Result - Sponsor";
          $reputation->type = "Minted Pending";
          $reputation->save();
        } else {
          $reputation = new Reputation;
          $reputation->user_id = $op->id;
          $reputation->proposal_id = $vote->proposal_id;
          $reputation->vote_id = $vote->id;
          $reputation->pending = $op_minted_pending;
          $reputation->event = "Proposal Vote Result - OP";
          $reputation->type = "Minted Pending";
          $reputation->save();
        }
      }
    }
  }

  // Run Loser Flow
  public static function runLoserFlow($proposal, $vote, $settings)
  {
    $op = User::with('profile')->where('id', $proposal->user_id)->first();

    $for_value = (float) $vote->for_value;
    $against_value = (float) $vote->against_value;

    // Get For Voters
    $itemsFor = VoteResult::where('proposal_id', $vote->proposal_id)
      ->where('vote_id', $vote->id)
      ->where('type', 'for')
      ->get();

    // Get Against Voters
    $itemsAgainst = VoteResult::where('proposal_id', $vote->proposal_id)
      ->where('vote_id', $vote->id)
      ->where('type', 'against')
      ->get();

    // Get Losing Side Voters
    $items = $itemsAgainst;

    // Split Algorithm - Has No Minted Pending
    foreach ($items as $item) {
      $value = (float) $item->value;
      $rate = (float) $value / $against_value;

      $extra = (float) ($for_value + (float) $proposal->rep) * $rate;
      $extra = round($extra, 2);

      $rep = $value + $extra;
      $rep = (float) round($rep, 2);

      $voter = User::with('profile')->where('id', $item->user_id)->first();
      if ($voter && isset($voter->profile)) {
        // $voter->profile->rep = (float) $voter->profile->rep + $rep;
        // $voter->profile->save();
        Helper::updateRepProfile($voter->id, $rep);
        Helper::createRepHistory($item->user_id, $rep, $voter->profile->rep, 'Gained', 'Proposal Vote Result',$proposal->id, $vote->id, 'runLoserFlow');

        // Stake Returned
        if ($value != 0) {
          Reputation::where('user_id', $voter->id)
            ->where('proposal_id', $vote->proposal_id)
            ->where('vote_id', $vote->id)
            ->where('type', 'Staked')
            //->where('event', 'Proposal Vote')
            ->delete();
        }

        // Gained
        if ($extra != 0) {
          $reputation = new Reputation;
          $reputation->user_id = $voter->id;
          $reputation->proposal_id = $vote->proposal_id;
          $reputation->vote_id = $vote->id;
          $reputation->value = $extra;
          $reputation->event = "Proposal Vote Result";
          $reputation->type = "Gained";
          $reputation->save();
        }
      }
    }

    // Stake Lost
    foreach ($itemsFor as $item) {
      $voter = User::with('profile')->where('id', $item->user_id)->first();

      if ($voter && isset($voter->profile)) {
        $reputation = Reputation::where('user_id', $voter->id)
          ->where('proposal_id', $vote->proposal_id)
          ->where('vote_id', $vote->id)
          ->where('event', 'Proposal Vote')
          ->where('type', 'Staked')
          ->first();
        if ($reputation) {
          $value = (float) $reputation->staked;

          if ($value != 0) {
            $reputationNew = new Reputation;
            $reputationNew->user_id = $voter->id;
            $reputationNew->proposal_id = $vote->proposal_id;
            $reputationNew->vote_id = $vote->id;
            $reputationNew->value = $value;
            $reputationNew->type = "Stake Lost";
            $reputationNew->event = "Proposal Vote Result";
            $reputationNew->save();
          }

          $reputation->delete();
        }
      }
    }

    // OP
    if ($op && isset($op->profile)) {
      // Create Reputation Track
      $reputation = Reputation::where('user_id', $op->id)
        ->where('proposal_id', $vote->proposal_id)
        ->where('type', 'Staked')
        ->first();
      if ($reputation) {
        $value = (float) $reputation->staked;

        if ($value != 0) {
          $reputationNew = new Reputation;
          $reputationNew->user_id = $op->id;
          $reputationNew->proposal_id = $vote->proposal_id;
          $reputationNew->vote_id = $vote->id;
          $reputationNew->value = $value;
          $reputationNew->type = "Stake Lost";
          $reputationNew->event = "Proposal Vote Result - OP";
          $reputationNew->save();
        }

        $reputation->delete();
      }
    }
  }

  // Give Vote Rep Back
  public static function clearVoters($vote)
  {
    if ($vote->type != "formal") return false;

    $items = VoteResult::where('proposal_id', $vote->proposal_id)
      ->where('vote_id', $vote->id)
      ->get();

    foreach ($items as $item) {
      $userId = (int) $item->user_id;
      $value = (float) $item->value;

      $profile = Profile::where('user_id', $userId)->first();
      if ($profile) {
        // $profile->rep = (float) $profile->rep + $value;
        // $profile->save();
        Helper::updateRepProfile($userId, $value);
        Helper::createRepHistory($userId,  $value,  $profile->rep, 'Gained', 'Proposal Vote Result', $vote->proposal_id, $vote->id, 'clearVoters');

        Reputation::where('user_id', $userId)
          ->where('proposal_id', $vote->proposal_id)
          ->where('vote_id', $vote->id)
          ->where('type', 'Staked')
          ->delete();
      }
    }
  }

  // Send Admin Email
  public static function triggerAdminEmail($title, $emailerData, $proposal = null, $vote = null, $user = null, $total_rep = null)
  {
    if (count($emailerData['admins'] ?? [])) {
      $item = $emailerData['triggerAdmin'][$title] ?? null;
      if ($item) {
        $content = $item['content'];
        $subject = $item['subject'];
        if ($proposal) {
          $content = str_replace('[title]', $proposal->title, $content);
          $content = str_replace('[number]', $proposal->id, $content);
          $content = str_replace('[proposal title]', $proposal->title, $content);
          $content = str_replace('[proposal number]', $proposal->id, $content);
        }
        if ($vote) {
          $content = str_replace('[voteType]', $vote->type, $content);
          $content = str_replace('[voteContentType]', $vote->content_type, $content);
          $content = str_replace('[voteId]', $vote->id, $content);
        }
        if ($user) {
          $name =  $user->first_name . ' ' .  $user->last_name;
          $content = str_replace('[first_name]', $user->first_name, $content);
          $content = str_replace('[last_name]', $user->last_name, $content);

          $subject = str_replace('[name]', $name, $subject);
        }
        if ($total_rep) {
          $content = str_replace('[total rep]', $total_rep, $content);
        }
        Mail::to($emailerData['admins'])->send(new AdminAlert($subject, $content));
      }
    }
  }

  // Send User Email
  public static function triggerUserEmail($to, $title, $emailerData, $proposal = null, $vote = null, $user = null, $extra = [], $milestone = null, $denyReason = null)
  {
    $item = $emailerData['triggerUser'][$title] ?? null;
    if ($item) {
      $subject = $item['subject'];
      $content = $item['content'];
      if ($proposal) {
        $content = str_replace('[title]', $proposal->title, $content);
        $content = str_replace('[proposalId]', $proposal->id, $content);
        $subject = str_replace('[proposalId]', $proposal->id, $subject);
      }
      if (isset($extra['pendingChangesCount'])) {
        $content = str_replace('[pendingChangesCount]', $extra['pendingChangesCount'], $content);
      }
      if (isset($extra['url'])) {
        $content = str_replace('[url]', $extra['url'], $content);
      }
      if ($vote) {
        $content = str_replace('[voteType]', $vote->type, $content);
        $content = str_replace('[voteContentType]', $vote->content_type, $content);
        $content = str_replace('[voteId]', $vote->id, $content);
      }
      if ($user) {
        $content = str_replace('[first_name]', $user->first_name, $content);
        $content = str_replace('[last_name]', $user->last_name, $content);
      }
      if ($milestone) {
        $content = str_replace('[milestoneId]', $milestone->id, $content);
      }
      if ($denyReason) {
        $content = str_replace('[deny reason]', $denyReason, $content);
      }
      Mail::to($to)->send(new UserAlert($subject, $content));
    }
  }

  // Send Member Email
  public static function triggerMemberEmail($title, $emailerData, $proposal = null, $vote = null, $discusstions = null, $votingToday = null,  $noQuorumVotes = null)
  {
    $item = $emailerData['triggerMember'][$title] ?? null;
    if ($item) {
      $subject = $item['subject'];
      $content = $item['content'];

      if ($proposal) {
        $subject = str_replace('[type]', $proposal->type, $subject);
        $content = str_replace('[title]', $proposal->title, $content);
        $content = str_replace('[content]', $proposal->short_description, $content);
      }

      if ($vote) {
        $subject = str_replace('[voteContentType]', $vote->content_type, $subject);
      }
      if ($discusstions) {
        $titleDiscussion = '';
        foreach ($discusstions as  $value) {
          $titleDiscussion .= "- $value->title <br>";
        }
        $content = str_replace('[Proposal Tittle Discussions]', $titleDiscussion, $content);
      }
      if ($votingToday) {
        $titleVoting = '';
        foreach ($votingToday as  $value) {
          $titleVoting .= "- $value->title - $value->type $value->content_type Vote <br>";
        }
        $content = str_replace('[Proposal started vote today]', $titleVoting, $content);
      }
      $now = Carbon::parse('UTC');
      if ($noQuorumVotes) {
        $titleNoQuorum = '';
        foreach ($noQuorumVotes as  $value) {
          $titleNoQuorum .= "- $value->title <br>";
        }
        $content = str_replace('[Proposal not reached quorum]', $titleNoQuorum, $content);
      }
      $members = User::where('is_member', 1)
        ->where('banned', 0)
        ->get();

      if ($members) {
        foreach ($members as $member) {
          MemberAlert::dispatch($member, $subject, $content);
        }
      }
    }
  }

  // Send Membership Hellosign Request
  public static function sendMembershipHellosign($user, $proposal, $settings)
  {
    $client = new \HelloSign\Client(config('services.hellosign.api_key'));

    $request = new \HelloSign\TemplateSignatureRequest;
    // $request->enableTestMode();

    $request->setTemplateId(config('services.hellosign.membership_template_id'));
    $request->setSubject('Membership Amendment');
    $request->setClientId(config('services.hellosign.client_id'));

    // OP Signer
    $request->setSigner(
      'OP',
      $user->email,
      $user->first_name . ' ' . $user->last_name
    );

    // if (isset($settings['coo_email']) && $settings['coo_email']) {
    //   // COO Signer
    //   $request->setSigner(
    //     'COO',
    //     $settings['coo_email'],
    //     'COO'
    //   );
    // }

    $response = $client->sendTemplateSignatureRequest($request);

    // Log when request to Hellosign
    $signatures = SignatureGrant::where('proposal_id',  $proposal->id)->get();
    Helper::createHellosignLogging(
      $user->id,
      'Send Signature Request',
      'send_signature_request',
      json_encode([
        'Subject' => 'Membership Amendment',
        'Signatures' => $signatures->only(['email', 'role', 'signed']),
      ])
    );

    // Void the prior request when a new request is sent
    // if ($proposal->membership_signature_request_id) {
    //   $client->cancelSignatureRequest($proposal->membership_signature_request_id);
    // }

    $signature_request_id = $response->getId();

    $proposal->membership_signature_request_id = $signature_request_id;
    $proposal->save();

    return $response;
  }

  // Send Onboarding Hellosign Request 1
  public static function sendOnboardingHellosign1($user, $proposal, $settings)
  {
    $client = new \HelloSign\Client(config('services.hellosign.api_key'));

    $request = new \HelloSign\TemplateSignatureRequest;
    // $request->enableTestMode();

    $request->setTemplateId('433b94ed3747e2d7a0831e3fc0a6bd0ab33f7d78');
    $request->setSubject('Grant Agreement');
    if ($proposal->pdf) {
      $urlFile = public_path() . $proposal->pdf;
      $request->addFile($urlFile);
    }

    $request->setCustomFieldValue('FullName', $user->first_name . ' ' . $user->last_name);

    $initialA = substr($user->first_name, 0, 1);
    $initialB = substr($user->last_name, 0, 1);
    $request->setCustomFieldValue('Initial', $initialA . ' ' . $initialB);
    $request->setCustomFieldValue('ProjectTitle', $proposal->title);
    $request->setCustomFieldValue('ProjectDescription', $proposal->short_description);
    $request->setCustomFieldValue('ProposalId', $proposal->id);
    $request->setCustomFieldValue('TotalGrant', number_format($proposal->total_grant, 2));
    $request->setClientId(config('services.hellosign.client_id'));

    $request->setSigner(
      'OP',
      $user->email,
      $user->first_name . ' ' . $user->last_name
    );

    // OP Signer
    $signature = Signature::where('role', 'OP')
      ->where('email', $user->email)
      ->first();
    if (!$signature) $signature = new Signature;
    $signature->proposal_id = $proposal->id;
    $signature->name = $user->first_name . ' ' . $user->last_name;
    $signature->email = $user->email;
    $signature->role = 'OP';
    $signature->signed = 0;
    $signature->save();

    // if (isset($settings['coo_email']) && $settings['coo_email']) {
    //   $request->setSigner(
    //     'COO',
    //     $settings['coo_email'],
    //     'COO'
    //   );
    //   // COO Signer
    //   $signature = Signature::where('role', 'COO')
    //                           ->where('email', $settings['coo_email'])
    //                           ->first();
    //   if (!$signature) $signature = new Signature;
    //   $signature->proposal_id = $proposal->id;
    //   $signature->name = 'COO';
    //   $signature->email = $settings['coo_email'];
    //   $signature->role = 'COO';
    //   $signature->signed = 0;
    //   $signature->save();
    // }

    // if (isset($settings['cfo_email']) && $settings['cfo_email']) {
    //   $request->setSigner(
    //     'CFO',
    //     $settings['cfo_email'],
    //     'CFO'
    //   );
    //   // CFO Signer
    //   $signature = Signature::where('role', 'CFO')
    //                           ->where('email', $settings['cfo_email'])
    //                           ->first();
    //   if (!$signature) $signature = new Signature;
    //   $signature->proposal_id = $proposal->id;
    //   $signature->name = 'CFO';
    //   $signature->email = $settings['cfo_email'];
    //   $signature->role = 'CFO';
    //   $signature->signed = 0;
    //   $signature->save();
    // }

    $response = $client->sendTemplateSignatureRequest($request);

    // Log when request to Hellosign
    $signatures = SignatureGrant::where('proposal_id',  $proposal->id)->get();
    Helper::createHellosignLogging(
      $user->id,
      'Send Signature Request',
      'send_signature_request',
      json_encode([
        'Subject' => 'Grant Agreement',
        'Signatures' => $signatures->only(['email', 'role', 'signed']),
      ])
    );

    // Void the prior request when a new request is sent
    // if ($proposal->signature_request_id) {
    //   $client->cancelSignatureRequest($proposal->signature_request_id);
    // }

    $signature_request_id = $response->getId();

    $proposal->signature_request_id = $signature_request_id;
    $proposal->save();

    return $response;
  }

  // Start Onboarding
  public static function startOnboarding($proposal, $vote, $status = 'pending')
  {
    Log::info("Start Onboarding of proposal $proposal->id");
    if ($vote->type != "informal") return null;

    $settings = self::getSettings();
    $onboarding = OnBoarding::where('proposal_id', $proposal->id)
    ->where('vote_id', $vote->id)
      ->first();
    if (!$onboarding) {
      $token = Str::random(50);
      $onboarding = new OnBoarding();
      $onboarding->proposal_id = $proposal->id;
      $onboarding->vote_id = $vote->id;
      $onboarding->user_id = $proposal->user_id;
      $onboarding->status = $status;
      $onboarding->compliance_token = $token;
      $onboarding->admin_email = $settings['compliance_admin'] ?? '';
      $onboarding->compliance_status = 'pending';
      $onboarding->save();
      try {
        if ($settings['compliance_admin']) {
          Log::info("Send mail compliance review of proposal $proposal->id");
          $title = "Proposal $proposal->id needs a compliance review";
          $public_url = config('app.fe_url') . "/public-proposals/$proposal->id";
          $approve_url = config('app.fe_url') . "/compliance-approve-grant/$proposal->id?token=$token";
          $deny_url = config('app.fe_url') . "/compliance-deny-grant/$proposal->id?token=$token";
          Mail::to($settings['compliance_admin'])->send(new ComplianceReview($title, $proposal, $public_url, $approve_url, $deny_url));
        }
      } catch (Exception $e) {
        Log::info($e->getMessage());
      }
      return $onboarding;
    }

    return null;
  }

  // Start Formal Vote
  public static function startFormalVote($vote)
  {
    if ($vote->type != "informal") return false;

    $proposal_id = (int) $vote->proposal_id;
    $temp = Vote::where('proposal_id', $proposal_id)
      ->where('type', 'formal')
      ->where('content_type', '!=', 'milestone')
      ->first();

    if (!$temp) {
      $temp = new Vote;
      $temp->proposal_id = $proposal_id;
      $temp->type = 'formal';
      $temp->content_type = $vote->content_type;
      $temp->status = 'active';
      $temp->save();

      // Save Formal Voting ID
      $vote->formal_vote_id = (int) $temp->id;
      $vote->save();
      self::createGrantTracking($vote->proposal_id, "Entered Formal vote", 'entered_formal_vote');
      return $temp;
    }

    return null;
  }

  // Get Vote Result
  public static function getVoteResult($proposal, $vote, $settings)
  {
    $pass_rate = 0;
    if ($vote->content_type == "grant")
      $pass_rate = $settings["pass_rate"] ?? 0;
    else if ($vote->content_type == "simple")
      $pass_rate = $settings["pass_rate_simple"] ?? 0;
    else if ($vote->content_type == "milestone")
      $pass_rate = $settings["pass_rate_milestone"] ?? 0;

    $pass_rate = (float) $pass_rate;

    $for_value = (float) $vote->for_value;
    $against_value = (float) $vote->against_value;
    $total = $for_value + $against_value;

    $standard = (float)($total * $pass_rate / 100);

    if ($for_value > $standard) return "success";
    return "fail";
  }

  // Get Total Members
  public static function getTotalMembers()
  {
    $totalMembers = User::where('is_member', 1)
      ->where('banned', 0)
      ->where('can_access', 1)
      ->get()
      ->count();
    return $totalMembers;
  }

  // Get Total Members
  public static function getTotalMemberProposal($proposalId)
  {
    $proposal = Proposal::where('id', $proposalId)->first();
    $vote = Vote::where('proposal_id', $proposalId)->orderBy('created_at', 'desc')->first();
    if (!$vote) {
      return self::getTotalMembers();
    }
    if ($vote->type == 'informal') {
      return self::getTotalMembers();
    }
    if ($vote->type == 'formal' && $vote->content_type == 'milestone') {
      $result = Vote::where('proposal_id', $proposalId)->where('type', 'informal')
        ->where('content_type', 'milestone')->orderBy('created_at', 'desc')->first();
      return $result->result_count ?? self::getTotalMembers();
    }
    if ($vote->type == 'formal' && $vote->content_type == 'grant') {
      $result = Vote::where('proposal_id', $proposalId)->where('type', 'informal')
        ->where('content_type', 'grant')->orderBy('created_at', 'desc')->first();
      return $result->result_count ?? self::getTotalMembers();
    }
    if ($vote->type == 'formal' && $vote->content_type == 'simple') {
      $result = Vote::where('proposal_id', $proposalId)->where('type', 'informal')
        ->where('content_type', 'simple')->orderBy('created_at', 'desc')->first();
      return $result->result_count ?? self::getTotalMembers();
    }
    return self::getTotalMembers();
  }

  // Get Settings
  public static function getSettings()
  {
    // Get Settings
    $settings = [];
    $items = Setting::get();
    if ($items) {
      foreach ($items as $item) {
        $settings[$item->name] = $item->value;
      }
    }
    return $settings;
  }

  // Get Membership Proposal
  public static function getMembershipProposal($user)
  {
    return null;
  }

  // Get Emailer Data
  public static function getEmailerData()
  {
    $data = [
      'admins' => [],
      'triggerAdmin' => [],
      'triggerUser' => [],
      'triggerMember' => []
    ];

    $admins = EmailerAdmin::where('id', '>', 0)
      ->orderBy('email', 'asc')->get();
    $triggerAdmin = EmailerTriggerAdmin::where('id', '>', 0)
      ->orderBy('id', 'asc')
      ->get();
    $triggerUser = EmailerTriggerUser::where('id', '>', 0)
      ->orderBy('id', 'asc')
      ->get();
    $triggerMember = EmailerTriggerMember::where('id', '>', 0)
      ->orderBy('id', 'asc')
      ->get();

    if ($admins && count($admins)) {
      foreach ($admins as $admin) {
        $data['admins'][] = $admin->email;
      }
    }

    if ($triggerAdmin && count($triggerAdmin)) {
      foreach ($triggerAdmin as $item) {
        if ((int) $item->enabled)
          $data['triggerAdmin'][$item->title] = $item;
        else
          $data['triggerAdmin'][$item->title] = null;
      }
    }

    if ($triggerUser && count($triggerUser)) {
      foreach ($triggerUser as $item) {
        if ((int) $item->enabled)
          $data['triggerUser'][$item->title] = $item;
        else
          $data['triggerUser'][$item->title] = null;
      }
    }

    if ($triggerMember && count($triggerMember)) {
      foreach ($triggerMember as $item) {
        if ((int) $item->enabled)
          $data['triggerMember'][$item->title] = $item;
        else
          $data['triggerMember'][$item->title] = null;
      }
    }

    return $data;
  }

  // Send grant Hellosign Request 1
  public static function sendGrantHellosign($user, $proposal, $settings)
  {
    $client = new \HelloSign\Client(config('services.hellosign.api_key'));
    $profile = Profile::where('user_id', $user->id)->first();
    $request = new \HelloSign\TemplateSignatureRequest;
    // $request->enableTestMode();
    $finalGrant = FinalGrant::where('proposal_id', $proposal->id)->first();
    $request->setTemplateId('a77ecd6d708736d6ae0e2d8d35e5e54938c83436');
    $subject = "Grant $proposal->id - Sign to activate DEVxDAO grant!";
    $request->setSubject($subject);
    if ($proposal->pdf) {
      $urlFile = public_path() . $proposal->pdf;
      if (file_exists($urlFile)) {
        $request->addFile($urlFile);
      }
    }
    $request->setCustomFieldValue('FullName', $user->first_name . ' ' . $user->last_name);

    $shuftipro = Shuftipro::where('user_id', $user->id)->first();

    $initialA = substr($user->first_name, 0, 1);
    $initialB = substr($user->last_name, 0, 1);
    $fullName = implode(' ', array_filter([$initialA, $initialB]));
    $fullAddress = implode(' ', array_filter([$profile->address, $profile->address2, $profile->city, $profile->zip]));
    $shuftiproData = json_decode($shuftipro->data);
    $shuftiproFullName = $shuftiproAddress = $shuftiproCountry = '';
    if (isset($shuftipro->address_result) && $shuftipro->address_result) {
      $shuftiproFullName = $shuftiproData->address_document->name->full_name ?? '';
      $shuftiproAddress = $shuftiproData->address_document->full_address ?? '';
      $shuftiproCountry = $shuftiproData->address_document->country ?? '';
    } else {
      $shuftiproAddress = $shuftiproData->profile_address ?? '';
      $shuftiproCountry = $shuftiproData->country_company ?? '';
    }

    $request->setCustomFieldValue('Initial', $shuftiproFullName ? $shuftiproFullName : $fullName);
    $request->setCustomFieldValue('ProjectTitle', $proposal->title);
    $request->setCustomFieldValue('ProjectDescription', $proposal->short_description);
    $request->setCustomFieldValue('ProposalId', $proposal->id);
    $request->setCustomFieldValue('TotalGrant', number_format($proposal->total_grant, 2));
    $request->setCustomFieldValue('Address', $shuftiproAddress ? $shuftiproAddress : $fullAddress);
    $request->setCustomFieldValue('Entity', $proposal->name_entity);
    $request->setCustomFieldValue('From', $shuftiproCountry ? $shuftiproCountry : $proposal->entity_country);
    if ($shuftipro) {
      $request->setCustomFieldValue('ShuftiId', $shuftipro->reference_id);
    }
    $request->setClientId(config('services.hellosign.client_id'));

    $request->setSigner(
      'OP',
      $user->email,
      $user->first_name . ' ' . $user->last_name
    );

    // OP Signer
    SignatureGrant::where('role', 'OP')
      ->where('proposal_id',  $proposal->id)
      ->delete();
    $signature = new SignatureGrant;
    $signature->proposal_id = $proposal->id;
    $signature->name = $user->first_name . ' ' . $user->last_name;
    $signature->email = $user->email;
    $signature->role = 'OP';
    $signature->signed = 0;
    $signature->save();

    if (isset($settings['cfo_email']) && $settings['cfo_email']) {
      $request->setSigner(
        'CFO',
        $settings['cfo_email'],
        'CFO'
      );
      // CFO Signer
      SignatureGrant::where('role', 'CFO')
        ->where('proposal_id',  $proposal->id)
        ->delete();
      $signature = new SignatureGrant;
      $signature->proposal_id = $proposal->id;
      $signature->name = 'CFO';
      $signature->email = $settings['cfo_email'];
      $signature->role = 'CFO';
      $signature->signed = 0;
      $signature->save();
    }

    if (isset($settings['board_member_email']) && $settings['board_member_email']) {
      $request->setSigner(
        'BM',
        $settings['board_member_email'],
        'BM'
      );
      // board_member email Signer
      SignatureGrant::where('role', 'BM')
        ->where('proposal_id',  $proposal->id)
        ->delete();
      $signature = new SignatureGrant;
      $signature->proposal_id = $proposal->id;
      $signature->name = 'BM';
      $signature->email = $settings['board_member_email'];
      $signature->role = 'BM';
      $signature->signed = 0;
      $signature->save();
    }

    $response = $client->sendTemplateSignatureRequest($request);

    // Log when request to Hellosign
    $signatures = SignatureGrant::where('proposal_id',  $proposal->id)->get();
    Helper::createHellosignLogging(
      $user->id,
      'Send Signature Request',
      'send_signature_request',
      json_encode([
        'Subject' => "Grant $proposal->id - Sign to activate DEVxDAO grant!",
        'Signatures' => $signatures->only(['email', 'role', 'signed']),
      ])
    );

    // Void the prior request when a new request is sent
    // if ($proposal->signature_grant_request_id) {
    //   $client->cancelSignatureRequest($proposal->signature_grant_request_id);
    // }

    $signature_request_id = $response->getId();

    $proposal->signature_grant_request_id = $signature_request_id;
    $proposal->save();

    Helper::createGrantLogging([
      'proposal_id' => $proposal->id,
      'final_grant_id' => $finalGrant->id,
      'user_id' => null,
      'email' => null,
      'role' => 'system',
      'type' => 'sent_doc',
    ]);

    return $response;
  }

  public static function checkPendingFinalGrant($user)
  {
    $count = FinalGrant::where('user_id', $user->id)->where('status', 'active')->count();
    return $count > 0 ? true : false;
  }

  public static function checkGrantProposal($user)
  {
    $count = Proposal::where('user_id', $user->id)->where('type', 'grant')->count();
    return $count > 0 ? true : false;
  }

  public static function createMilestoneLog($milestone_id, $email, $user_id, $role, $action)
  {
    $milestoneLog = new MilestoneLog();
    $milestoneLog->milestone_id = $milestone_id;
    $milestoneLog->email = $email;
    $milestoneLog->user_id = $user_id;
    $milestoneLog->role = $role;
    $milestoneLog->action = $action;
    $milestoneLog->save();
  }

  public static function queryGetMilestone($email, $proposalId, $hideCompletedGrants, $startDate, $endDate, $search)
  {
    $query =  Milestone::with(['votes', 'milestones'])
      ->join('proposal', 'proposal.id', '=', 'milestone.proposal_id')
      ->join('users', 'users.id', '=', 'proposal.user_id')
      ->leftJoin('milestone_review', 'milestone.id', '=', 'milestone_review.milestone_id')
      ->leftJoin('final_grant', 'proposal.id', '=', 'final_grant.proposal_id')
      ->where('final_grant.status', '!=', 'pending')
      ->where(function ($query) use ($email, $proposalId, $hideCompletedGrants,  $startDate, $endDate, $search) {
        if ($email) {
          $query->where(
            'users.email',
            '=',
            $email
          );
        }
        if ($proposalId) {
          $query->where('milestone.proposal_id', '=', $proposalId);
        }
        if ($hideCompletedGrants == 1) {
          $query->where('final_grant.status', '=', 'active');
        }
        if ($startDate) {
          $query->whereRaw("IF (milestone.submitted_time IS NOT NULL, date(milestone.submitted_time), STR_TO_DATE(milestone.deadline,'%Y-%m-%d')) >= '$startDate' ");
        }
        if ($endDate) {
          $query->whereRaw("IF (milestone.submitted_time IS NOT NULL, date(milestone.submitted_time), STR_TO_DATE(milestone.deadline,'%Y-%m-%d')) <= '$endDate' ");
        }
        if ($search) {
          $query->where('proposal.id', 'like', '%' . $search . '%')
            ->orWhere('proposal.title', 'like', '%' . $search . '%')
            ->orWhere('users.email', 'like', '%' . $search . '%');
        }
      });
    return $query;
  }

  public static function getResultMilestone($milestone)
  {
    $milestones = Milestone::where('proposal_id', $milestone->proposal_id)->orderBy('id', 'asc')->get();
    $total = count($milestones);
    $position = 1;
    foreach ($milestones as $key => $value) {
      if ($value->id == $milestone->id) {
        $position =  $key + 1;
        break;
      }
    }
    return [
      'Milestone' => '  ' . $position . ' / ' . $total,
      'Milestone Number' => '  ' . $milestone->proposal_id . ' - ' . $position,
    ];
  }

  public static function getVoteMilestone($milestone)
  {
    $vote = Vote::where('milestone_id', $milestone->id)->orderBy('created_at', 'desc')->first();
    if ($vote) {
      if ($vote->result == 'success') {
        return 'Pass';
      }
      if ($vote->result == 'no-quorum') {
        return 'No quorum';
      }
      if ($vote->result == 'fail') {
        return 'Fail';
      }
      return '';
    }
    return '';
  }

  public static function getPointSurvey($place_choice)
  {
    switch ($place_choice) {
      case 1:
        return 10;
        break;
      case 2:
        return 9;
        break;
      case 3:
        return 8;
        break;
      case 4:
        return 7;
        break;
      case 5:
        return 6;
        break;
      case 6:
        return 5;
        break;
      case 7:
        return 4;
        break;
      case 8:
        return 3;
        break;
      case 9:
        return 2;
        break;
      case 10:
        return 1;
        break;
      default:
        return 0;
    }
  }

  public static function checkActiveSurvey($user)
  {
    $survey = Survey::where('status', 'active')->first();
    if(!$survey) {
      return false;
    }
    $checkSurveyResult = SurveyResult::where('survey_id', $survey->id)->where('user_id', $user->id)->first();
    return $checkSurveyResult ? false : true;
  }

  public static function createRepHistory($user_id, $value, $rep, $type, $event = null, $proposal_id = null, $vote_id = null, $function_name = null)
  {
    $rep_history = new RepHistory();
    $rep_history->user_id = $user_id;
    $rep_history->value = $value;
    $rep_history->rep = $rep;
    $rep_history->type = $type;
    $rep_history->event = "$event - proposal: $proposal_id - vote: $vote_id at $function_name";
    $rep_history->save();
  }

  public static function getPositionMilestone($milestone)
  {
    $milestones = Milestone::where('proposal_id', $milestone->proposal_id)->orderBy('id', 'asc')->get();
    $total = count($milestones);
    foreach ($milestones as $key => $value) {
      if ($value->id == $milestone->id) {
        $position =  $key + 1;
        break;
      }
    }
    return $position;
  }

  public static function getNotesFailSubmitReview($data)
  {
    $notes = '';
    if ($data['crdao_acknowledged_project'] == 0 && isset($data['crdao_acknowledged_project_notes'])) {
      $notes .= $data['crdao_acknowledged_project_notes'] . '<br>';
    }
    if ($data['crdao_accepted_pm'] == 0 && isset($data['crdao_accepted_pm_notes'])
    ) {
      $notes .= $data['crdao_accepted_pm_notes'] . '<br>';
    }
    if ($data['crdao_acknowledged_receipt'] == 0 && isset($data['crdao_acknowledged_receipt_notes'])) {
      $notes .= $data['crdao_acknowledged_receipt_notes'] . ' <br>';
    }
    if ($data['crdao_submitted_review'] == 0 && isset($data['crdao_submitted_review_notes'])) {
      $notes .= $data['crdao_submitted_review_notes'] . ' <br>';
    }
    if ($data['crdao_submitted_subs'] == 0 && isset($data['crdao_submitted_subs_notes '])
    ) {
      $notes .= $data['crdao_submitted_subs_notes'] . ' <br>';
    }
    if ($data['pm_submitted_evidence'] == 0 && isset($data['pm_submitted_evidence_notes'])) {
      $notes .= $data['pm_submitted_evidence_notes'] . ' <br>';
    }
    if ($data['pm_submitted_admin'] == 0 && isset($data['crdao_acknowledged_project_notes'])) {
      $notes .= $data['crdao_acknowledged_project_notes'] . ' <br>';
    }
    if ($data['pm_submitted_admin'] == 0 && isset($data['pm_submitted_admin_notes'])) {
      $notes .= $data['pm_submitted_admin_notes'] . ' <br>';
    }
    if ($data['pm_verified_corprus'] == 0 && isset($data['pm_verified_corprus_notes'])
    ) {
      $notes .= $data['pm_verified_corprus_notes'] . ' <br>';
    }
    if ($data['pm_verified_crdao'] == 0 && isset($data['pm_verified_crdao_notes'])
    ) {
      $notes .= $data['pm_verified_crdao_notes'] . ' <br>';
    }
    if ($data['pm_verified_subs'] == 0 && isset($data['pm_verified_subs_notes'])
    ) {
      $notes .= $data['pm_verified_subs_notes'] . ' <br>';
    }
    return $notes;
  }

  public static function checkSendMailTriggerMember($title)
  {
    $check = EmailerTriggerMember::where('title', $title)->where('enabled', 1)->count();
    return $check > 0 ? true : false;
  }

  public static function getStatusProposal($proposal)
  {
    $dos_paid = $proposal->dos_paid;
    if ($proposal->status == 'payment') {
      if ($dos_paid) {
        return 'Payment Clearing';
      } else {
        return 'Payment Waiting';
      }
    } else if ($proposal->status == 'pending'
    ) {
      return 'Pending';
    } else if ($proposal->status == 'denied'
    ) {
      return 'Denied';
    } else if ($proposal->status == 'completed'
    ) {
      return 'Completed';
    } else if ($proposal->status == 'approved'
    ) {
      $vote = Vote::where('proposal_id', $proposal->id)->orderBy('created_at', 'desc')->first();
      if ($vote) {
        $type = $vote->type == 'formal' ? "Formal Voting" : "Informal Voting";
        if ($vote->status == 'active'
        ) {
          return "$type - Live";
        } else if ($vote->result  == 'success') {
          return "$type - Passed";
        } else if ($vote->result  == 'no-quorum') {
          return "$type - No Quorum";
        } else {
          return "$type - Failed";
        }
      } else {
        return 'In Discussion';
      }
    } else {
      return '';
    }
  }

  public static function inviteKycKangaroo($invitation_name, $email, $invite_id = null)
  {
    $url = config('services.kyc_kangaroo.url') . '/api/devxdao/invite-user';
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'Authorization' => 'Token ' . config('services.kyc_kangaroo.token')
    ])->post($url, [
      'invitation_name' => $invitation_name,
      'email' => $email,
      'invite_id' => $invite_id,
    ]);
    return $response->json();
  }

  public static function getInviteKycKangaroo($invite_id)
  {
    $url = config('services.kyc_kangaroo.url') . '/api/devxdao/get-invite';
    $response = Http::withHeaders([
      'Content-Type' => 'application/json',
      'Authorization' => 'Token ' . config('services.kyc_kangaroo.token')
    ])->get($url, [
      'invite_id' => $invite_id,
    ]);
    return $response->json();
  }

  public static function createGrantTracking($proposal_id, $event, $key)
  {
    $grantTracking = GrantTracking::where('proposal_id', $proposal_id)->where('key', $key)->first();
    if ($grantTracking) {
        return;
    }
    $grantTracking = new GrantTracking();
    $grantTracking->proposal_id = $proposal_id;
    $grantTracking->event = $event;
    $grantTracking->key = $key;
    $grantTracking->save();
    return;
  }

  public static function createMilestoneSubmitHistory($milestone, $user_id, $milestone_review_id)
  {
    $milesontePosition = self::getPositionMilestone($milestone);
    $milestone_history = new MilestoneSubmitHistory();
    $milestone_history->milestone_id = $milestone->id;
    $milestone_history->proposal_id = $milestone->proposal_id;
    $milestone_history->user_id = $user_id;
    $milestone_history->title = $milestone->title;
    $milestone_history->time_submit = $milestone->time_submit;
    $milestone_history->milestone_position = $milesontePosition;
    $milestone_history->grant = $milestone->grant;
    $milestone_history->url = $milestone->url;
    $milestone_history->comment = $milestone->comment;
    $milestone_history->milestone_review_id = $milestone_review_id;
    $milestone_history->save();
  }

  public static function updateRepProfile($user_id, $value)
  {
    DB::beginTransaction();
    $profile = Profile::where('user_id', $user_id)->lockForUpdate()->first();
    if($profile) {
      $profile->rep = $profile->rep + $value;
      $profile->save();
    }
    DB::commit();
  }

  public static function processKycKangaroo($kyc_response, $user_id)
  {
    $invite = isset($kyc_response['invite']) ? $kyc_response['invite'] : null;
    if (!$invite) {
      return;
    }
    $shuftipro_temp = ShuftiproTemp::where('user_id', $user_id)->whereNotNull('invite_id')->first();
    if (!$shuftipro_temp && isset($invite['invite_id']) && $invite['invite_id'] > 0) {
      ShuftiproTemp::where('user_id', $user_id)->delete();
      $shuftipro_temp = new ShuftiproTemp();
      $shuftipro_temp->user_id = $user_id;
      $shuftipro_temp->reference_id = $invite['shufti_ref_id'] ?? '';
      $shuftipro_temp->status = 'booked';
      $shuftipro_temp->invite_id = $invite['invite_id'] ?? null;
      $shuftipro_temp->invited_at = $invite['invited_at'] ?? null;
      $shuftipro_temp->save();
    }

    $status = $invite['status'] ?? null;
    if ($status) {
      $shuftipro = Shuftipro::where('user_id', $user_id)->first();
      if (!$shuftipro) {
        $shuftipro = new Shuftipro();
      }
      $shuftipro->reference_id = $invite['shufti_ref_id'] ?? '';
      $shuftipro->status = $status;
      $shuftipro->user_id = $user_id;
      $shuftipro->is_successful = $invite['is_successful'] ?? 0;
      $shuftipro->reviewed = $invite['reviewed'] ?? 0;
      $data = json_encode([
        'declined_reason' => $invite['declined_reason'] ?? null,
        'event' => $invite['event'] ?? null,
        'address_document' => $invite['address_document'] ?? null,
        'profile_address' => $invite['profile_address'] ?? null,
        'country_company' => $invite['country_company'] ?? null,
        'api' => $invite['api'] ?? '',
      ]);
      $shuftipro->data = $data;
      $shuftipro->address_result = isset($invite['address_document']) ? 1 : 0;
      $shuftipro->save();
      if ($status == 'approved') {
        $onboardings = OnBoarding::where('user_id', $user_id)->where('status', 'pending')->where('compliance_status', 'approved')->get();
        foreach ($onboardings as $onboarding) {
          $onboarding->status = 'completed';
          $onboarding->save();
          $vote = Vote::find($onboarding->vote_id);
          $proposal = Proposal::find($onboarding->proposal_id);
          $op = User::find($onboarding->user_id);
          $emailerData = Helper::getEmailerData();
          if ($vote && $op && $proposal) {
            Helper::triggerUserEmail($op, 'Passed Informal Grant Vote', $emailerData, $proposal, $vote);
          }
          Helper::startFormalVote($vote);
        }
        $proposals = Proposal::where('user_id', $user_id)->get();
        foreach($proposals as $proposal) {
            Helper::createGrantTracking($proposal->id, "KYC checks complete", 'kyc_checks_complete');
        }
      }
    }
  }

  public static function createGrantLogging($data)
  {
    $data = (object) $data;

    $grantLog = new GrantLog();
    $grantLog->proposal_id = $data->proposal_id;
    $grantLog->final_grant_id = $data->final_grant_id ?? null;
    $grantLog->user_id = $data->user_id ?? null;
    $grantLog->email = $data->email ?? null;
    $grantLog->role = $data->role ? strtolower($data->role) : null;
    $grantLog->type = strtolower($data->type);
    $grantLog->save();
  }

  public static function createHellosignLogging($userId, $event, $key, $metadata)
  {
    $hellosignLog = new HellosignLog();
    $hellosignLog->user_id = $userId;
    $hellosignLog->event = $event;
    $hellosignLog->key = $key;
    $hellosignLog->metadata = $metadata;
    $hellosignLog->save();
  }

  public static function sendKycKangarooUser($user)
  {
    try {
      $shuftipro_temp = ShuftiproTemp::where('user_id', $user->id)->first();
      $invite_id =  $shuftipro_temp->invite_id ?? null;
      $shuftipro = Shuftipro::where('user_id', $user->id)->where('status', 'approved')->first();
      if (!$shuftipro) {
        ShuftiproTemp::where('user_id', $user->id)->delete();
        $kyc_response = Helper::inviteKycKangaroo("$user->first_name $user->last_name", $user->email, $invite_id);
        if (isset($kyc_response['success']) && $kyc_response['success'] == false) {
          Helper::processKycKangaroo($kyc_response, $user->id);
          return;
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
        return;
      }
    } catch (Exception $e) {
      Log::info($e->getMessage());
    }
  }

  public static function queryGetInvoice($startDate, $endDate, $search)
  {
    $query = Invoice::join('proposal', 'proposal.id', '=', 'invoice.proposal_id')
      ->join('users', 'users.id', '=', 'invoice.payee_id')
      ->join('milestone', 'milestone.id', '=', 'invoice.milestone_id')
      ->where(function ($query) use ( $search, $startDate, $endDate) {
        if ($startDate) {
          $query->where('invoice.sent_at', '>=', $startDate);
        }
        if ($endDate) {
          $query->where('invoice.sent_at', '<=', $endDate);
        }
        if ($search) {
          $query->where('proposal.id', 'like', '%' . $search . '%')
            ->orWhere('proposal.title', 'like', '%' . $search . '%')
            ->orWhere('invoice.code', 'like', '%' . $search . '%')
            ->orWhere('users.email', 'like', '%' . $search . '%');
        }
      });
    return $query;
  }

  public static function generatePdfGrantReport()
  {
    $grants = FinalGrant::has('proposal')->with(['proposal'])->orderBy('id', 'asc')->get();
    $collection = collect();
    foreach ($grants as $grant) {
        $month =  $grant->created_at->format('M') . ' ' . $grant->created_at->format('Y');
        $collection->push([
            'id' => $grant->id,
            'month' => $month,
            'grant' => $grant->proposal->total_grant,
        ]);
    }
    $grant_results = [];
    $groupByMonths = $collection->groupBy('month');
    foreach ($groupByMonths as $key => $value) {
        $total = $value->sum('grant');
        $grant_results[] = [
            'month' => $key,
            'number_onboarded' => count($value),
            'total' => $total,
        ];
    }

    $reptutions = Reputation::join('users' , 'users.id', '=', 'reputation.user_id')
        ->select(['reputation.*', 'users.email', DB::raw('YEAR(reputation.created_at) year, MONTH(reputation.created_at) month')])
        ->orderBy('reputation.id', 'asc')->get();
    $reptutions = $reptutions->groupBy('year');
    $rep_results = collect();
    foreach($reptutions as $key => $values) {
        $rep_collection = collect();
        $rep_response = collect();
        foreach($values as $value) {
            $rep_collection->push([
                'id' => $value->id,
                'user_id' => $value->user_id,
                'email' => $value->email,
                'value' => $value->value,
                'type' => $value->type,
                'staked' => $value->staked,
                'pending' => $value->pending,
                'month' => $value->month,
                'year' => $value->year,
                'created_at' => $value->created_at,
            ]);
        }
        $grouped = $rep_collection->groupBy('user_id');
        foreach($grouped as $user_rep) {
            $user_rep_result = collect();
            for($i=1; $i <=12; $i++) {
                $user_rep_filter = $user_rep->where('month', $i);
                $total_stake = $user_rep_filter->where('type', 'Staked')->sum('staked');
                $total_minted = $user_rep_filter->whereIn('type', ['Gained', 'Minted', 'Stake Lost', 'Lost'])->sum('value');
                $user_rep_result["month_$i"] = $total_minted -  $total_stake;
            }
            $user_rep_result['rep_pending'] = $user_rep->sum('pending');
            $rep_response->push([
                'user_id' => $user_rep[0]['user_id'],
                'email' => $user_rep[0]['email'],
                'rep_results' =>  $user_rep_result,
            ]);
        }
        $rep_results->push([
            'year' => $key,
            'rep_response' => $rep_response
        ]);
    }
    $pdf = App::make('dompdf.wrapper');
    $pdfFile = $pdf->loadView('pdf.onboarding', compact('grant_results', 'rep_results'));
    // return $pdf->stream();
    $fullpath = 'pdf/grant/report.pdf';
    Storage::disk('local')->put($fullpath, $pdf->output());
    $url = Storage::disk('local')->url($fullpath);
    return $url;
  }
}
