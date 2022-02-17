<?php

namespace App\Console\Commands;

use App\Exports\MyReputationExport;
use App\Mail\DailyReputation;
use App\Reputation;
use App\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;
use Maatwebsite\Excel\Facades\Excel;

class DailyCsvReputation extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'daily-reputation';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Daily export csv reputation';

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
        $users = User::where('notice_send_reputation', 1)->get();
        $today = Carbon::now()->format('d-m-Y');
        $subject = "Your DxD REP summary for $today";
        foreach ($users as $user) {
            try {
                $userId = $user->id;
                $items = Reputation::leftJoin('proposal', 'proposal.id', '=', 'reputation.proposal_id')
                    ->leftJoin('users', 'users.id', '=', 'proposal.user_id')
                    ->where('reputation.user_id', $userId)
                    ->select([
                        'reputation.*',
                        'proposal.include_membership',
                        'proposal.title as proposal_title',
                        'users.first_name as op_first_name',
                        'users.last_name as op_last_name'
                    ])
                    ->orderBy('reputation.id', 'desc')
                    ->get();
                $total_staked = $items->where('type', 'Staked')->sum('staked');
                $total_minted = $items->whereIn('type', ['Gained', 'Minted', 'Stake Lost', 'Lost'])->sum('value');
                $total_pending = $items->where('type', 'Minted Pending')->sum('pending');
                foreach ($items as $item) {
                    $item->total_staked = $total_staked;
                    $item->total_minted = $total_minted;
                    $item->total_pending = $total_pending;
                }
                $file_path = "reputation/user_" . $userId . "_daily_reputation.csv";
                Excel::store(new MyReputationExport($items), $file_path, 'local');
                $path = url(Storage::url($file_path));
                Mail::to($user->email)->send(new DailyReputation($subject, $user->first_name, $today, $path));
            } catch (Exception $e) {
                Log::error('Daily export csv reputation error: ' . $e->getMessage());
            }
        }
    }
}
