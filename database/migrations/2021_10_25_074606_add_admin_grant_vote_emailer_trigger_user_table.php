<?php

use App\EmailerTriggerUser;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddAdminGrantVoteEmailerTriggerUserTable extends Migration
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
                'title' => 'Admin Grant Vote Failed',
                'subject' => 'You are now a voting associate!',
                'content' => 'Way to go! You are now a voting associate! This comes with obligations, please watch your email and join discussions and make sure to vote when you get the alerts. For more answers, just ask in telegram.'
            ],
            [
                'title' => 'Admin Grant Vote Passed',
                'subject' => 'Your admin grant proposal has passed the vote',
                'content' => 'Your admin grant proposal titled "[title]" has passed the [voteType] vote.'
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
