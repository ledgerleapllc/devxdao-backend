<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateSurveyRfpBidTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('survey_rfp_bid', function (Blueprint $table) {
            $table->id();
            $table->integer('survey_id');
            $table->integer('bid');
            $table->string('name');
            $table->string('forum');
            $table->string('email');
            $table->timestamp('delivery_date');
            $table->integer('amount_of_bid');
            $table->text('additional_note')->nullable();
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
        Schema::dropIfExists('survey_rfp_bid');
    }
}
