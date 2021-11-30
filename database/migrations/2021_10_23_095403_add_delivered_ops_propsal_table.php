<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class AddDeliveredOpsPropsalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal', function (Blueprint $table) {
            $table->text('things_delivered')->nullable();
            $table->timestampTz('delivered_at', 0)->nullable();
            \DB::statement("ALTER TABLE `proposal` CHANGE `type` `type` ENUM('grant', 'membership', 'simple', 'admin-grant') DEFAULT 'grant';");
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
