<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyRfpResultTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey_rfp_result', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('user_id');
            $table->integer('bid_id');
            $table->integer('bid');
            $table->integer('place_choice');
            $table->integer('point');
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
        Schema::dropIfExists('survey_rfp_result');
    }
}
