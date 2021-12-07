<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Doctrine\DBAL\Types\FloatType;
use Doctrine\DBAL\Types\Type;

class ChangeTypeColumnRep extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        if (!Type::hasType('double')) {
            Type::addType('double', FloatType::class);
        }
        Schema::table('reputation', function (Blueprint $table) {
            $table->double('value', 15, 5)->change();
            $table->double('staked', 15, 5)->change();
            $table->double('pending', 15, 5)->change();
        });

        Schema::table('profile', function (Blueprint $table) {
            $table->double('rep', 15, 5)->change();
        });

        Schema::table('rep_history', function (Blueprint $table) {
            $table->double('value', 15, 5)->change();
            $table->double('rep', 15, 5)->change();
        });

        Schema::table('vote_result', function (Blueprint $table) {
            $table->double('value', 15, 5)->change();
        });

        Schema::table('vote', function (Blueprint $table) {
            $table->double('for_value', 15, 5)->change();
            $table->double('against_value', 15, 5)->change();
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
