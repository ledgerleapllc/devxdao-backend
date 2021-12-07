<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyResultTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey_result', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('user_id');
            $table->integer('proposal_id');
            $table->integer('place_choice')->nullable();
            $table->integer('point')->nullable();
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
        Schema::dropIfExists('survey_result');
    }
}
