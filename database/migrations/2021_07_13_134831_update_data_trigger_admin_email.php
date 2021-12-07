<?php

use App\EmailerTriggerAdmin;
use App\EmailerTriggerMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataTriggerAdminEmail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item = [
            'title' => 'KYC Review',
            'subject' => 'KYC review needed',
            'content' => '[first_name] [last_name] for proposal number [number] and title [title] has submitted their KYC and it needs review. If you are the compliance director, please log in and review under the Move to Formal tab. You can also log into the Shufti portal for further info. This KYC must be Approved, Reset, or Denied.'
        ];
        $record = EmailerTriggerAdmin::where('title', $item['title'])->first();
    
        if($record) {
            $record->content = $item['content'];
            $record->save();
        }

        $item = [
            'title' => 'VA daily summary',
            'subject' => 'Daily Voting Associate summary for DEVxDAO',
            'content' => "Hello VA's <br><br>Please review the following proposals that entered discussions today:<br>[Proposal Tittle Discussions] <br>
                And also the proposals that have started a vote today. Don?t forget to vote:<br> [Proposal started vote today] <br>
                Votes ending within 24 hours that have not reached quorum: <br> [Proposal not reached quorum] <br>
                Remember, voting is essential to being a Voting Associate. Logging in once a day and voting makes sure you do
                not lose your status as a Voting Associate and related rewards. <br> <br> Best Regards, <br><br> DxD Admins",
    
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
