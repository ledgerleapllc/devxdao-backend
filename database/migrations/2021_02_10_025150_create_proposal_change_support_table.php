<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalChangeSupportTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_change_support', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_change_id')->constrained('proposal_change');
            $table->foreignId('user_id')->constrained('users');
            $table->enum('value', ['up', 'down'])->default('up');
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
        Schema::dropIfExists('proposal_change_support');
    }
}
