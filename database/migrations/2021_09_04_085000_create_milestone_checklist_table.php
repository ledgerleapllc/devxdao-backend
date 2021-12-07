<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class CreateMilestoneChecklistTable extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::create('milestone_checklist', function (Blueprint $table) {
            $table->id();
            $table->integer('user_id');
            $table->integer('milestone_id');
            $table->string('appl_accepted_definition')->nullable();
            $table->string('appl_accepted_pm')->nullable();
            $table->string('appl_attests_accounting')->nullable();
            $table->string('appl_attests_criteria')->nullable();
            $table->string('appl_submitted_corprus')->nullable();
            $table->string('appl_accepted_corprus')->nullable();
            $table->string('crdao_acknowledged_project')->nullable();
            $table->string('crdao_accepted_pm')->nullable();
            $table->string('crdao_acknowledged_receipt')->nullable();
            $table->string('crdao_submitted_review')->nullable();
            $table->string('crdao_submitted_subs')->nullable();
            $table->string('pm_submitted_evidence')->nullable();
            $table->string('pm_submitted_admin')->nullable();
            $table->string('pm_verified_corprus')->nullable();
            $table->string('pm_verified_crdao')->nullable();
            $table->string('pm_verified_subs')->nullable();
            $table->text('crdao_acknowledged_project_notes')->nullable();
            $table->text('crdao_accepted_pm_notes')->nullable();
            $table->text('crdao_acknowledged_receipt_notes')->nullable();
            $table->text('crdao_submitted_review_notes')->nullable();
            $table->text('crdao_submitted_subs_notes')->nullable();
            $table->text('pm_submitted_evidence_notes')->nullable();
            $table->text('pm_submitted_admin_notes')->nullable();
            $table->text('pm_verified_corprus_notes')->nullable();
            $table->text('pm_verified_crdao_notes')->nullable();
            $table->text('pm_verified_subs_notes')->nullable();
            $table->text('addition_note')->nullable();
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
        Schema::dropIfExists('milestone_checklist');
    }
}
