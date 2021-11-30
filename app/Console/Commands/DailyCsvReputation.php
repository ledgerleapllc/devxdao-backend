<?php

namespace App\Console\Commands;

use App\Exports\MyReputationExport;
use App\Mail\DailyReputation;
use App\Reputation;
use App\User;
use Carbon\Carbon;
use Illuminate\Console\Command;
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

            $file_path = "reputation/user_" . $userId . "_daily_reputation.csv";
            Excel::store(new MyReputationExport($items), $file_path, 'local');
            $path = url(Storage::url($file_path));
            Mail::to($user->email)->send(new DailyReputation($subject, $user->first_name, $today, $path));
        }
    }
}
