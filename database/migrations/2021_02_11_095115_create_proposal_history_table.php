<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalHistoryTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_history', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposal');
            $table->foreignId('proposal_change_id')->constrained('proposal_change');
            $table->string('what_section');
            $table->text('change_to_before')->nullable();
            $table->text('additional_notes_before')->nullable();
            $table->double('grant_before', 15, 2)->nullable();
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
        Schema::dropIfExists('proposal_history');
    }
}
