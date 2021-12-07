<?php

use App\EmailerTriggerUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdvancePaymentVoteEmailerTriggerUserTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $userData = [
            [
                'title' => 'Advance Payment Vote Failed',
                'subject' => 'Your advance payment proposal has failed the vote',
                'content' => 'Your advance payment proposal titled "[title]" has failed the [voteType] vote.'
            ],
            [
                'title' => 'Advance Payment Vote Passed',
                'subject' => 'Your advance payment proposal has passed the vote',
                'content' => 'Your advance payment proposal titled "[title]" has passed the [voteType] vote.'
            ],
        ];

        foreach ($userData as $item) {
            $record = EmailerTriggerUser::where('title', $item['title'])->first();
            if ($record) $record->delete();

            $record = new EmailerTriggerUser;
            $record->title = $item['title'];
            $record->subject = $item['subject'];
            $record->content = $item['content'];
            $record->enabled = 1;
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
