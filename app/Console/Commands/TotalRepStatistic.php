<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class TotalRepStatistic extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'total-rep:statistic';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Total rep statistic';

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
        $users = User::with(['profile'])->where('is_member', 1)
            ->where('banned', 0)
            ->where('can_access', 1)
            ->get();
        $body = '<table><tbody>';
        foreach ($users as $user) {
            $total_staked = DB::table('reputation')
                ->where('user_id', $user->id)
                ->where('type', 'Staked')
                ->sum('staked');
            $total_pending = DB::table('reputation')
                ->where('user_id', $user->id)
                ->where('type', 'Minted Pending')
                ->sum('pending');    
            $total_staked = round(abs($total_staked), 2);
            $available = $user->profile->rep;
            $total = $available + $total_staked;
            if($total < 0) {
                $total = 0;
            }
            $body .= "<tr style='padding-bottom:30px'>
                <td style='vertical-align:top; padding-right: 10px;'>$user->email</td>
                <td style='padding-bottom: 8px'>
                    <b> Total: </b>  $total <br>
                    <b> Available:</b> $available <br>
                    <b> Staked: </b> $total_staked  <br>
                    <b> Minted Pending: </b> $total_pending  <br>
                </td>
            </tr>";
        }
        $body .= '</tbody></table>';
        // Emailer Admin
        $emailerData = Helper::getEmailerData();
        Helper::triggerAdminEmail('Total rep', $emailerData, null, null, null, $body);
    }
}
