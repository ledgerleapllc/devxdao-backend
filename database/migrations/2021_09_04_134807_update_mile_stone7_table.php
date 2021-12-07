<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateMileStone7Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('milestone', function (Blueprint $table) {
            $table->string('attest_accepted_definition')->nullable();
            $table->string('attest_accepted_pm')->nullable();
            $table->string('attest_submitted_accounting')->nullable();
            $table->string('attest_work_adheres')->nullable();
            $table->string('attest_submitted_corprus')->nullable();
            $table->string('attest_accept_crdao')->nullable();
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
