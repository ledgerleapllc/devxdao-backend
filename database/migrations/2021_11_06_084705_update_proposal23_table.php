<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProposal23Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal', function (Blueprint $table) {
            $table->text('amount_advance_detail')->nullable();
            $table->integer('proposal_request_payment')->nullable();
            $table->integer('proposal_request_from')->nullable();
            $table->string('proposal_advance_status')->nullable();
            \DB::statement("ALTER TABLE `proposal` CHANGE `type` `type` ENUM('grant', 'membership', 'simple', 'admin-grant', 'advance-payment') DEFAULT 'grant';");
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
