<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateProposalTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->text('short_description');
            $table->text('explanation_benefit');
            $table->text('explanation_goal');
            $table->double('total_grant', 15, 2);
            $table->integer('license')->default(0);
            $table->string('license_other')->nullable();
            $table->string('relationship');
            $table->boolean('received_grant_before')->default(0);
            $table->string('grant_id')->nullable();
            $table->boolean('has_fulfilled')->default(0);
            $table->text('previous_work')->nullable();
            $table->text('other_work')->nullable();
            $table->boolean('received_grant')->default(0);
            $table->text('foundational_work')->nullable();
            $table->text('files')->nullable();
            $table->enum('status', [
                'pending',
                'payment',
                'approved',
                'denied',
                'completed'
            ])->default('pending');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     *
     * @return void
     */
    public function down()
    {
        Schema::dropIfExists('proposal');
    }
}
