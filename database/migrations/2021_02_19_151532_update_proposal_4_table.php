<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProposal4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal', function ($table) {
            $table->enum('type', ['grant', 'membership', 'simple'])->default('grant');
            $table->text('member_reason')->nullable();
            $table->text('member_benefit')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('github')->nullable();
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
