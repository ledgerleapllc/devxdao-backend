<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProposal14Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal', function ($table) {
            $table->boolean('yesNo1')->default(false);
            $table->text('yesNo1Exp')->nullable();
            $table->boolean('yesNo2')->default(false);
            $table->text('yesNo2Exp')->nullable();
            $table->boolean('yesNo3')->default(false);
            $table->text('yesNo3Exp')->nullable();
            $table->boolean('yesNo4')->default(false);
            $table->text('yesNo4Exp')->nullable();
            $table->text('formField1')->nullable();
            $table->text('formField2')->nullable();
            $table->string('purpose')->nullable();
            $table->string('purposeOther')->nullable();
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
