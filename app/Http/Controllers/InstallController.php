<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role;

use App\User;
use App\Profile;
use App\Setting;
use App\Bank;
use App\Crypto;
use App\Citation;
use App\Grant;
use App\Milestone;
use App\OnBoarding;
use App\ProposalChangeComment;
use App\ProposalChangeSupport;
use App\ProposalChange;
use App\ProposalFile;
use App\ProposalHistory;
use App\Reputation;
use App\Team;
use App\VoteResult;
use App\Vote;
use App\Proposal;
use App\FinalGrant;
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerTriggerMember;

use App\Mail\Confirmation;

class InstallController extends Controller
{
    public function clear() {
        Bank::where('id', '>', 0)->delete();
        Crypto::where('id', '>', 0)->delete();
        Grant::where('id', '>', 0)->delete();
        Milestone::where('id', '>', 0)->delete();
        Citation::where('id', '>', 0)->delete();
        OnBoarding::where('id', '>', 0)->delete();

        FinalGrant::where('id', '>', 0)->delete();
        ProposalChangeComment::where('id', '>', 0)->delete();
        ProposalChangeSupport::where('id', '>', 0)->delete();
        ProposalHistory::where('id', '>', 0)->delete();
        ProposalChange::where('id', '>', 0)->delete();
        ProposalFile::where('id', '>', 0)->delete();
        Reputation::where('id', '>', 0)->delete();
        Team::where('id', '>', 0)->delete();

        VoteResult::where('id', '>', 0)->delete();
        Vote::where('id', '>', 0)->delete();
        Proposal::where('id', '>', 0)->delete();
    }

