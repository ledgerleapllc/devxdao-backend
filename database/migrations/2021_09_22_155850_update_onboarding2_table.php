<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateOnboarding2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('onboarding', function (Blueprint $table) {
            $table->string('compliance_status')->nullable();
            $table->string('compliance_token')->nullable();
            $table->timestamp('compliance_reviewed_at')->nullable();
            $table->text('deny_reason')->nullable();
            $table->string('admin_email')->nullable();
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
