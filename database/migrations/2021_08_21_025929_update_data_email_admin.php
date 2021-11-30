<?php

use App\EmailerTriggerAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataEmailAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item = [
            'title' => 'Admin Task Report',
            'subject' => 'Daily MVPR Admin Task Report',
            'content' => "Hello Admins, <br><br>The system has [count vote] votes needing to enter the formal stage. Please review the Admin portal \"Move to Formal\" tab. <br>[Pass Vote] <br><br>
            The system has [count grant] grants still needing activation signatures. Please check your inboxes for HelloSign requests. <br> [Pass grant] <br> <br>
            The system has [count mistone] milestones submitted and needing review to start the vote process. Please review the Admin portal \"Move to Formal\" tab. <br> [Milestone Submit] <br> <br>
            This daily email is designed to keep all Admins aware of any bottlenecks in the grant process. Please review the above and respond appropriately. <br> <br> Best Regards, <br><br> DxD Admins",
        ];
        $record = EmailerTriggerAdmin::where('title', $item['title'])->first();
        if(!$record) {
            $record = new EmailerTriggerAdmin;
            $record->title = $item['title'];
            $record->subject = $item['subject'];
            $record->enabled = 1;
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
