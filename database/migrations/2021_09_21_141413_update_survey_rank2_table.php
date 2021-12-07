<?php

use App\Survey;
use App\SurveyRank;
use App\SurveyResult;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateSurveyRank2Table extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        Schema::table('survey_rank', function (Blueprint $table) {
            $table->integer('total_point')->nullable();
        });

        $surveys = Survey::where('status', 'completed')->get();

        foreach ($surveys as $survey) {
            $reults = SurveyResult::where('survey_id', $survey->id)->get();
            $surey_grouped = $reults->groupBy('proposal_id');
            $responses = collect();
            foreach ($surey_grouped as $key => $value) {
                $total_point = $value->sum('point');
                $responses->push([
                    'proposal_id' => $key,
                    'total_point' => $total_point
                ]);
            }
            foreach ($responses as $response) {
                $survey_rank = SurveyRank::where('survey_id', $survey->id)->where('proposal_id',  $response['proposal_id'])->first();
                if($survey_rank) {
                    $survey_rank->total_point = $response['total_point'];
                    $survey_rank->save();
                }
            }
        }
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
