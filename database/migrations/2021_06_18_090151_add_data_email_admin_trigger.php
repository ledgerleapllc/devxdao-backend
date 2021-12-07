<?php

use App\EmailerTriggerAdmin;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDataEmailAdminTrigger extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $emailTriggerAdmin = new EmailerTriggerAdmin();
        $emailTriggerAdmin->title = 'KYC Needs Review';
        $emailTriggerAdmin->subject ='User [name] needs KYC review';
        $emailTriggerAdmin->content = 'Please login to the portal and review the account for [user name] [user email] for proposal number [proposal number]. You will need to go to the tab titled "Move to Formal" and click the "Review" link in the KYC column for proposal title [proposal title.';
        $emailTriggerAdmin->enabled = 0;
        $emailTriggerAdmin->save();
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
