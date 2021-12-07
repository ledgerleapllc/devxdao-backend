<?php

use App\Setting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

class UpdateDataSeting extends Migration
{
    /**
     * Run the migrations.
     *
     * @return void
     */
    public function up()
    {
        $names = [
            'gate_new_milestone_votes' => 'no',
        ];

        foreach ($names as $name => $value) {
            $setting = Setting::where('name', $name)->first();
            if (!$setting) {
                $setting = new Setting();
                $setting->name = $name;
                $setting->value = $value;
                $setting->save();
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