    public function installEmailer() {
        // Setup Member
        $memberData = [
            [
                'title' => 'New Proposal Discussion',
                'subject' => 'A [type] proposal is being discussed',
                'content' => 'Please log in to your portal and review proposal in your Discussions tab<br/><br/>Proposal title - [title]<br/>Proposal content - [content]'
            ],
            [
                'title' => 'New Vote',
                'subject' => 'A [voteContentType] proposal vote is live!',
                'content' => 'Please log in to your portal and vote on the following<br/><br/>Proposal title - [title]<br/>Proposal content - [content]'
            ],
            [
                'title' => 'VA daily summary',
                'subject' => 'Daily Voting Associate summary for DEVxDAO',
                'content' => "Hello VA's <br><br>Please review the following proposals that entered discussions today: <br>[Proposal Tittle Discussions] <br><br>
                    And also the proposals that have started a vote today. Don?t forget to vote: <br> [Proposal started vote today] <br> <br>
                    Remember, voting is essential to being a Voting Associate. Logging in once a day and voting makes sure you do
                    not lose your status as a Voting Associate and related rewards. <br> <br> Best Regards, <br><br> DxD Admins",

            ]
        ];

        EmailerTriggerMember::where('id', '>', 0)->delete();

        if (count($memberData)) {
            foreach ($memberData as $item) {
                $record = EmailerTriggerMember::where('title', $item['title'])->first();
                if ($record) $record->delete();

                $record = new EmailerTriggerMember;
                $record->title = $item['title'];
                $record->subject = $item['subject'];
                $record->content = $item['content'];
                $record->save();
            }
        }

        // Setup User
        $userData = [
            [
                'title' => 'New User',
                'subject' => 'Notification',
                'content' => 'Thank you for signing up. Please look out for an email from the portal admin requesting your signature on the required agreements for your portal access.'
            ],
            [
                'title' => 'Access Granted',
                'subject' => 'Notification',
                'content' => 'Welcome and congratulations! Your portal account is now active. Thank your for signing your forms and feel free to log in and start your first proposal in the My Proposals tab.'
            ],
            [
                'title' => 'New Proposal',
                'subject' => 'Notification',
                'content' => 'Your proposal has been submitted. Next, an administrator will review your proposal. If no changes are needed, your proposal will be approved and you will need to pay a DOS fee before your proposal moves to the discussion and voting stages.'
            ],
            [
                'title' => 'Admin Approval',
                'subject' => 'Notification',
                'content' => 'Your proposal has been approved. Before your proposal can move forward, you must pay your DOS fee. Please log into your portal and click the button next to your proposal in the "My Proposals" tab.'
            ],
            [
                'title' => 'DOS Confirmation',
                'subject' => 'Notification',
                'content' => 'Your proposal is now active. Thank you for paying the DOS fee. Next, your proposal will enter the discussion stage. Members will review, comment, and potentially suggest changes to your proposal. Changes suggested by the members may be found by clicking on the Proposal in your "My Proposals" tab and scrolling down to see the changes section at the bottom. It is your choice to accept or deny any change, but keep in mind each change will show a level of support from the members. We recommend reviewing all changes carefully. Changes with high support mean that the crowd would like a change made. When these members vote on your proposal in the next step, it helps to have member support. Conversely, changes with low support may not affect the vote as heavily. Your proposal will be eligible to move to the voting step after 72 hours. Once that time has passed, review and act on any pending changes and click on your proposals tab to start the vote.'
            ],
            [
                'title' => 'Vote Ready to Start',
                'subject' => 'Your proposal is ready for the voting step.',
                'content' => 'You proposal "[title]" is ready to start the informal voting stage. You have [pendingChangesCount] changes to address before you can start the informal vote. To address any changes and start the vote, go to your My Proposals tab and click the proposal. A start vote button is at the top of the page and any changes you need to make a decision on are at the bottom.'
            ],
            [
                'title' => 'Failed Informal Grant Vote',
                'subject' => 'Your proposal has failed the informal voting stage',
                'content' => 'You proposal "[title]" has failed the informal voting stage and cannot move ahead.'
            ],
            [
                'title' => 'Passed Informal Grant Vote',
                'subject' => 'Your proposal has passed the informal voting stage - more info needed',
                'content' => 'Congratulations, your proposal "[title]" has passed the informal voting stage. Please log in to your portal, click the My Proposals tab, and look at the upper table for the 3 actions you must take before the formal vote can start.<br/><br/>1. You must submit your KYC information.<br/><br/>2. You must complete the payment form.<br/><br/>3. You must sign the grant agreement. Check your email for an email from HelloSign.'
            ],
            [
                'title' => 'Vote Recieved No Quorum',
                'subject' => 'Not enough people voted',
                'content' => 'Your [voteContentType] vote titled "[title]" did not receive enough voted. An Admin will restart this vote withing 72 hours.'
            ],
            [
                'title' => 'AML Submit',
                'subject' => 'Notification',
                'content' => 'Thank you for providing your AML/KYC details. We will send another email once these documents are reviewed.'
            ],
            [
                'title' => 'AML Deny',
                'subject' => 'Notification',
                'content' => 'We are sorry but your KYC/AML information was denied. We cannot provide you with a grant or membership.'
            ],
            [
                'title' => 'AML Approve',
                'subject' => 'Notification',
                'content' => 'Your AML/KYC status is approved. If you have not yet completed the other steps, please log in and do these now.'
            ],
            [
                'title' => 'AML Reset',
                'subject' => 'Notification',
                'content' => 'Your AML was reset, please try again. You will see a second email with notes from a staff member about why you need to submit again and how you can avoid further issues in this step.'
            ],
            [
                'title' => 'Payment Form Complete',
                'subject' => 'Payment form completed',
                'content' => 'Thank you for completing the payment form! Please submit your KYC and payment form as well if you have not yet completed these steps.'
            ],
            [
                'title' => 'Grant Ready for Formal Vote',
                'subject' => 'You have completed all grant on-boarding steps!',
                'content' => 'Thank you for completing KYC, the payment form, and signing the grant agreement. An admin will review these details and start a formal vote for your proposal within 72 hours.'
            ],
            [
                'title' => 'Formal Grant Vote Failed',
                'subject' => 'Your grant has failed to pass the formal vote',
                'content' => 'Unfortunately your proposal has failed to pass the vote and cannot become a grant. You may submit again later.'
            ],
            [
                'title' => 'Formal Grant Vote Passed',
                'subject' => 'Your grant has passed the formal vote!',
                'content' => 'Congratulations! Your proposal has passed the formal vote and will become a grant. We will be completing your grant on-boarding. Please check your Grants tab in 72 hours.'
            ],
            [
                'title' => 'Grant Live',
                'subject' => 'Your Grant is active! It\'s time to build!',
                'content' => 'Congratulations! Your grant is fully live. Please begin building your project. When you are ready to submit your first milestone, go to your Grants tab and click the Submit Milestone button. Once all milestones are complete, your will become a Voting Associate.'
            ],
            [
                'title' => 'Milestone Submitted',
                'subject' => 'Your milestone has been submitted',
                'content' => 'We have received your milestone submission. Next, this milestone will go to an informal, then formal vote. Please watch your email.'
            ],
            [
                'title' => 'Milestone Vote Failed',
                'subject' => 'Your milestone has failed the vote',
                'content' => 'Unfortunately, your milestone submission has failed the vote. Please check your work and resubmit after your have solved the problem. Contact admin in the telegram for more details.'
            ],
            [
                'title' => 'Milestone Vote Passed Informal',
                'subject' => 'Your milestone has passed the informal vote',
                'content' => 'Your milestone has passed the informal vote for "[title]". Next it will go to the formal vote.'
            ],
            [
                'title' => 'Milestone Vote Passed Formal',
                'subject' => 'Your milestone has passed the formal vote',
                'content' => 'Your milestone has passed the formal vote for "[title]". Well done on your delivery! Please keep building the next milestone!'
            ],
            [
                'title' => 'All Milestones Complete',
                'subject' => 'You have completed the job!',
                'content' => 'Well done! You have completed all milestones. All REP minted and pending will now be delivered to you. If you are not already a Voting Associate, you have just become one!'
            ],
            [
                'title' => 'New Voting Associate',
                'subject' => 'You are now a voting associate!',
                'content' => 'Way to go! You are now a voting associate! This comes with obligations, please watch your email and join discussions and make sure to vote when you get the alerts. For more answers, just ask in telegram.'
            ],
            [
                'title' => 'Simple Vote Failed',
                'subject' => 'You are now a voting associate!',
                'content' => 'Way to go! You are now a voting associate! This comes with obligations, please watch your email and join discussions and make sure to vote when you get the alerts. For more answers, just ask in telegram.'
            ],
            [
                'title' => 'Simple Vote Passed',
                'subject' => 'Your simple proposal has passed the vote',
                'content' => 'Your simple proposal titled "[title]" has passed the [voteType] vote.'
            ],
            [
                'title' => 'Admin Grant Vote Failed',
                'subject' => 'You are now a voting associate!',
                'content' => 'Way to go! You are now a voting associate! This comes with obligations, please watch your email and join discussions and make sure to vote when you get the alerts. For more answers, just ask in telegram.'
            ],
            [
                'title' => 'Admin Grant Vote Passed',
                'subject' => 'Your admin grant proposal has passed the vote',
                'content' => 'Your admin grant proposal titled "[title]" has passed the [voteType] vote.'
            ],
            [
                'title' => 'Pre-Register Approve',
                'subject' => 'Notification',
                'content' => 'You are invited to the Portal registration. <a href="[url]">Click here to go to the registration page.</a>'
            ],
            [
                'title' => 'Pre-Register Deny',
                'subject' => 'Notification',
                'content' => 'You are not approved for joining at this time.'
            ],
            [
                'title' => 'Deny Access',
                'subject' => 'Notification',
                'content' => 'You are not approved for an account at this time. Feel free to try again in a few weeks when more spots are open.'
            ]
        ];

        EmailerTriggerUser::where('id', '>', 0)->delete();

        if (count($userData)) {
            foreach ($userData as $item) {
                $record = EmailerTriggerUser::where('title', $item['title'])->first();
                if ($record) $record->delete();

                $record = new EmailerTriggerUser;
                $record->title = $item['title'];
                $record->subject = $item['subject'];
                $record->content = $item['content'];
                $record->save();
            }
        }

        // Setup Admin
        $adminData = [
            [
                'title' => 'New User',
                'subject' => 'A user has signed up',
                'content' => 'You can review this user in the upper table in the admin\'s "Users" tab. Click review to approved or deny portal access.'
            ],
            [
                'title' => 'New Proposal',
                'subject' => 'A proposal has been submitted',
                'content' => 'You must review this proposal in the New Proposals tab and approve it if acceptable.'
            ],
            [
                'title' => 'DOS Fee Paid',
                'subject' => 'DOS fee paid - action required',
                'content' => 'Proposal "[title]" has paid the DOS fee. Please log in, go to the New Proposals tab, and review this payment. Once payment is approved, this proposal will enter discussion.'
            ],
            [
                'title' => 'Vote Started',
                'subject' => 'A vote has started',
                'content' => 'Proposal "[title]" has started the [voteType] vote. This is a [voteContentType] vote.'
            ],
            [
                'title' => 'Vote Complete with No Quorum',
                'subject' => 'A vote had no quorum',
                'content' => 'Proposal "[title]" has failed to achieve quorum. You can restart this vote by clicking Revote in the vote\'s detail page. You can find this by going to the Votes tab and clicking Completed at the top.',
            ],
            [
                'title' => 'Signatures Needed',
                'subject' => 'Signatures needed - informal grant vote passed',
                'content' => 'Proposal "[title]" has passed the informal vote. If you are the COO or CFO, please check your email for a hellosign email. You must sign for this proposal to move ahead.'
            ],
            [
                'title' => 'KYC Review',
                'subject' => 'KYC review needed',
                'content' => '[first_name] [last_name] for proposal number [number] and title [title] has submitted their KYC and it needs review. If you are the compliance director, please log in and review under the Move to Formal tab. You can also log into the Shufti portal for further info. This KYC must be Approved, Reset, or Denied.'
            ],
            [
                'title' => 'Vote Ready for Formal',
                'subject' => 'Formal vote ready to start',
                'content' => 'An OP has completed all steps to ready their proposal "[title]" for formal vote. Please log in and go to your Move to Formal tab and click Start Formal Vote.',
            ],
            [
                'title' => 'Formal Vote Passed',
                'subject' => 'Formal vote has passed - action needed',
                'content' => 'Proposal "[title]" has passed its formal vote. Please log in and go to the Grants tab. If you are the association President, you must sign the form and upload it to start the Grant.',
            ],
            [
                'title' => 'New Milestone',
                'subject' => 'A milestone has been submitted',
                'content' => '[email] has submitted a milestone for "[title]". This vote is now live.',
            ],
            [
                'title' => 'Grant Complete',
                'subject' => 'A grant is complete and a new membership issued',
                'content' => '[email] has completed their grant for "[title]" and is now a member.',
            ],
            [
                'title' => 'KYC Needs Review',
                'subject' => 'User [name] needs KYC review',
                'content' => 'Please login to the portal and review the account for [user name] [user email] for proposal number [proposal number]. You will need to go to the tab titled "Move to Formal" and click the "Review" link in the KYC column for proposal title [proposal title].',
            ]
        ];

        EmailerTriggerAdmin::where('id', '>', 0)->delete();

        if (count($adminData)) {
            foreach ($adminData as $item) {
                $record = EmailerTriggerAdmin::where('title', $item['title'])->first();
                if ($record) $record->delete();

                $record = new EmailerTriggerAdmin;
                $record->title = $item['title'];
                $record->subject = $item['subject'];
                $record->content = $item['content'];
                $record->save();
            }
        }
    }

