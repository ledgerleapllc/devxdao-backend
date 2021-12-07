<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCitationTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('citation', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposal');
            $table->foreignId('rep_proposal_id')->constrained('proposal');
            $table->text('explanation');
            $table->integer('percentage');
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
        Schema::dropIfExists('citation');
    }
}
