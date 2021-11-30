<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;

use App\User;
use App\Profile;
use App\Shuftipro;
use App\ShuftiproTemp;
use App\EmailerTriggerAdmin;
use App\EmailerTriggerUser;
use App\EmailerAdmin;

use App\Http\Helper;

use App\Mail\AdminAlert;
use App\Mail\UserAlert;
use App\Proposal;

class ShuftiproCheck extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'shuftipro:check';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Shuftipro Response';

    /**
     * Create a new command instance.
     *
     * @return void
     */
    public function __construct()
    {
        parent::__construct();
    }

    protected function process($item) 
    {
        $keys = [
            'production' => [
              'clientId' => config('services.shuftipro.client_id_prod'),
              'clientSecret' => config('services.shuftipro.client_secret_prod'),
            ],
            'test' => [
              'clientId' => config('services.shuftipro.client_id_test'),
              'clientSecret' => config('services.shuftipro.client_secret_test'),
            ]
        ];

        $mode = 'production';

        $url = 'https://api.shuftipro.com/status';
        $client_id  = $keys[$mode]['clientId'];
        $secret_key = $keys[$mode]['clientSecret'];

        $auth = $client_id . ":" . $secret_key;

        $response = Http::withBasicAuth($client_id, $secret_key)->post($url, [
            'reference' => $item->reference_id
        ]);

        $data = $response->json();
        if (!$data || !is_array($data)) return;

        if (
            !isset($data['reference']) || 
            !isset($data['event'])
        ) {
            return "error";
        }

        $events = [
            'verification.accepted', 
            'verification.declined'
        ];

        $user_id = (int) $item->user_id;
        $reference_id = $data['reference'];
        $event = $data['event'];

        // Remove Other Temp Records
        ShuftiproTemp::where('user_id', $user_id)
                     ->where('reference_id', '!=', $reference_id)
                     ->delete();
    
        // Event Validation
        if (!in_array($event, $events))
            return "error";

        // Temp Record
        $temp = ShuftiproTemp::where('reference_id', $reference_id)->first();
        if (!$temp) return "error";
        
        $declined_reason = isset($data['declined_reason']) ? $data['declined_reason'] : null;
        $proofs = isset($data['proofs']) ? $data['proofs'] : null;
        $verification_result = isset($data['verification_result']) ? $data['verification_result'] : null;
        $verification_data = isset($data['verification_data']) ? $data['verification_data'] : null;
  
        $is_successful = $event == 'verification.accepted' ? 1 : 0;
        $status = $is_successful ? 'approved' : 'denied';
  
        $data = json_encode([
            'declined_reason' => $declined_reason,
            // 'event' => $event,
            // 'proofs' => $proofs,
            // 'verification_result' => $verification_result,
            // 'verification_data' => $verification_data
        ]);
  
        $document_proof = $address_proof = null;
        $document_result = 
        $address_result = 
        $background_checks_result = 0;
  
        // Document Proof
        if (
            $proofs && 
            isset($proofs['document']) && 
            isset($proofs['document']['proof'])
        ) {
            $document_proof = $proofs['document']['proof'];
        }
  
        // Address Proof
        if (
            $proofs && 
            isset($proofs['address']) && 
            isset($proofs['address']['proof']) 
        ) {
            $address_proof = $proofs['address']['proof'];
        }
  
        // Document Result
        if (
            $verification_result && 
            isset($verification_result['document'])
        ) {
            $zeroCount = $oneCount = 0;
            foreach ($verification_result['document'] as $key => $value) {
                if ($key == 'document_proof') continue;
          
                $value = (int) $value;

                if ($value)
                    $oneCount++;
                else
                    $zeroCount++;
            }
  
            if ($oneCount && !$zeroCount)
                $document_result = 1;
        }
  
        // Address Result
        if (
            $verification_result && 
            isset($verification_result['address'])
        ) {
            $zeroCount = $oneCount = 0;
            foreach ($verification_result['address'] as $key => $value) {
                if ($key == 'address_document_proof') continue;

                $value = (int) $value;

                if ($value)
                    $oneCount++;
                else
                    $zeroCount++;
            }
  
            if ($oneCount && !$zeroCount)
                $address_result = 1;
        }
  
        // Background Checks Result
        if (
            $verification_result && 
            isset($verification_result['background_checks']) && 
            (int) $verification_result['background_checks'] === 1
        ) {
            $background_checks_result = 1;
        }
  
        Shuftipro::where('user_id', $user_id)->delete();
      
        $record = new Shuftipro;
        $record->user_id = $user_id;
        $record->reference_id = $reference_id;
        $record->is_successful = $is_successful;
        $record->data = $data;
        $record->document_result = $document_result;
        $record->address_result = $address_result;
        $record->background_checks_result = $background_checks_result;
        $record->status = $status;
        $record->reviewed = $is_successful ? 1 : 0; // No need to review successful ones
        
        if ($document_proof)
            $record->document_proof = $document_proof;
        if ($address_proof)
            $record->address_proof = $address_proof;

        $record->save();
      
        // Update Temp Record
        $temp->status = 'processed';
        $temp->save();

        $emailerData = Helper::getEmailerData();
        $user = User::find($user_id);

        // Emailer Admin
        if ($user) {
            $proposal = Proposal::where('user_id', $user_id)->first();
            Helper::triggerAdminEmail('KYC Review', $emailerData, $proposal, null, $user);
            if ($status == "approved"){
                Helper::triggerUserEmail($user, 'AML Approve', $emailerData);
            }
            // else
            //     Helper::triggerUserEmail($user, 'AML Deny', $emailerData);
        }
    }

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        // Runs Every 5 Mins ( 300 Seconds )
        // Process 20 per Run
        
        $limit = 20;
        $records = ShuftiproTemp::where('status', 'booked')
                                  ->orderBy('id', 'asc')
                                  ->offset(0)
                                  ->limit($limit)
                                  ->get();

        if (count($records)) {
            foreach ($records as $record) {
                $this->process($record);
            }
        }
    }
}
