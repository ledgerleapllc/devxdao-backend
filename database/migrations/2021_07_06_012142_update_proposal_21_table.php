<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateProposal21Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('proposal', function ($table) {
            $table->boolean('agree1')->default(false);
            $table->boolean('agree2')->default(false);

            $table->boolean('is_company_or_organization')->default(false);
            $table->text('name_entity')->nullable();
            $table->text('entity_country')->nullable();

            $table->boolean('have_mentor')->default(false);
            $table->text('name_mentor')->nullable();
            $table->text('total_hours_mentor')->nullable();
            
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
