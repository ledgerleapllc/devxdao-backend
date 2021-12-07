<?php

use App\Profile;
use App\RepHistory;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateRepHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('rep_history', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->float('value')->default(0);
            $table->float('rep')->default(0);
            $table->string('type')->nullable();
            $table->string('event')->nullable();
            $table->timestamps();
        });

        $profiles = Profile::join('users', 'users.id', '=', 'profile.user_id')->where('users.is_admin', '!=',  1)
            ->where('users.is_super_admin', '!=',  1)->get();
        foreach ($profiles as $profile) {
            $rep_history = new RepHistory();
            $rep_history->user_id = $profile->user_id;
            $rep_history->value = 0;
            $rep_history->rep = $profile->rep;
            $rep_history->type = '';
            $rep_history->event = '';
            $rep_history->save();
        }
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('rep_history');
    }
}
