<?php

use App\EmailerTriggerMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTriggerEmailMember extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item = [
            'title' => 'VA daily summary',
            'subject' => 'Daily Voting Associate summary for DEVxDAO',
            'content' => "<br> Hello VA's<br> <br> Please review the following <b>proposals that entered discussions today:</b><br><br> [Proposal Tittle Discussions]<br><br> Review the <b>proposals that have started a vote today.</b> Don't forget to vote:<br><br> [Proposal started vote today] <br><br> Votes that will <b> expire within the next 24hrs </b> that have <b> not met quorum: </b> <br><br>[Proposal not reached quorum]<br><br> <b>Remember, voting is essential to being a Voting Associate. Logging in once a day and voting makes sure you do not lose your status as a Voting Associate and related rewards.</b> <br><br><br> Best Regards, <br><br> DxD Admins <br><br>",
    
        ];
        $record = EmailerTriggerMember::where('title', $item['title'])->first();
        if($record) {
            $record->content = $item['content'];
            $record->save();
        }
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
