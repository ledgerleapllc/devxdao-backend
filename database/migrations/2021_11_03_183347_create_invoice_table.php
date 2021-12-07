<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateInvoiceTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('invoice', function (Blueprint $table) {
            $table->id();
            $table->string('code');
            $table->integer('proposal_id');
            $table->integer('milestone_id');
            $table->integer('payee_id')->nullable();
            $table->string('payee_email')->nullable();
            $table->tinyInteger('paid')->default(0);
            $table->timestamp('marked_paid_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->string('pdf_url')->nullable();
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
        Schema::dropIfExists('invoice');
    }
}
