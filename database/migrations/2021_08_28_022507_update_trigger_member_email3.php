<?php

use App\EmailerTriggerMember;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateTriggerMemberEmail3 extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item = [
            'title' => 'New Survey',
            'subject' => 'A new DEVxDAO survey needs your responses',
            'content' => "Please log in to your portal to complete your survey. This is mandatory!",
    
        ];
        $record = EmailerTriggerMember::where('title', $item['title'])->first();
        if(!$record) {
            $record = new EmailerTriggerMember;
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
