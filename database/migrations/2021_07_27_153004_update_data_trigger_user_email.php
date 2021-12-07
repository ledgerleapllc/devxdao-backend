<?php

use App\EmailerTriggerUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataTriggerUserEmail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {

        $item = [
            'title' => 'Milestone Deny',
            'subject' => 'Your milestone submission for proposal [proposalId] has been denied.',
            'content' => "[first_name], <br><br> Your submission of milestone number [milestoneId] for proposal number [proposalId] and title [title] has been denied for the following reason: <br><br> <b> [deny reason] </b> <br> <br> You may resubmit this milestone ONLY AFTER you have corrected the problem. <br><br> Best Regards, <br><br> DxD Admins <br><br>",

        ];
        $emailTriggerUser = EmailerTriggerUser::where('title', $item['title'])->first();
        if (!$emailTriggerUser) {
            $emailTriggerUser = new EmailerTriggerUser();
        }
        $emailTriggerUser->title = $item['title'];
        $emailTriggerUser->subject = $item['subject'];
        $emailTriggerUser->content = $item['content'];
        $emailTriggerUser->enabled = 1;
        $emailTriggerUser->save();
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        //
    }
}
