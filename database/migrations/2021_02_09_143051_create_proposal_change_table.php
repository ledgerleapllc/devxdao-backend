<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalChangeTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_change', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposal');
            $table->string('what_section');
            $table->text('change_to')->nullable();
            $table->text('additional_notes')->nullable();
            $table->double('grant', 15, 2)->nullable();
            $table->enum('status', ['pending', 'approved', 'denied', 'withdrawn'])->default('pending');
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
        Schema::dropIfExists('proposal_change');
    }
}
