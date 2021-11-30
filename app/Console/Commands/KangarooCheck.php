<?php

namespace App\Console\Commands;

use App\Http\Helper;
use App\OnBoarding;
use App\Proposal;
use App\Shuftipro;
use App\ShuftiproTemp;
use App\User;
use App\Vote;
use Illuminate\Console\Command;

class KangarooCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'kangaroo:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Kangaroo check';

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
        $records = ShuftiproTemp::where('status', 'booked')->whereNotNull('invite_id')
            ->orderBy('id', 'asc')
            ->get();

        foreach ($records as $record) {
            $response = Helper::getInviteKycKangaroo($record->invite_id);
            if ($response['success'] == true && $response['invite']) {
                $invite = $response['invite'];
                $record->reference_id = $invite['shufti_ref_id'];
                $record->save();
                $status =  $invite['status'];
                if ($status) {
                    $shuftipro = Shuftipro::where('user_id', $record->user_id)->first();
                    if(!$shuftipro) {
                        $shuftipro = new Shuftipro();
                    }
                    $shuftipro->reference_id = $invite['shufti_ref_id'] ?? '';
                    $shuftipro->status = $status;
                    $shuftipro->user_id = $record->user_id;
                    $shuftipro->is_successful = $invite['is_successful'] ?? 0;
                    $shuftipro->reviewed = $invite['reviewed'] ?? 0;
                    $data = json_encode([
                        'declined_reason' => $invite['declined_reason'] ?? null,
                        'event' => $invite['event'] ?? null,
                        'address_document' => $invite['address_document'] ?? null,
                        'profile_address' => $invite['profile_address'] ?? null,
                        'country_company' => $invite['country_company'] ?? null,
                        'api' => $invite['api'] ?? '',
                    ]);
                    $shuftipro->data = $data;
                    $shuftipro->address_result = isset($invite['address_document']) ? 1 : 0;
                    $shuftipro->save();
                    // Update Temp Record
                    if ($status == 'approved' || $status == 'denined') {
                        $record->status = 'processed';
                        $record->save();
                    }
                    if ($status == 'approved') {
                        $onboardings = OnBoarding::where('user_id', $record->user_id)->where('status', 'pending')->where('compliance_status', 'approved')->get();
                        foreach ($onboardings as $onboarding) {
                            $onboarding->status = 'completed';
                            $onboarding->save();
                            $vote = Vote::find($onboarding->vote_id);
                            $proposal = Proposal::find($onboarding->proposal_id);
                            $op = User::find($onboarding->user_id);
                            $emailerData = Helper::getEmailerData();
                            if ($vote && $op && $proposal) {
                                Helper::triggerUserEmail($op, 'Passed Informal Grant Vote', $emailerData, $proposal, $vote);
                            }
                            Helper::startFormalVote($vote);
                        }
                        $proposals = Proposal::where('user_id', $record->user_id)->get();
                        foreach($proposals as $proposal) {
                            Helper::createGrantTracking($proposal->id, "KYC checks complete", 'kyc_checks_complete');
                        }
                    }
                }
            }
        }
    }
}
