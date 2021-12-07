<?php

use App\EmailerTriggerMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataEmailTriggerMember extends Migration
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
            'content' => "Hello VA's <br><br>Please review the following proposals that entered discussions today:<br> [LIST] <br>[Proposal Tittle Discussions] <br><br> 
                And also the proposals that have started a vote today. Don?t forget to vote:<br>[LIST]<br> [Proposal started vote today] <br> <br>
                Remember, voting is essential to being a Voting Associate. Logging in once a day and voting makes sure you do
                not lose your status as a Voting Associate and related rewards. <br> <br> Best Regards, <br><br> DxD Admins",
    
        ];
        $record = EmailerTriggerMember::where('title', $item['title'])->first();
        if(!$record) {
            $record = new EmailerTriggerMember();
            $record->title = $item['title'];
            $record->subject = $item['subject'];
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
