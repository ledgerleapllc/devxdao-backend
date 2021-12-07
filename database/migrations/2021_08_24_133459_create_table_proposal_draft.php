<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateTableProposalDraft extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('proposal_draft', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->integer('user_id');
            $table->text('short_description')->nullable();
            $table->text('explanation_benefit')->nullable();
            $table->integer('license')->nullable();
            $table->string('license_other')->nullable();
            $table->float('total_grant')->nullable();
            $table->integer('member_required')->nullable();
            $table->integer('is_company_or_organization')->nullable();
            $table->text('members')->nullable();
            $table->text('grants')->nullable();
            $table->string('bank_name')->nullable();
            $table->string('iban_number')->nullable();
            $table->string('swift_number')->nullable();
            $table->string('holder_name')->nullable();
            $table->string('account_number')->nullable();
            $table->string('bank_address')->nullable();
            $table->string('bank_city')->nullable();
            $table->string('bank_country')->nullable();
            $table->string('holder_country')->nullable();
            $table->string('holder_zip')->nullable();
            $table->string('crypto_type')->nullable();
            $table->string('crypto_address')->nullable();
            $table->text('milestones')->nullable();
            $table->text('citations')->nullable();
            $table->string('relationship')->nullable();
            $table->integer('received_grant_before')->nullable();
            $table->integer('grant_id')->nullable();
            $table->integer('has_fulfilled')->nullable();
            $table->string('previous_work')->nullable();
            $table->string('other_work')->nullable();
            $table->string('include_membership')->nullable();
            $table->text('member_reason')->nullable();
            $table->text('member_benefit')->nullable();
            $table->string('linkedin')->nullable();
            $table->string('github')->nullable();
            $table->string('sponsor_code_id')->nullable();
            $table->string('name_entity')->nullable();
            $table->string('entity_country')->nullable();
            $table->integer('have_mentor')->nullable();
            $table->string('name_mentor')->nullable();
            $table->integer('total_hours_mentor')->nullable();
            $table->string('agree1')->nullable();
            $table->string('agree2')->nullable();
            $table->string('agree3')->nullable();
            $table->text('tags')->nullable();
            $table->text('resume')->nullable();
            $table->text('extra_notes')->nullable();

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
        Schema::dropIfExists('proposal_draft');
    }
}
