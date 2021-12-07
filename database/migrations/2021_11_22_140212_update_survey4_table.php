<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSurvey4Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('survey', function (Blueprint $table) {
            $table->string('type')->default('grant');
            $table->string('job_title')->nullable();
            $table->text('job_description')->nullable();
            $table->float('total_price', 10, 5)->nullable();
            $table->timestamp('job_start_date')->nullable();
            $table->timestamp('job_end_date')->nullable();
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
