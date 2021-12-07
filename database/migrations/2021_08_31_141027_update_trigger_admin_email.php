<?php

use App\EmailerTriggerAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTriggerAdminEmail extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item = [
            'title' => 'Total rep',
            'subject' => 'End of month rep',
            'content' => "The DxD Voting associates finished the month with the following Total Rep numbers: <br> <br> [total rep]",
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
