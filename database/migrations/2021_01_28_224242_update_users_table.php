<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateUsersTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('users', function ($table) {
            $table->boolean('is_admin')->default(0);
            $table->boolean('is_member')->default(0);
            $table->boolean('is_proposer')->default(0);
            $table->boolean('is_participant')->default(0);
            $table->boolean('is_guest')->default(0);
            $table->enum('status', ['pending', 'approved', 'denied'])->default('pending');
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
