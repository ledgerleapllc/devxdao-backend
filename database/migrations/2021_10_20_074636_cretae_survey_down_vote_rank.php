<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CretaeSurveyDownVoteRank extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey_downvote_rank', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('proposal_id');
            $table->integer('rank')->nullable();
            $table->integer('total_point')->nullable();
            $table->tinyInteger('is_winner')->nullable();
            $table->tinyInteger('is_approved')->nullable();
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
        //
    }
}
