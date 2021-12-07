<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyRfpRank extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey_rfp_rank', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('bid_id');
            $table->integer('bid');
            $table->integer('rank');
            $table->integer('total_point');
            $table->integer('is_winner')->default(0);
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
        Schema::dropIfExists('survey_rfp_rank');
    }
}
