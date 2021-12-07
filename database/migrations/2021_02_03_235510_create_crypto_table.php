<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateCryptoTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('crypto', function (Blueprint $table) {
            $table->id();
            $table->foreignId('proposal_id')->constrained('proposal');
            $table->string('public_address')->nullable();
            $table->enum('type', ['btc', 'eth', 'casper'])->nullable();
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
        Schema::dropIfExists('crypto');
    }
}
