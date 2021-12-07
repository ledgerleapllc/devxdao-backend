<?php

use App\EmailerTriggerAdmin;
use App\EmailerTriggerMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateEmailTriggerMember extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        EmailerTriggerAdmin::where('title', 'Passed milestone review')->delete();
        EmailerTriggerAdmin::where('title', 'Failed milestone review')->delete();

        $items = [
            [
                'title' => 'PM Reviewer assigned',
                'subject' => 'Reviewer assigned to your DEVxDAO Grant Milestone',
                'content' => "[Firstname], <br> A reviewer has been assigned to review the milestone you submitted towards your grant [grant title]. Please allow up to a week for this process and look our for our next email informing you of your submission review status.
                    If your milestone is approved as delivered, your proposal will moving automatically to voting. If it needs work, the review team will reply with any needed notes.
                    Thank you for being part of the program,
                    DxD Program Management",
            ],
            [
                'title' => 'Milestone code review Passed',
                'subject' => 'Your DEVxDAO Grant Milestone is Approved',
                'content' => "[Firstname], <br>Nice work! Your milestone submission for [grant title] is Approved. This will now  move forward to voting. Please allow up to 2 weeks for the voting and look our for a next email regarding the votes.
                    Thank you for being part of the program,
                    DxD Program Management",
            ],
            [
                'title' => 'Milestone code review Failed',
                'subject' => 'Your DEVxDAO Grant Milestone needs some work',
                'content' => "[Firstname], <br>Unfortunately your milestone submission needs a few adjustments before it can be considered for voting and payment.
                    Please see the notes below and submit this milestone again in the DEVxDAO portal when these items are remedied.           
                    Thank you for being part of the program,
                    DxD Program Management",
            ],
        ];
        foreach ($items as $item) {
            $record = EmailerTriggerMember::where('title', $item['title'])->first();
            if (!$record) {
                $record = new EmailerTriggerMember;
                $record->title = $item['title'];
                $record->subject = $item['subject'];
                $record->enabled = 1;
                $record->content = $item['content'];
                $record->save();
            }
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
