<?php

use App\EmailerTriggerAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataEmailTriggerAdmin extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $item =  [
            'title' => 'Vote Complete with No Quorum',
            'subject' => 'A vote had no quorum',
            'content' => 'Proposal "[title]" has failed to achieve quorum. You can restart this vote by clicking Revote in the vote\'s detail page. You can find this by going to the Votes tab and clicking Completed at the top.',
        ];
        $record = EmailerTriggerAdmin::where('title', $item['title'])->first();
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
