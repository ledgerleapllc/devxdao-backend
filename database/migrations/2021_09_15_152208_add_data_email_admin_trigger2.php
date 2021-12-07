<?php

use App\EmailerTriggerAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDataEmailAdminTrigger2 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item = [
            'title' => 'Passed milestone review',
            'subject' => 'Your grant [number] has passed milestone review!',
            'content' => "Congratulations! Your proposal titled [title] has passed it’s review for milestone number [number]. The milestone vote will begin shortly.",
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

        $item = [
            'title' => 'Failed milestone review',
            'subject' => 'Your grant [number] has failed milestone review.',
            'content' => "Your proposal titled [title] has failed it’s review for milestone number [number]. Please log in to your portal, review the notes and submit again after the problems are fixes. Please see the reason below: [add any notes passed with red X items to the bottom of the email as bullet points]",
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
