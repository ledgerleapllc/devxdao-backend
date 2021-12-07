<?php

namespace App\Console\Commands;

use App\GrantTracking;
use App\OnBoarding;
use Illuminate\Console\Command;

class GrantTrackingComplianceComplete extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'grant-tracking:compliance-complete';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Grant tracking ETA compliance complete';

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
        $onboadings = OnBoarding::where('status', 'completed')->get();
        foreach ($onboadings as $onboading) {
            $grantTracking = GrantTracking::where('proposal_id', $onboading->proposal_id)->where('key', 'eta_compliance_complete')->first();
            if (!$grantTracking) {
                $grantTracking = new GrantTracking();
                $grantTracking->proposal_id = $onboading->proposal_id;
                $grantTracking->event = "ETA compliance complete";
                $grantTracking->key = 'eta_compliance_complete';
                $grantTracking->created_at = $onboading->updated_at;
                $grantTracking->save();
            }
        }
    }
}
