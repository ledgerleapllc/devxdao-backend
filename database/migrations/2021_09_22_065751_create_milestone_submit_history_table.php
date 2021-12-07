<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMilestoneSubmitHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('milestone_submit_history', function (Blueprint $table) {
            $table->id();
            $table->integer('milestone_id');
            $table->integer('proposal_id');
            $table->integer('user_id');
            $table->string('title');
            $table->integer('time_submit')->nullable();
            $table->integer('milestone_position')->nullable();
            $table->float('grant')->nullable();
            $table->string('url')->nullable();
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('milestone_submit_history');
    }
}
