<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProfile9Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('profile', function ($table) {
            $table->boolean('twoFA_login')->default(0);
            $table->string('twoFA_login_code')->nullable();
            $table->integer('twoFA_login_time')->nullable();
            $table->boolean('twoFA_login_active')->default(0);
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