    public function install() {
		/* Setting Roles */
        $role = Role::where(['name' => 'admin'])->first();
        if (!$role) Role::create(['name' => 'admin']);

        $role = Role::where(['name' => 'participant'])->first();
        if (!$role) Role::create(['name' => 'participant']);

        $role = Role::where(['name' => 'member'])->first();
        if (!$role) Role::create(['name' => 'member']);

        $role = Role::where(['name' => 'proposer'])->first();
        if (!$role) Role::create(['name' => 'proposer']);

        $role = Role::where(['name' => 'guest'])->first();
        if (!$role) Role::create(['name' => 'guest']);

        $role = Role::where(['name' => 'super-admin'])->first();
        if (!$role) Role::create(['name' => 'super-admin']);

        echo "Roles created!<br/>";

        /* Setting Admin */
        $user = User::where(['email' => 'ledgerleapllc@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Ledger';
            $user->last_name = 'Leap';
            $user->email = 'ledgerleapllc@gmail.com';
            $random_pw = Str::random(10);
            $user->password = Hash::make($random_pw);
            Log::info('Created admin');
            Log::info('Email: '.$user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin'))
            $user->assignRole('admin');

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'LedgerLeap';
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
        echo "Admin created!<br/>";

        /* Second Admin */
        $user = User::where(['email' => 'wulf@wulfkaal.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'DevDao';
            $user->last_name = 'Admin';
            $user->email = 'wulf@wulfkaal.com';
            $random_pw = Str::random(10);
            $user->password = Hash::make($random_pw);
            Log::info('Created admin');
            Log::info('Email: '.$user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin'))
            $user->assignRole('admin');

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'DevDao';
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
        echo "Second Admin created!<br/>";

        /* Third Admin */
        $user = User::where(['email' => 'timothytlewis@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Tomothy';
            $user->last_name = 'Tlewis';
            $user->email = 'timothytlewis@gmail.com';
            $random_pw = Str::random(10);
            $user->password = Hash::make($random_pw);
            Log::info('Created admin');
            Log::info('Email: '.$user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin'))
            $user->assignRole('admin');

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'DevDao';
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
        echo "Third Admin created!<br/>";

        /* Fourth Admin */
        $user = User::where(['email' => 'timothy.messer@emergingte.ch'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Timothy';
            $user->last_name = 'Messer';
            $user->email = 'timothy.messer@emergingte.ch';
            $random_pw = Str::random(10);
            $user->password = Hash::make($random_pw);
            Log::info('Created admin');
            Log::info('Email: '.$user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin'))
            $user->assignRole('admin');

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'DevDao';
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
        echo "Fourth Admin created!<br/>";

        /* Fifth Admin */
        $user = User::where(['email' => 'hhoweconsulting@gmail.com'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Halyley';
            $user->last_name = 'Howe';
            $user->email = 'hhoweconsulting@gmail.com';
            $random_pw = Str::random(10);
            $user->password = Hash::make($random_pw);
            Log::info('Created admin');
            Log::info('Email: '.$user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin'))
            $user->assignRole('admin');

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'DevDao';
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
        echo "Fifth Admin created!<br/>";

        /* Sixth Admin */
        $user = User::where(['email' => 'raphael.baumann@emergingte.ch'])->first();
        if (!$user) {
            $user = new User;
            $user->first_name = 'Raphael';
            $user->last_name = 'Baumann';
            $user->email = 'raphael.baumann@emergingte.ch';
            $random_pw = Str::random(10);
            $user->password = Hash::make($random_pw);
            Log::info('Created admin');
            Log::info('Email: '.$user->email);
            Log::info('Password: '.$random_pw);
            Log::info('');
            $user->confirmation_code = 'admin';
            $user->email_verified = 1;
            $user->is_admin = 1;
            $user->save();
        }

        if (!$user->hasRole('admin'))
            $user->assignRole('admin');

        $profile = Profile::where('user_id', $user->id)->first();
        if (!$profile) {
            $profile = new Profile;
            $profile->user_id = $user->id;
            $profile->company = 'DevDao';
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
        echo "Sixth Admin created!<br/>";

        /* Fix Forum Name */
        $users = User::get();
        foreach ($users as $user) {
            $profile = Profile::where('user_id', $user->id)->first();

            if ($profile && !$profile->forum_name) {
                $forum_name = $user->first_name . '_' . $user->id;
                $profile->forum_name = $forum_name;
                $profile->save();
            }
        }
        echo "Forum Name created!<br/>";

        /* Setting */
        $names = [
            'coo_email' => '',
            'cfo_email' => '',
            'board_member_email' => '',
            'president_email' => '',
            'time_before_op_do' => '24',
            'time_unit_before_op_do' => 'hour',
            'can_op_start_informal' => 'yes',
            'time_before_op_informal' => '7',
            'time_unit_before_op_informal' => 'day',
            'time_before_op_informal_simple' => '7',
            'time_unit_before_op_informal_simple' => 'day',
            'time_informal' => '48',
            'time_unit_informal' => 'hour',
            'time_formal' => '48',
            'time_unit_formal' => 'hour',
            'time_simple' => '48',
            'time_unit_simple' => 'hour',
            'time_milestone' => '48',
            'time_unit_milestone' => 'hour',
            'dos_fee_amount' => '100',
            'btc_address' => 'bc1qxy2kgdygjrsqtzq2n0yrf2493p83kkfjhx0wlh',
            'eth_address' => '0x06cf1395611c3789d4cbb7a6ce927503d4a9d22f',
            'rep_amount' => '50',
            'minted_ratio' => '0.5',
            'op_percentage' => '50',
            'pass_rate' => '60',
            'quorum_rate' => '50',
            'pass_rate_simple' => '60',
            'quorum_rate_simple' => '50',
            'pass_rate_milestone' => '60',
            'quorum_rate_milestone' => '50',
            'need_to_approve' => 'yes',
            'autostart_grant_formal_votes' => 'no',
            'autostart_simple_formal_votes' => 'no',
            'autostart_admin_grant_formal_votes' => 'no',
            'autostart_advance_payment_formal_votes' => 'no',
            'autoactivate_grants' => 'yes',
        ];

        foreach ($names as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if (!$setting) {
                $setting = new Setting;
                $setting->name = $name;
                $setting->value = $value;
                $setting->save();
            }
        }

        echo "Setting created<br/>";

        // Members
        $emails = [
            'charles+testfds@ledgerleap.com',
            'charles+testfdsf@ledgerleap.com',
            'charles+testfdskhfdsfdhs@ledgerleap.com',
            'sam+dxdtest6@ledgerleap.com',
            'sam+dxdtest8@ledgerleap.com',
            'jasoncoellox1@gmail.com'
        ];

        foreach ($emails as $email) {
            $user = User::where('email', $email)->first();

            if ($user) {
                $user->assignRole('member');
                $user->is_member = 1;
                $user->save();
            }
        }
	}
}
