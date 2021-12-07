<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\Profile;
use App\Reputation;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class UpdateRepProfile extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'update:rep-profile';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update rep profile';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $profiles = Profile::get();
        foreach ($profiles as $profile) {
            $total_minted = Reputation::where(function ($query) {
                $query->where('type', 'Gained')
                    ->orWhere('type', 'Minted')
                    ->orWhere('type', 'Stake Lost')
                    ->orWhere('type', 'Lost');
            })->where('user_id', $profile->user_id)->sum('value');
            $total_staked = Reputation::where('type', 'Staked')->where('user_id', $profile->user_id)->sum('staked');
            $rep = $total_minted + $total_staked;
            if ($rep < 0) {
                $rep = 0;
            }
            $profile->rep = $rep;
            $profile->save();
            Helper::createRepHistory($profile->user_id, $rep, $profile->rep, '', 'recalc available rep');
        }
    }
}
